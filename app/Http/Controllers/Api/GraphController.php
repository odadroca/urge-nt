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
                'avg_evaluation_score' => \App\Models\ResultEvaluation::whereIn(
                    'result_id', $prompt->results()->pluck('results.id')
                )->whereRaw('evaluation_version = (SELECT MAX(re2.evaluation_version) FROM result_evaluations re2 WHERE re2.result_id = result_evaluations.result_id)')
                ->avg('score'),
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
            'positions' => ['present', 'array'],
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

    public function appendInclude(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $validated = $request->validate([
            'fragment_slug' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prompt = Prompt::visibleTo($user)->whereHas('creator', fn ($q) => $q->where('slug', $username))
            ->where('slug', $promptSlug)->first();

        if (! $prompt) {
            return $this->error('Prompt not found', 404);
        }

        // Verify fragment exists
        $fragment = Prompt::where('slug', $validated['fragment_slug'])->where('type', 'fragment')->first();
        if (! $fragment) {
            return $this->error('Fragment not found', 404);
        }

        $activeVersion = $prompt->activeVersion;
        if (! $activeVersion) {
            return $this->error('Prompt has no active version', 400);
        }

        $newContent = $activeVersion->content . "\n{{>" . $validated['fragment_slug'] . "}}";

        $version = $this->versioningService->createVersion($prompt, [
            'content' => $newContent,
            'commit_message' => "Added include: {$validated['fragment_slug']}",
            'variable_metadata' => $activeVersion->variable_metadata,
        ], $user);

        return $this->success([
            'version_number' => $version->version_number,
            'content' => $version->content,
            'includes' => $version->includes,
        ]);
    }

    public function removeInclude(Request $request, string $username, string $promptSlug): JsonResponse
    {
        $validated = $request->validate([
            'fragment_slug' => ['required', 'string'],
        ]);

        $user = $request->user();
        $prompt = Prompt::visibleTo($user)->whereHas('creator', fn ($q) => $q->where('slug', $username))
            ->where('slug', $promptSlug)->first();

        if (! $prompt) {
            return $this->error('Prompt not found', 404);
        }

        $activeVersion = $prompt->activeVersion;
        if (! $activeVersion) {
            return $this->error('Prompt has no active version', 400);
        }

        $slug = preg_quote($validated['fragment_slug'], '/');
        $newContent = preg_replace("/\n?\{\{>{$slug}\}\}/", '', $activeVersion->content);
        $newContent = trim($newContent);

        if ($newContent === trim($activeVersion->content)) {
            return $this->error('Include not found in content', 400);
        }

        $version = $this->versioningService->createVersion($prompt, [
            'content' => $newContent,
            'commit_message' => "Removed include: {$validated['fragment_slug']}",
            'variable_metadata' => $activeVersion->variable_metadata,
        ], $user);

        return $this->success([
            'version_number' => $version->version_number,
            'content' => $version->content,
            'includes' => $version->includes,
        ]);
    }

    public function edges(Request $request): JsonResponse
    {
        $user = $request->user();

        // Composition edges: derived from {{>slug}} includes in prompt content
        $prompts = Prompt::visibleTo($user)->get();
        $compositionEdges = [];

        foreach ($prompts as $prompt) {
            $activeVersion = $prompt->activeVersion;
            if (! $activeVersion) {
                continue;
            }

            $includes = $this->templateEngine->extractIncludes($activeVersion->content);
            foreach ($includes as $includeSlug) {
                $compositionEdges[] = [
                    'source_id' => $prompt->id,
                    'source_slug' => $prompt->slug,
                    'source_type' => $prompt->type,
                    'target_slug' => $includeSlug,
                    'type' => 'includes',
                ];
            }
        }

        // Collection edges: from CollectionItem relationships
        $collections = Collection::where('created_by', $user->id)->with('items')->get();
        $collectionEdges = [];

        foreach ($collections as $collection) {
            foreach ($collection->items as $item) {
                $collectionEdges[] = [
                    'collection_id' => $collection->id,
                    'collection_slug' => $collection->slug,
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                ];
            }
        }

        return response()->json([
            'data' => [
                'composition' => $compositionEdges,
                'collection' => $collectionEdges,
            ],
        ]);
    }
}
