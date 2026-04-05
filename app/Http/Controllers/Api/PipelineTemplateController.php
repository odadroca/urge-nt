<?php

namespace App\Http\Controllers\Api;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateChannel;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use App\Services\PipelineTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineTemplateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $templates = PipelineTemplate::where('is_active', true)
            ->withCount('channels')
            ->orderBy('name')
            ->get();

        return $this->success($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $template = PipelineTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($template, 201);
    }

    public function show(PipelineTemplate $pipelineTemplate): JsonResponse
    {
        $pipelineTemplate->load(['channels.llmProvider']);

        return $this->success($pipelineTemplate);
    }

    public function update(Request $request, PipelineTemplate $pipelineTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $pipelineTemplate->update($validated);

        return $this->success($pipelineTemplate->fresh());
    }

    public function destroy(PipelineTemplate $pipelineTemplate): JsonResponse
    {
        $pipelineTemplate->delete();

        return $this->success(['message' => 'Template deleted.']);
    }

    public function addChannel(Request $request, PipelineTemplate $pipelineTemplate): JsonResponse
    {
        $validated = $request->validate([
            'role_label' => 'required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt' => 'nullable|string',
            'trigger' => 'required|in:parallel,synthesis',
            'sort_order' => 'integer|min:0',
        ]);

        $channel = PipelineTemplateChannel::create([
            'pipeline_template_id' => $pipelineTemplate->id,
            'role_label' => $validated['role_label'],
            'llm_provider_id' => $validated['llm_provider_id'] ?? null,
            'system_prompt' => $validated['system_prompt'] ?? null,
            'trigger' => $validated['trigger'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($channel, 201);
    }

    public function updateChannel(Request $request, PipelineTemplate $pipelineTemplate, PipelineTemplateChannel $channel): JsonResponse
    {
        if ($channel->pipeline_template_id !== $pipelineTemplate->id) {
            return $this->error('Channel does not belong to this template.', 404);
        }

        $validated = $request->validate([
            'role_label' => 'sometimes|required|string|max:255',
            'llm_provider_id' => 'nullable|integer|exists:llm_providers,id',
            'system_prompt' => 'nullable|string',
            'trigger' => 'sometimes|required|in:parallel,synthesis',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $channel->update($validated);

        return $this->success($channel->fresh());
    }

    public function removeChannel(PipelineTemplate $pipelineTemplate, PipelineTemplateChannel $channel): JsonResponse
    {
        if ($channel->pipeline_template_id !== $pipelineTemplate->id) {
            return $this->error('Channel does not belong to this template.', 404);
        }

        $channel->delete();

        return $this->success(['message' => 'Channel removed.']);
    }

    public function runTemplate(Request $request, string $username, string $promptSlug, PipelineTemplateService $service): JsonResponse
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

        $template = PipelineTemplate::where('slug', $validated['template_slug'])
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return $this->error('Template not found or inactive.', 404);
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
            $template,
            $version,
            $validated['variables'] ?? [],
            $request->user()->id,
        );

        return $this->success(['result_ids' => $resultIds]);
    }
}
