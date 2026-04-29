<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PipelineChannel;
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
     * Channels with a configured + active LLM provider are dispatched server-side
     * and produce Results. Channels without an active provider are returned as
     * `pending_client_channels` so the caller (an LLM client) can execute them
     * locally and store the outputs back via store_result.
     *
     * @return array{
     *   result_ids: int[],
     *   pending_client_channels: array<int, array<string, mixed>>
     * }
     */
    public function run(
        Pipeline $pipeline,
        PromptVersion $version,
        array $variableValues,
        int $userId,
    ): array {
        $pipeline->load(['parallelChannels.llmProvider', 'synthesisChannel.llmProvider']);

        // Render the prompt content (strict: reject if required vars missing)
        $renderResult = $this->templateEngine->render(
            $version->content,
            $variableValues,
            $version->variable_metadata,
            null,
            strict: true,
        );
        $renderedContent = $renderResult['rendered'];

        $runId = (string) Str::uuid();
        $resultIds = [];
        $parallelResults = [];
        $pendingClient = [];

        // Parallel channels
        foreach ($pipeline->parallelChannels as $channel) {
            $systemPrompt = $this->resolveSystemPrompt($channel);

            if ($channel->execution_mode === 'client') {
                $pendingClient[] = [
                    'channel_id'    => $channel->id,
                    'role_label'    => $channel->role_label,
                    'trigger'       => $channel->trigger,
                    'sort_order'    => $channel->sort_order,
                    'system_prompt' => $systemPrompt,
                    'user_prompt'   => $renderedContent,
                ];
                continue;
            }

            $provider = $channel->llmProvider;
            $llmResult = $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $renderedContent);

            $result = Result::create([
                'prompt_id'         => $version->prompt_id,
                'prompt_version_id' => $version->id,
                'source'            => 'api',
                'role_label'        => $channel->role_label,
                'pipeline_id'       => $pipeline->id,
                'pipeline_run_id'   => $runId,
                'provider_name'     => $provider->name,
                'model_name'        => $llmResult->modelUsed,
                'llm_provider_id'   => $provider->id,
                'rendered_content'  => $renderedContent,
                'variables_used'    => !empty($variableValues) ? $variableValues : null,
                'response_text'     => $llmResult->text,
                'input_tokens'      => $llmResult->inputTokens,
                'output_tokens'     => $llmResult->outputTokens,
                'duration_ms'       => $llmResult->durationMs,
                'status'            => $llmResult->success ? 'success' : 'error',
                'error_message'     => $llmResult->error,
                'created_by'        => $userId,
            ]);

            $resultIds[] = $result->id;

            if ($llmResult->success) {
                $parallelResults[] = [
                    'role_label'    => $channel->role_label,
                    'response_text' => $llmResult->text,
                ];
            }
        }

        // Synthesis channel
        $synthesisChannel = $pipeline->synthesisChannel;
        if ($synthesisChannel) {
            $systemPrompt = $this->resolveSystemPrompt($synthesisChannel);

            if ($synthesisChannel->execution_mode === 'client') {
                // If at least one parallel ran server-side, hand the LLM a pre-built
                // synthesis input. Otherwise leave user_prompt null — the LLM will
                // fetch parallel results (its own + any server's) and build the input itself.
                $pendingClient[] = [
                    'channel_id'    => $synthesisChannel->id,
                    'role_label'    => $synthesisChannel->role_label,
                    'trigger'       => $synthesisChannel->trigger,
                    'sort_order'    => $synthesisChannel->sort_order,
                    'system_prompt' => $systemPrompt,
                    'user_prompt'   => !empty($parallelResults)
                        ? $this->buildSynthesisInput($parallelResults)
                        : null,
                ];
            } elseif (!empty($parallelResults)) {
                $synthesisInput = $this->buildSynthesisInput($parallelResults);
                $provider = $synthesisChannel->llmProvider;

                $llmResult = $this->dispatchService->dispatchWithSystem(
                    $provider,
                    $systemPrompt,
                    $synthesisInput,
                );

                $result = Result::create([
                    'prompt_id'         => $version->prompt_id,
                    'prompt_version_id' => $version->id,
                    'source'            => 'api',
                    'role_label'        => $synthesisChannel->role_label,
                    'pipeline_id'       => $pipeline->id,
                    'pipeline_run_id'   => $runId,
                    'provider_name'     => $provider->name,
                    'model_name'        => $llmResult->modelUsed,
                    'llm_provider_id'   => $provider->id,
                    'rendered_content'  => $synthesisInput,
                    'variables_used'    => !empty($variableValues) ? $variableValues : null,
                    'response_text'     => $llmResult->text,
                    'input_tokens'      => $llmResult->inputTokens,
                    'output_tokens'     => $llmResult->outputTokens,
                    'duration_ms'       => $llmResult->durationMs,
                    'status'            => $llmResult->success ? 'success' : 'error',
                    'error_message'     => $llmResult->error,
                    'created_by'        => $userId,
                ]);

                $resultIds[] = $result->id;
            }
        }

        return [
            'result_ids'              => $resultIds,
            'pending_client_channels' => $pendingClient,
        ];
    }

    private function resolveSystemPrompt(PipelineChannel $channel): string
    {
        $systemPrompt = $channel->system_prompt ?? '';

        if (!$systemPrompt) {
            return '';
        }

        // Resolve {{>slug}} includes in the system prompt
        $result = $this->templateEngine->render($systemPrompt, [], null);

        return $result['rendered'];
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
