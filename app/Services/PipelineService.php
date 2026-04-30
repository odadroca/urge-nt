<?php

namespace App\Services;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Models\User;
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
     * Each channel's user_prompt is determined by its input_source:
     * - "prompt" (default): the rendered prompt content (parallel) or this run's
     *   parallel outputs (synthesis)
     * - "result_history": a serialized batch of past Results matching the
     *   channel's input_filters, used for trend / drift analysis
     *
     * Channels with a configured + active LLM provider are dispatched server-side
     * and produce Results. Channels without an active provider land in
     * pending_client_channels so the caller can execute them locally and store
     * the outputs back via store_result.
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
        ?string $runSource = null,
    ): array {
        $pipeline->load(['parallelChannels.llmProvider', 'synthesisChannel.llmProvider']);

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

        $user = User::find($userId);

        foreach ($pipeline->parallelChannels as $channel) {
            $systemPrompt = $this->resolveSystemPrompt($channel);
            $userPrompt = $this->resolveUserPrompt($channel, $renderedContent, $version, $user);

            // result_history with no matching results → skip the channel.
            // This is normal for the first scheduled run when no history exists yet.
            if ($userPrompt === null) {
                continue;
            }

            if ($channel->execution_mode === 'client') {
                $pendingClient[] = [
                    'channel_id'    => $channel->id,
                    'role_label'    => $channel->role_label,
                    'trigger'       => $channel->trigger,
                    'sort_order'    => $channel->sort_order,
                    'input_source'  => $channel->input_source,
                    'system_prompt' => $systemPrompt,
                    'user_prompt'   => $userPrompt,
                ];
                continue;
            }

            $provider = $channel->llmProvider;
            $llmResult = $this->dispatchService->dispatchWithSystem($provider, $systemPrompt, $userPrompt);

            $result = Result::create([
                'prompt_id'         => $version->prompt_id,
                'prompt_version_id' => $version->id,
                'source'            => 'api',
                'run_source'        => $runSource,
                'role_label'        => $channel->role_label,
                'pipeline_id'       => $pipeline->id,
                'pipeline_run_id'   => $runId,
                'provider_name'     => $provider->name,
                'model_name'        => $llmResult->modelUsed,
                'llm_provider_id'   => $provider->id,
                'rendered_content'  => $userPrompt,
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

        $synthesisChannel = $pipeline->synthesisChannel;
        if ($synthesisChannel) {
            $systemPrompt = $this->resolveSystemPrompt($synthesisChannel);
            $synthesisInput = $this->resolveSynthesisInput(
                $synthesisChannel,
                $parallelResults,
                $version,
                $user,
            );

            // null synthesis input means: result_history selected but no matches,
            // OR no parallels available for the default behaviour. Either way,
            // we'd dispatch the LLM with an empty user_prompt — skip server-side,
            // surface as pending_client only if the channel is client-mode.

            if ($synthesisChannel->execution_mode === 'client') {
                $pendingClient[] = [
                    'channel_id'    => $synthesisChannel->id,
                    'role_label'    => $synthesisChannel->role_label,
                    'trigger'       => $synthesisChannel->trigger,
                    'sort_order'    => $synthesisChannel->sort_order,
                    'input_source'  => $synthesisChannel->input_source,
                    'system_prompt' => $systemPrompt,
                    'user_prompt'   => $synthesisInput,
                ];
            } elseif ($synthesisInput !== null && $synthesisInput !== '') {
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
                    'run_source'        => $runSource,
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

    /**
     * @return string|null Returns null when input_source=result_history matches
     *                    no results — caller treats that as "skip this channel".
     */
    private function resolveUserPrompt(
        PipelineChannel $channel,
        string $renderedContent,
        PromptVersion $version,
        ?User $user,
    ): ?string {
        if ($channel->input_source !== 'result_history') {
            return $renderedContent;
        }

        return $this->buildResultHistoryInput($channel, $version, $user);
    }

    /**
     * Synthesis channels: input_source=result_history overrides the default
     * "build from this run's parallel results" behaviour.
     */
    private function resolveSynthesisInput(
        PipelineChannel $synthesisChannel,
        array $parallelResults,
        PromptVersion $version,
        ?User $user,
    ): ?string {
        if ($synthesisChannel->input_source === 'result_history') {
            return $this->buildResultHistoryInput($synthesisChannel, $version, $user);
        }

        return !empty($parallelResults) ? $this->buildSynthesisInput($parallelResults) : null;
    }

    /**
     * Build a serialized batch of past Results for a channel with
     * input_source=result_history. Returns null when no results match.
     *
     * Visibility: only Results whose parent Prompt is visible to $user are
     * included. Without a user (e.g. some test paths) we return null.
     */
    private function buildResultHistoryInput(
        PipelineChannel $channel,
        PromptVersion $version,
        ?User $user,
    ): ?string {
        if (!$user) {
            return null;
        }

        $filters = $channel->input_filters ?? [];

        $targetSlug = $filters['prompt_slug'] ?? $version->prompt->slug;
        $ownerSlug  = $filters['owner'] ?? null;

        $promptQuery = Prompt::visibleTo($user)->where('slug', $targetSlug);
        if ($ownerSlug) {
            $owner = User::where('slug', $ownerSlug)->first();
            if (!$owner) {
                return null;
            }
            $promptQuery->where('created_by', $owner->id);
        }
        $targetPrompt = $promptQuery->first();
        if (!$targetPrompt) {
            return null;
        }

        $query = Result::where('prompt_id', $targetPrompt->id);

        if (!empty($filters['since'])) {
            try {
                $since = now()->sub(new \DateInterval($filters['since']));
                $query->where('created_at', '>=', $since);
            } catch (\Exception $e) {
                // Invalid duration — ignore the filter rather than fail the run
            }
        }

        if (!empty($filters['run_source']) && in_array($filters['run_source'], ['manual', 'scheduled'], true)) {
            $query->where('run_source', $filters['run_source']);
        }

        if (empty($filters['include_failures'])) {
            $query->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'error');
            });
        }

        $limit = min((int) ($filters['limit'] ?? 50), 100);

        $results = $query->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        $sections = $results->map(function (Result $r) {
            $providerLabel = trim(($r->provider_name ?? '') . ' · ' . ($r->model_name ?? ''), ' ·');
            $header = '[' . $r->created_at->toIso8601String() . ']';
            if ($providerLabel) {
                $header .= " [{$providerLabel}]";
            }
            return "{$header}\n" . ($r->response_text ?? '');
        })->all();

        return implode("\n\n---\n\n", $sections);
    }

    private function resolveSystemPrompt(PipelineChannel $channel): string
    {
        $systemPrompt = $channel->system_prompt ?? '';

        if (!$systemPrompt) {
            return '';
        }

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
