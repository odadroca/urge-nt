<?php

namespace App\Console\Commands;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Console\Command;

class SeedEvaluationCommand extends Command
{
    protected $signature = 'urge:seed-evaluation';
    protected $description = 'Create default evaluation prompt, pipeline, and settings';

    public function handle(): int
    {
        $user = User::first();
        if (!$user) {
            $this->error('No users exist. Register a user first.');
            return 1;
        }

        // 1. Create evaluation prompt (fragment)
        $prompt = Prompt::where('slug', 'system-evaluation-template')->first();
        if (!$prompt) {
            $prompt = Prompt::create([
                'name'        => 'System Evaluation Template',
                'type'        => 'fragment',
                'description' => 'Default evaluation prompt used to assess LLM results. Edit this to customize evaluation criteria.',
                'created_by'  => $user->id,
            ]);

            $content = <<<'TEMPLATE'
Evaluate the following LLM response against the original prompt.

ORIGINAL PROMPT:
---
{{original_prompt}}
---

LLM RESPONSE:
---
{{response_text}}
---

EVALUATION DIMENSIONS:
{{dimensions}}

RATING SCALE: {{scale}}

For each dimension, provide a score (1-5) and brief reasoning.
Return ONLY valid JSON in this exact format:
{"scores":[{"dimension":"dimension_name","score":N,"reasoning":"brief explanation"}]}
TEMPLATE;

            app(VersioningService::class)->createVersion($prompt, [
                'content'        => $content,
                'commit_message' => 'Default evaluation template',
            ], $user);

            $this->info("Created evaluation prompt: {$prompt->slug}");
        } else {
            $this->info("Evaluation prompt already exists: {$prompt->slug}");
        }

        // 2. Create evaluation pipeline with dimension channels
        $pipeline = Pipeline::where('slug', 'evaluation')->first();
        if (!$pipeline) {
            $defaultProvider = LlmProvider::where('is_active', true)->first();

            $pipeline = Pipeline::create([
                'name'        => 'Evaluation',
                'description' => 'Evaluates LLM results across quality dimensions: relevance, completeness, accuracy, clarity, conciseness. Each channel scores one dimension.',
                'is_active'   => true,
                'created_by'  => $user->id,
            ]);

            $dimensions = [
                ['label' => 'relevance',    'prompt' => 'Focus ONLY on the "relevance" dimension. Does the response address what the prompt asked for? Score 1-5 with reasoning. Return JSON: {"scores":[{"dimension":"relevance","score":N,"reasoning":"..."}]}'],
                ['label' => 'completeness', 'prompt' => 'Focus ONLY on the "completeness" dimension. Are all parts of the prompt addressed? Nothing missing? Score 1-5 with reasoning. Return JSON: {"scores":[{"dimension":"completeness","score":N,"reasoning":"..."}]}'],
                ['label' => 'accuracy',     'prompt' => 'Focus ONLY on the "accuracy" dimension. Is the information correct and well-reasoned? Score 1-5 with reasoning. Return JSON: {"scores":[{"dimension":"accuracy","score":N,"reasoning":"..."}]}'],
                ['label' => 'clarity',      'prompt' => 'Focus ONLY on the "clarity" dimension. Is the response well-structured and easy to follow? Score 1-5 with reasoning. Return JSON: {"scores":[{"dimension":"clarity","score":N,"reasoning":"..."}]}'],
                ['label' => 'conciseness',  'prompt' => 'Focus ONLY on the "conciseness" dimension. Right amount of detail — not too verbose, not too sparse? Score 1-5 with reasoning. Return JSON: {"scores":[{"dimension":"conciseness","score":N,"reasoning":"..."}]}'],
            ];

            foreach ($dimensions as $i => $dim) {
                PipelineChannel::create([
                    'pipeline_id'     => $pipeline->id,
                    'role_label'      => $dim['label'],
                    'llm_provider_id' => $defaultProvider?->id,
                    'system_prompt'   => $dim['prompt'],
                    'trigger'         => 'parallel',
                    'sort_order'      => $i,
                ]);
            }

            $this->info("Created evaluation pipeline: {$pipeline->slug} ({$pipeline->channels()->count()} channels)");
        } else {
            $this->info("Evaluation pipeline already exists: {$pipeline->slug}");
        }

        // 3. Set default settings
        EvaluationSetting::set('enabled', false);
        EvaluationSetting::set('auto_evaluate', false);
        EvaluationSetting::set('prompt_slug', $prompt->slug);
        EvaluationSetting::set('pipeline_slug', $pipeline->slug);
        EvaluationSetting::set('dimensions', config('urge.evaluation.default_dimensions'));

        $this->info('Evaluation settings initialized.');
        $this->info('Enable evaluation in Settings > Evaluation tab.');
        if (!LlmProvider::where('is_active', true)->exists()) {
            $this->warn('No active LLM provider found. Add one in Settings > LLM Providers, then assign it to evaluation pipeline channels.');
        }

        return 0;
    }
}
