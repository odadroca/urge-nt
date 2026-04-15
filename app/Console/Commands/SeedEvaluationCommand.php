<?php

namespace App\Console\Commands;

use App\Models\EvaluationSetting;
use App\Models\Prompt;
use App\Models\User;
use App\Services\VersioningService;
use Illuminate\Console\Command;

class SeedEvaluationCommand extends Command
{
    protected $signature = 'urge:seed-evaluation';
    protected $description = 'Create default evaluation prompt and settings';

    public function handle(): int
    {
        $user = User::first();
        if (!$user) {
            $this->error('No users exist. Register a user first.');
            return 1;
        }

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

        EvaluationSetting::set('enabled', false);
        EvaluationSetting::set('auto_evaluate', false);
        EvaluationSetting::set('prompt_slug', $prompt->slug);
        EvaluationSetting::set('dimensions', config('urge.evaluation.default_dimensions'));

        $this->info('Evaluation settings initialized.');
        $this->info('Enable evaluation in Settings > Evaluation tab.');

        return 0;
    }
}
