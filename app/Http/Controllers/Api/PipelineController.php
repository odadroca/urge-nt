<?php

namespace App\Http\Controllers\Api;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends ApiController
{
    use ResolvesPrompts;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Pipeline::where('is_active', true)->withCount('channels');

        if (!$user->isAdmin()) {
            $query->where('created_by', $user->id);
        }

        $pipelines = $query->orderBy('name')->get();

        return $this->success($pipelines);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $pipeline = Pipeline::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($pipeline, 201);
    }

    public function show(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'view');

        $pipeline->load(['channels.llmProvider']);

        $hasClient = false;
        $channels = $pipeline->channels->map(function (PipelineChannel $channel) use (&$hasClient) {
            $mode = $channel->execution_mode;
            if ($mode === 'client') {
                $hasClient = true;
            }

            return [
                'id'              => $channel->id,
                'pipeline_id'     => $channel->pipeline_id,
                'role_label'      => $channel->role_label,
                'system_prompt'   => $channel->system_prompt,
                'input_source'    => $channel->input_source ?? 'prompt',
                'input_filters'   => $channel->input_filters,
                'trigger'         => $channel->trigger,
                'sort_order'      => $channel->sort_order,
                'llm_provider_id' => $channel->llm_provider_id,
                'provider'        => $channel->llmProvider ? [
                    'id'        => $channel->llmProvider->id,
                    'name'      => $channel->llmProvider->name,
                    'model'     => $channel->llmProvider->model,
                    'is_active' => $channel->llmProvider->is_active,
                ] : null,
                'execution_mode'  => $mode,
            ];
        })->all();

        return $this->success([
            'id'                  => $pipeline->id,
            'name'                => $pipeline->name,
            'slug'                => $pipeline->slug,
            'description'         => $pipeline->description,
            'is_active'           => $pipeline->is_active,
            'created_by'          => $pipeline->created_by,
            'created_at'          => $pipeline->created_at,
            'updated_at'          => $pipeline->updated_at,
            'channels'            => $channels,
            'has_client_channels' => $hasClient,
        ]);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'update');

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $pipeline->update($validated);

        return $this->success($pipeline->fresh());
    }

    public function destroy(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'delete');

        $pipeline->delete();

        return $this->success(['message' => 'Pipeline deleted.']);
    }

    public function addChannel(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'manageChannels');

        $validated = $request->validate([
            'role_label'      => 'required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt'   => 'nullable|string',
            'input_source'    => 'nullable|in:prompt,result_history',
            'input_filters'   => 'nullable|array',
            'trigger'         => 'required|in:parallel,synthesis',
            'sort_order'      => 'integer|min:0',
        ]);

        $channel = PipelineChannel::create([
            'pipeline_id'     => $pipeline->id,
            'role_label'      => $validated['role_label'],
            'llm_provider_id' => $validated['llm_provider_id'] ?? null,
            'system_prompt'   => $validated['system_prompt'] ?? null,
            'input_source'    => $validated['input_source'] ?? 'prompt',
            'input_filters'   => $validated['input_filters'] ?? null,
            'trigger'         => $validated['trigger'],
            'sort_order'      => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($channel, 201);
    }

    public function updateChannel(Request $request, Pipeline $pipeline, PipelineChannel $channel): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'manageChannels');

        if ($channel->pipeline_id !== $pipeline->id) {
            return $this->error('Channel does not belong to this pipeline.', 404);
        }

        $validated = $request->validate([
            'role_label'      => 'sometimes|required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt'   => 'nullable|string',
            'input_source'    => 'sometimes|in:prompt,result_history',
            'input_filters'   => 'nullable|array',
            'trigger'         => 'sometimes|required|in:parallel,synthesis',
            'sort_order'      => 'sometimes|integer|min:0',
        ]);

        $channel->update($validated);

        return $this->success($channel->fresh());
    }

    public function removeChannel(Request $request, Pipeline $pipeline, PipelineChannel $channel): JsonResponse
    {
        $this->authorizePipeline($request, $pipeline, 'manageChannels');

        if ($channel->pipeline_id !== $pipeline->id) {
            return $this->error('Channel does not belong to this pipeline.', 404);
        }

        $channel->delete();

        return $this->success(['message' => 'Channel removed.']);
    }

    public function runPipeline(Request $request, string $username, string $promptSlug, PipelineService $service): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'template_slug' => 'required|string',
            'version' => 'nullable|integer',
            'variables' => 'nullable|array',
            'run_source' => 'nullable|in:manual,scheduled',
        ]);

        $pipeline = Pipeline::where('slug', $validated['template_slug'])
            ->where('is_active', true)
            ->first();

        if (!$pipeline) {
            return $this->error('Pipeline not found or inactive.', 404);
        }

        $this->authorizePipeline($request, $pipeline, 'run');

        $version = null;
        if (!empty($validated['version'])) {
            $version = PromptVersion::where('prompt_id', $prompt->id)
                ->where('version_number', $validated['version'])
                ->first();
        } else {
            $version = $prompt->active_version;
        }

        if (!$version) {
            return $this->error('Version not found.', 404);
        }

        $runResult = $service->run(
            $pipeline,
            $version,
            $validated['variables'] ?? [],
            $request->user()->id,
            $validated['run_source'] ?? null,
        );

        return $this->success($runResult);
    }

    private function authorizePipeline(Request $request, Pipeline $pipeline, string $ability): void
    {
        $user = $request->user();
        if (!$user->can($ability, $pipeline)) {
            $code = $ability === 'view' ? 404 : 403;
            $message = $code === 404
                ? 'Pipeline not found.'
                : "You do not have permission to {$ability} this pipeline.";
            abort($code, $message);
        }
    }
}
