<?php

namespace App\Http\Controllers\Api;

use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\Team;
use App\Services\LlmDispatchService;
use App\Services\TemplateEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptController extends ApiController
{
    use ResolvesPrompts;

    public function index(Request $request): JsonResponse
    {
        $query = Prompt::query()->with(['category', 'latestVersion', 'creator']);

        // Apply visibility scoping
        if ($request->user()) {
            $query->visibleTo($request->user());
        }

        // Scope filter: mine, shared, all (default)
        $scope = $request->input('scope', 'all');
        if ($scope === 'mine') {
            $query->where('created_by', $request->user()->id);
        } elseif ($scope === 'shared') {
            $query->where('created_by', '!=', $request->user()->id);
        }

        // Scope to allowed prompts if key has scoped access
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey && $apiKey->prompts()->exists()) {
            $query->whereIn('id', $apiKey->prompts()->pluck('prompts.id'));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($tag = $request->input('tag')) {
            $query->whereJsonContains('tags', $tag);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('updated_at');

        return $this->paginated($query, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'in:prompt,fragment',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'visibility' => 'nullable|in:private,shared',
            'derived_from' => 'nullable|string',
        ]);

        $derivedFromId = null;
        if (! empty($validated['derived_from'])) {
            $sourcePrompt = Prompt::where('slug', $validated['derived_from'])->first();
            if ($sourcePrompt) {
                $derivedFromId = $sourcePrompt->id;
            }
        }

        $prompt = Prompt::create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'prompt',
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'created_by' => $request->user()->id,
            'derived_from_prompt_id' => $derivedFromId,
        ]);

        return $this->success($prompt->load('category', 'creator:id,name,slug'), 201);
    }

    public function show(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $prompt->load(['category', 'activeVersion', 'creator']);
        $prompt->loadCount(['versions', 'results']);

        return $this->success($prompt);
    }

    public function update(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|in:prompt,fragment',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'visibility' => 'nullable|in:private,shared',
            'derived_from_prompt_id' => 'nullable|integer|exists:prompts,id',
        ]);

        $prompt->update($validated);

        return $this->success($prompt->fresh()->load('category'));
    }

    public function destroy(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $prompt->delete();

        return $this->success(['message' => 'Prompt deleted.']);
    }

    public function share(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $validated = $request->validate([
            'team_id' => 'required_without:team_slug|nullable|exists:teams,id',
            'team_slug' => 'required_without:team_id|nullable|string',
        ]);

        if (! empty($validated['team_id'])) {
            $team = Team::findOrFail($validated['team_id']);
        } else {
            $team = Team::where('slug', $validated['team_slug'])->firstOrFail();
        }

        // Attach team if not already attached
        if (! $prompt->teams()->where('teams.id', $team->id)->exists()) {
            $prompt->teams()->attach($team->id);
        }

        $prompt->update(['visibility' => 'shared']);

        return $this->success(['message' => 'Prompt shared with team.', 'team' => $team->slug]);
    }

    public function unshare(Request $request, string $username, string $promptSlug, int $team): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $teamModel = Team::findOrFail($team);
        $prompt->teams()->detach($teamModel->id);

        // If no teams left, set visibility back to private
        if ($prompt->teams()->count() === 0) {
            $prompt->update(['visibility' => 'private']);
        }

        return $this->success(['message' => 'Prompt unshared from team.']);
    }

    public function pin(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $validated = $request->validate([
            'version_id' => 'nullable|integer',
        ]);

        $versionId = $validated['version_id'] ?? null;

        if ($versionId) {
            $version = $prompt->versions()->where('id', $versionId)->first();
            if (! $version) {
                return $this->error('Version not found.', 404);
            }
        }

        $prompt->update(['pinned_version_id' => $versionId]);

        return $this->success([
            'pinned_version_id' => $prompt->pinned_version_id,
            'message' => $versionId ? "Pinned to version #{$version->version_number}." : 'Unpinned — using latest.',
        ]);
    }

    public function run(Request $request, string $username, string $promptSlug, TemplateEngine $templateEngine, LlmDispatchService $dispatchService): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);

        $validated = $request->validate([
            'version_number' => 'nullable|integer',
            'provider_ids' => 'required|array|min:1',
            'provider_ids.*' => 'integer',
            'variables' => 'nullable|array',
        ]);

        // Resolve version
        $version = null;
        if (! empty($validated['version_number'])) {
            $version = $prompt->versions()->where('version_number', $validated['version_number'])->first();
            if (! $version) {
                return $this->error('Version not found.', 404);
            }
        }
        $version = $version ?? $prompt->activeVersion;

        if (! $version) {
            return $this->error('No version found. Save a version first.', 404);
        }

        // Render template
        $variables = $validated['variables'] ?? [];
        $renderResult = $templateEngine->render(
            $version->content,
            $variables,
            $version->variable_metadata,
            $request->user(),
        );
        $renderedContent = $renderResult['rendered'];

        // Dispatch to each provider
        $results = [];
        foreach ($validated['provider_ids'] as $providerId) {
            $provider = LlmProvider::where('id', $providerId)->where('is_active', true)->first();
            if (! $provider) {
                continue;
            }

            $llmResult = $dispatchService->dispatch($provider, $renderedContent);

            $results[] = Result::create([
                'prompt_id' => $prompt->id,
                'prompt_version_id' => $version->id,
                'source' => 'api',
                'provider_name' => $provider->name,
                'model_name' => $llmResult->modelUsed,
                'llm_provider_id' => $provider->id,
                'rendered_content' => $renderedContent,
                'variables_used' => ! empty($variables) ? $variables : null,
                'response_text' => $llmResult->success ? $llmResult->text : null,
                'input_tokens' => $llmResult->inputTokens,
                'output_tokens' => $llmResult->outputTokens,
                'duration_ms' => $llmResult->durationMs,
                'status' => $llmResult->success ? 'success' : 'error',
                'error_message' => $llmResult->error,
                'created_by' => $request->user()->id,
            ]);
        }

        return $this->success($results, 201);
    }
}
