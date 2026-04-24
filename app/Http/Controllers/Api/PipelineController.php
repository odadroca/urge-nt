<?php

namespace App\Http\Controllers\Api;

use App\Models\Pipeline;
use App\Models\PipelineChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $pipelines = Pipeline::where('is_active', true)
            ->withCount('channels')
            ->orderBy('name')
            ->get();

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

    public function show(Pipeline $pipeline): JsonResponse
    {
        $pipeline->load(['channels.llmProvider', 'channels.fragment:id,name,slug']);

        return $this->success($pipeline);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $pipeline->update($validated);

        return $this->success($pipeline->fresh());
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $pipeline->delete();

        return $this->success(['message' => 'Pipeline deleted.']);
    }

    public function addChannel(Request $request, Pipeline $pipeline): JsonResponse
    {
        $validated = $request->validate([
            'role_label' => 'required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt' => 'nullable|string',
            'prompt_fragment_id' => 'nullable|integer|exists:prompts,id',
            'trigger' => 'required|in:parallel,synthesis',
            'sort_order' => 'integer|min:0',
        ]);

        $channel = PipelineChannel::create([
            'pipeline_id' => $pipeline->id,
            'role_label' => $validated['role_label'],
            'llm_provider_id' => $validated['llm_provider_id'] ?? null,
            'system_prompt' => $validated['system_prompt'] ?? null,
            'prompt_fragment_id' => $validated['prompt_fragment_id'] ?? null,
            'trigger' => $validated['trigger'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $channel->load('fragment:id,name,slug');

        return $this->success($channel, 201);
    }

    public function updateChannel(Request $request, Pipeline $pipeline, PipelineChannel $channel): JsonResponse
    {
        if ($channel->pipeline_id !== $pipeline->id) {
            return $this->error('Channel does not belong to this pipeline.', 404);
        }

        $validated = $request->validate([
            'role_label' => 'sometimes|required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt' => 'nullable|string',
            'prompt_fragment_id' => 'nullable|integer|exists:prompts,id',
            'trigger' => 'sometimes|required|in:parallel,synthesis',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $channel->update($validated);

        return $this->success($channel->fresh()->load('fragment:id,name,slug'));
    }

    public function removeChannel(Pipeline $pipeline, PipelineChannel $channel): JsonResponse
    {
        if ($channel->pipeline_id !== $pipeline->id) {
            return $this->error('Channel does not belong to this pipeline.', 404);
        }

        $channel->delete();

        return $this->success(['message' => 'Channel removed.']);
    }

    public function runPipeline(Request $request, string $username, string $promptSlug, PipelineService $service): JsonResponse
    {
        $owner = User::where('slug', $username)->firstOrFail();
        $prompt = Prompt::where('created_by', $owner->id)
            ->where('slug', $promptSlug)
            ->firstOrFail();

        $validated = $request->validate([
            'template_slug' => 'required|string',
            'version' => 'nullable|integer',
            'variables' => 'nullable|array',
        ]);

        $pipeline = Pipeline::where('slug', $validated['template_slug'])
            ->where('is_active', true)
            ->first();

        if (!$pipeline) {
            return $this->error('Pipeline not found or inactive.', 404);
        }

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

        $resultIds = $service->run(
            $pipeline,
            $version,
            $validated['variables'] ?? [],
            $request->user()->id,
        );

        return $this->success(['result_ids' => $resultIds]);
    }
}
