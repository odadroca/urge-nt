<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Services\ShareLinkService;
use App\Services\TemplateEngine;

class ShareController extends Controller
{
    public function show(string $token, ShareLinkService $shareService, TemplateEngine $templateEngine)
    {
        $link = $shareService->findByToken($token);

        if (!$link) {
            abort(404);
        }

        $link->recordAccess();

        $collection = $link->collection->load(['items' => fn ($q) => $q->orderBy('sort_order')]);

        // Eager-load the polymorphic items
        $collection->items->load('item');

        // Load nested relationships for prompt versions
        $promptVersionItems = $collection->items
            ->where('item_type', 'prompt_version')
            ->pluck('item')
            ->filter();
        if ($promptVersionItems->isNotEmpty()) {
            $promptVersionItems->load('prompt');
        }

        // Load nested relationships for results
        $resultItems = $collection->items
            ->where('item_type', 'result')
            ->pluck('item')
            ->filter();
        if ($resultItems->isNotEmpty()) {
            $resultItems->load(['prompt', 'promptVersion']);
        }

        $maxDepth = config('urge.unlimited_collection_depth')
            ? 100
            : config('urge.max_collection_depth', 5);

        // Render items recursively (handles nested collections)
        $renderedItems = $this->renderCollectionItems($collection, $templateEngine, $maxDepth);

        return view('public.collection-story', [
            'collection'    => $collection,
            'renderedItems' => $renderedItems,
            'shareLink'     => $link,
        ]);
    }

    private function renderCollectionItems(Collection $collection, TemplateEngine $templateEngine, int $remainingDepth): \Illuminate\Support\Collection
    {
        $collection->load(['items' => fn ($q) => $q->orderBy('sort_order')]);
        $collection->items->load('item');

        // Load nested relationships for prompt versions
        $pvItems = $collection->items->where('item_type', 'prompt_version')->pluck('item')->filter();
        if ($pvItems->isNotEmpty()) {
            $pvItems->load('prompt');
        }

        // Load nested relationships for results
        $rItems = $collection->items->where('item_type', 'result')->pluck('item')->filter();
        if ($rItems->isNotEmpty()) {
            $rItems->load(['prompt', 'promptVersion']);
        }

        return $collection->items->map(function ($item) use ($templateEngine, $remainingDepth) {
            $data = [
                'item'     => $item,
                'resolved' => $item->item,
                'rendered' => null,
                'children' => null,
            ];

            if ($item->item_type === 'prompt_version' && $item->item) {
                $result = $templateEngine->render($item->item->content, []);
                $data['rendered'] = $result['rendered'];
            } elseif ($item->item_type === 'collection' && $item->item && $remainingDepth > 1) {
                $data['children'] = $this->renderCollectionItems($item->item, $templateEngine, $remainingDepth - 1);
            }

            return (object) $data;
        });
    }
}
