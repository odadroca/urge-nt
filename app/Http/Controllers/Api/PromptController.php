<?php

namespace App\Http\Controllers\Api;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
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
            'name'        => 'required|string|max:255',
            'type'        => 'in:prompt,fragment',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'tags'        => 'nullable|array',
            'tags.*'      => 'string|max:50',
            'visibility'  => 'nullable|in:private,shared',
        ]);

        $prompt = Prompt::create([
            'name'        => $validated['name'],
            'type'        => $validated['type'] ?? 'prompt',
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'tags'        => $validated['tags'] ?? null,
            'visibility'  => $validated['visibility'] ?? 'private',
            'created_by'  => $request->user()->id,
        ]);

        return $this->success($prompt->load('category'), 201);
    }

    public function show(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizePromptAccess($prompt, $request);

        $prompt->load(['category', 'activeVersion', 'creator']);
        $prompt->loadCount(['versions', 'results']);

        return $this->success($prompt);
    }

    public function update(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizePromptAccess($prompt, $request);
        $this->authorizeOwnership($prompt, $request);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'type'        => 'in:prompt,fragment',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'tags'        => 'nullable|array',
            'tags.*'      => 'string|max:50',
        ]);

        $prompt->update($validated);

        return $this->success($prompt->fresh()->load('category'));
    }

    public function destroy(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizePromptAccess($prompt, $request);
        $this->authorizeOwnership($prompt, $request);

        $prompt->delete();

        return $this->success(['message' => 'Prompt deleted.']);
    }

    public function legacyRedirect(Request $request, string $slug): JsonResponse
    {
        // Prefer current user's prompt, then fall back to oldest
        $user = $request->user();
        $prompt = null;

        if ($user) {
            $prompt = Prompt::where('slug', $slug)
                ->where('created_by', $user->id)
                ->first();
        }

        if (!$prompt) {
            $prompt = Prompt::where('slug', $slug)
                ->orderBy('created_at', 'asc')
                ->first();
        }

        if (!$prompt) {
            abort(404);
        }

        $prompt->load('creator');
        $ownerSlug = $prompt->creator->slug;

        return response()->json([
            'redirect' => "/api/v1/prompts/{$ownerSlug}/{$prompt->slug}",
        ], 301);
    }

    public function share(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $prompt = $this->resolvePrompt($username, $promptSlug, $request);
        $this->authorizeOwnership($prompt, $request);

        $validated = $request->validate([
            'team_id'   => 'required_without:team_slug|nullable|exists:teams,id',
            'team_slug' => 'required_without:team_id|nullable|string',
        ]);

        if (!empty($validated['team_id'])) {
            $team = Team::findOrFail($validated['team_id']);
        } else {
            $team = Team::where('slug', $validated['team_slug'])->firstOrFail();
        }

        // Attach team if not already attached
        if (!$prompt->teams()->where('teams.id', $team->id)->exists()) {
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

    private function authorizePromptAccess(Prompt $prompt, Request $request): void
    {
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey && $apiKey->prompts()->exists()) {
            if (!$apiKey->prompts()->where('prompts.id', $prompt->id)->exists()) {
                abort(403, 'API key does not have access to this prompt.');
            }
        }
    }
}
