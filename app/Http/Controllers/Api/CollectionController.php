<?php

namespace App\Http\Controllers\Api;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Services\CollectionNestingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Collection::where('created_by', $request->user()->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('updated_at');

        return $this->paginated($query, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection = Collection::create([
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        return $this->success($collection, 201);
    }

    public function show(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $maxDepth = config('urge.unlimited_collection_depth')
            ? 100
            : config('urge.max_collection_depth', 5);

        $data = $this->buildCollectionData($collection, $maxDepth);

        return $this->success($data);
    }

    private function buildCollectionData(Collection $collection, int $remainingDepth): array
    {
        $collection->load(['items' => function ($query) {
            $query->orderBy('sort_order')->with(['item' => function ($morphTo) {
                $morphTo->morphWith([
                    PromptVersion::class => ['prompt.creator'],
                    Result::class => ['prompt.creator', 'promptVersion'],
                ]);
            }]);
        }]);

        return [
            'id'          => $collection->id,
            'title'       => $collection->title,
            'slug'        => $collection->slug,
            'description' => $collection->description,
            'created_at'  => $collection->created_at,
            'updated_at'  => $collection->updated_at,
            'items'       => $collection->items->map(function ($item) use ($remainingDepth) {
                $resolved = $item->item;
                $entry = [
                    'id'         => $item->id,
                    'sort_order' => $item->sort_order,
                    'item_type'  => $item->item_type,
                    'item_id'    => $item->item_id,
                    'notes'      => $item->notes,
                ];

                if ($item->item_type === 'prompt_version' && $resolved) {
                    $entry['prompt_name']    = $resolved->prompt->name ?? null;
                    $entry['prompt_slug']    = $resolved->prompt->slug ?? null;
                    $entry['prompt_owner']   = $resolved->prompt->creator->slug ?? null;
                    $entry['version_number'] = $resolved->version_number;
                    $entry['content']        = $resolved->content;
                    $entry['variables']      = $resolved->variables;
                    $entry['commit_message'] = $resolved->commit_message;
                } elseif ($item->item_type === 'result' && $resolved) {
                    $entry['prompt_name']    = $resolved->prompt->name ?? null;
                    $entry['prompt_slug']    = $resolved->prompt->slug ?? null;
                    $entry['prompt_owner']   = $resolved->prompt->creator->slug ?? null;
                    $entry['version_number'] = $resolved->promptVersion->version_number ?? null;
                    $entry['provider_name']  = $resolved->provider_name;
                    $entry['model_name']     = $resolved->model_name;
                    $entry['response_text']  = $resolved->response_text;
                    $entry['rating']         = $resolved->rating;
                    $entry['starred']        = $resolved->starred;
                    $entry['input_tokens']   = $resolved->input_tokens;
                    $entry['output_tokens']  = $resolved->output_tokens;
                    $entry['duration_ms']    = $resolved->duration_ms;
                } elseif ($item->item_type === 'collection' && $resolved) {
                    $entry['title'] = $resolved->title;
                    $entry['slug']  = $resolved->slug;
                    if ($remainingDepth > 1) {
                        $entry['children'] = $this->buildCollectionData($resolved, $remainingDepth - 1)['items'];
                    } else {
                        $entry['children_count'] = $resolved->items()->count();
                    }
                }

                return $entry;
            })->values(),
        ];
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection->update($validated);

        return $this->success($collection->fresh());
    }

    public function destroy(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $collection->delete();

        return $this->success(['message' => 'Collection deleted.']);
    }

    public function removeItem(Request $request, Collection $collection, CollectionItem $item): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        if ($item->collection_id !== $collection->id) {
            return $this->error('Item does not belong to this collection.', 404);
        }

        $item->delete();

        return $this->success(['message' => 'Item removed from collection.']);
    }

    public function addItem(Request $request, Collection $collection): JsonResponse
    {
        if ($collection->created_by !== $request->user()->id) {
            return $this->error('Collection not found.', 404);
        }

        $validated = $request->validate([
            'item_type' => 'required|string|in:prompt_version,result,collection',
            'item_id'   => 'required|integer',
            'notes'     => 'nullable|string',
        ]);

        // Verify the referenced item exists
        $exists = match ($validated['item_type']) {
            'prompt_version' => PromptVersion::where('id', $validated['item_id'])->exists(),
            'result'         => Result::where('id', $validated['item_id'])->exists(),
            'collection'     => Collection::where('id', $validated['item_id'])->exists(),
        };

        if (!$exists) {
            return $this->error('Referenced item not found.', 404);
        }

        // Validate nesting constraints for collection items
        if ($validated['item_type'] === 'collection') {
            try {
                app(CollectionNestingService::class)->validateNesting(
                    $collection->id,
                    $validated['item_id']
                );
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), 422);
            }
        }

        // Check for duplicates
        $duplicate = CollectionItem::where('collection_id', $collection->id)
            ->where('item_type', $validated['item_type'])
            ->where('item_id', $validated['item_id'])
            ->exists();

        if ($duplicate) {
            return $this->error('Item already exists in this collection.', 409);
        }

        $maxSort = $collection->items()->max('sort_order') ?? -1;

        $item = CollectionItem::create([
            'collection_id' => $collection->id,
            'item_type'     => $validated['item_type'],
            'item_id'       => $validated['item_id'],
            'sort_order'    => $maxSort + 1,
            'notes'         => $validated['notes'] ?? null,
        ]);

        return $this->success($item, 201);
    }
}
