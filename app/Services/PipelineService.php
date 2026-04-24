<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PromptVersion;
use App\Models\Result;
use Illuminate\Support\Str;

class PipelineService
{
    public function __construct(
        private TemplateEngine $templateEngine,
        private LlmDispatchService $dispatchService,
    ) {}

    /**
     * Run a pipeline against a prompt version.
     *
     * @return array Array of created Result ids
     */
    public function run(
        Pipeline $pipeline,
        PromptVersion $version,
        array $variableValues,
        int $userId,
    ): array {
        $pipeline->load([
            'parallelChannels.llmProvider',
            'parallelChannels.fragment.activeVersion',
            'synthesisChannel.llmProvider',
            'synthesisChannel.fragment.activeVersion',
        ]);

        // Render the prompt content
        $renderResult = $this->templateEngine->render(
            $version->content,
            $variableValues,
            $version->variable_metadata,
        );
        $renderedContent = $renderResult['rendered'];

        $runId = (string) Str::uuid();
        $resultIds = [];
        $parallelResults = [];

        // Dispatch parallel channels
        foreach ($pipeline->parallelChannels as $channel) {
            $provider = $channel->llmProvider;
            if (!$provider || !$provider->is_active) {
                continue;
            }

            $systemPrompt = $this->resolveSystemPrompt($channel);
            $llmResult = $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $renderedContent);

            $result = Result::create([
                'prompt_id' => $version->prompt_id,
                'prompt_version_id' => $version->id,
                'source' => 'api',
                'role_label' => $channel->role_label,
                'pipeline_id' => $pipeline->id,
                'pipeline_run_id' => $runId,
                'provider_name' => $provider->name,
                'model_name' => $llmResult->modelUsed,
                'llm_provider_id' => $provider->id,
                'rendered_content' => $renderedContent,
                'variables_used' => !empty($variableValues) ? $variableValues : null,
                'response_text' => $llmResult->text,
                'input_tokens' => $llmResult->inputTokens,
                'output_tokens' => $llmResult->outputTokens,
                'duration_ms' => $llmResult->durationMs,
                'status' => $llmResult->success ? 'success' : 'error',
                'error_message' => $llmResult->error,
                'created_by' => $userId,
            ]);

            $resultIds[] = $result->id;

            if ($llmResult->success) {
                $parallelResults[] = [
                    'role_label' => $channel->role_label,
                    'response_text' => $llmResult->text,
                ];
            }
        }

        // Dispatch synthesis channel if present
        $synthesisChannel = $pipeline->synthesisChannel;
        if ($synthesisChannel && $synthesisChannel->llmProvider && $synthesisChannel->llmProvider->is_active && !empty($parallelResults)) {
            $synthesisInput = $this->buildSynthesisInput($parallelResults);
            $systemPrompt = $this->resolveSystemPrompt($synthesisChannel);

            $llmResult = $this->dispatchService->dispatchWithSystem(
                $synthesisChannel->llmProvider,
                $systemPrompt,
                $synthesisInput,
            );

            $result = Result::create([
                'prompt_id' => $version->prompt_id,
                'prompt_version_id' => $version->id,
                'source' => 'api',
                'role_label' => $synthesisChannel->role_label,
                'pipeline_id' => $pipeline->id,
                'pipeline_run_id' => $runId,
                'provider_name' => $synthesisChannel->llmProvider->name,
                'model_name' => $llmResult->modelUsed,
                'llm_provider_id' => $synthesisChannel->llmProvider->id,
                'rendered_content' => $synthesisInput,
                'variables_used' => !empty($variableValues) ? $variableValues : null,
                'response_text' => $llmResult->text,
                'input_tokens' => $llmResult->inputTokens,
                'output_tokens' => $llmResult->outputTokens,
                'duration_ms' => $llmResult->durationMs,
                'status' => $llmResult->success ? 'success' : 'error',
                'error_message' => $llmResult->error,
                'created_by' => $userId,
            ]);

            $resultIds[] = $result->id;
        }

        return $resultIds;
    }

    private function resolveSystemPrompt(\App\Models\PipelineChannel $channel): string
    {
        $fragmentContent = '';
        if ($channel->fragment && $channel->fragment->activeVersion) {
            $fragmentContent = $channel->fragment->activeVersion->content ?? '';
        }

        $systemPrompt = $channel->system_prompt ?? '';

        if ($fragmentContent && $systemPrompt) {
            return $fragmentContent . "\n\n" . $systemPrompt;
        }

        return $fragmentContent ?: $systemPrompt;
    }

    private function buildSynthesisInput(array $parallelResults): string
    {
        $sections = [];
        foreach ($parallelResults as $entry) {
            $label = strtoupper($entry['role_label']);
            $sections[] = "[{$label}]\n{$entry['response_text']}";
        }

        return implode("\n\n", $sections);
    }
}
