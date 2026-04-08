<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use App\Models\GraphPosition;
use App\Models\Prompt;
use App\Services\TemplateEngine;
use App\Services\VersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphController extends ApiController
{
    private const MAX_NODES = 500;

    public function __construct(
        private TemplateEngine $templateEngine,
        private VersioningService $versioningService,
    ) {}

    public function nodes(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load visible prompts (includes both prompts and fragments)
        $promptsQuery = Prompt::visibleTo($user)
            ->with(['creator', 'category', 'pinnedVersion', 'defaultBranch.headVersion', 'latestVersion'])
            ->withCount(['versions', 'results'])
            ->orderByDesc('updated_at');

        $totalPrompts = (clone $promptsQuery)->count();

        // Load collections created by the user
        $collectionsQuery = Collection::where('created_by', $user->id)
            ->withCount('items')
            ->orderByDesc('updated_at');

        $totalCollections = (clone $collectionsQuery)->count();
        $totalCount = $totalPrompts + $totalCollections;
        $truncated = $totalCount > self::MAX_NODES;

        // Determine how many of each to fetch
        $promptLimit = min($totalPrompts, self::MAX_NODES);
        $collectionLimit = min($totalCollections, self::MAX_NODES - $promptLimit);

        $prompts = $promptsQuery->limit($promptLimit)->get();
        $collections = $collectionsQuery->limit($collectionLimit)->get();

        // Load positions for current user
        $positionKeys = [];

        foreach ($prompts as $prompt) {
            $nodeType = $prompt->isFragment() ? 'fragment' : 'prompt';
            $positionKeys[] = ['node_type' => $nodeType, 'node_id' => $prompt->id];
        }

        foreach ($collections as $collection) {
            $positionKeys[] = ['node_type' => 'collection', 'node_id' => $collection->id];
        }

        $positions = collect();
        if (!empty($positionKeys)) {
            $positions = GraphPosition::where('user_id', $user->id)
                ->where(function ($query) use ($positionKeys) {
                    foreach ($positionKeys as $key) {
                        $query->orWhere(function ($q) use ($key) {
                            $q->where('node_type', $key['node_type'])
                              ->where('node_id', $key['node_id']);
                        });
                    }
                })
                ->get()
                ->keyBy(fn ($pos) => "{$pos->node_type}:{$pos->node_id}");
        }

        // Map prompts to response format
        $promptsData = $prompts->map(function (Prompt $prompt) use ($positions) {
            $nodeType = $prompt->isFragment() ? 'fragment' : 'prompt';
            $positionKey = "{$nodeType}:{$prompt->id}";
            $position = $positions->get($positionKey);

            $activeVersion = $prompt->active_version;

            $data = [
                'id' => $prompt->id,
                'slug' => $prompt->slug,
                'name' => $prompt->name,
                'type' => $prompt->type,
                'description' => $prompt->description,
                'tags' => $prompt->tags ?? [],
                'category' => $prompt->category ? [
                    'name' => $prompt->category->name,
                    'color' => $prompt->category->color_hex,
                ] : null,
                'owner' => $prompt->creator?->slug,
                'active_version' => $activeVersion ? [
                    'version_number' => $activeVersion->version_number,
                    'content' => $activeVersion->content,
                    'variables' => $activeVersion->variables ?? [],
                    'includes' => $activeVersion->includes ?? [],
                ] : null,
                'versions_count' => $prompt->versions_count,
                'results_count' => $prompt->results_count,
                'position' => $position ? [
                    'x' => $position->x,
                    'y' => $position->y,
                ] : null,
            ];

            return $data;
        })->values();

        // Map collections to response format
        $collectionsData = $collections->map(function (Collection $collection) use ($positions) {
            $positionKey = "collection:{$collection->id}";
            $position = $positions->get($positionKey);

            return [
                'id' => $collection->id,
                'slug' => $collection->slug,
                'title' => $collection->title,
                'description' => $collection->description,
                'items_count' => $collection->items_count,
                'position' => $position ? [
                    'x' => $position->x,
                    'y' => $position->y,
                ] : null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'prompts' => $promptsData,
                'collections' => $collectionsData,
            ],
            'meta' => [
                'total_count' => $totalCount,
                'truncated' => $truncated,
            ],
        ]);
    }

    public function positions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.node_type' => ['required', 'string', 'in:prompt,fragment,collection'],
            'positions.*.node_id' => ['required', 'integer', 'min:1'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
        ]);

        $count = 0;
        if (! empty($validated['positions'])) {
            $count = GraphPosition::bulkUpsert($request->user()->id, $validated['positions']);
        }

        return $this->success(['saved' => $count]);
    }
}
