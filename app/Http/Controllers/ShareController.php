<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\User;
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
        $collection->items->load('item');

        // Resolve includes as the collection owner (TPL-01 — was null user
        // → global slug fallback exfiltrating cross-tenant private fragments).
        $owner = $collection->creator()->first();

        // Inner relations on morph targets are lazy-loaded by the Blade
        // partial; the previous eager-load pattern via pluck()->load() was
        // a latent N+1 mitigation that didn't actually run (pluck returns
        // a Support\Collection, no ->load method).

        $maxDepth = config('urge.unlimited_collection_depth')
            ? 100
            : config('urge.max_collection_depth', 5);

        $visited = [];
        $renderedItems = $this->renderCollectionItems(
            $collection,
            $templateEngine,
            $maxDepth,
            $owner,
            $visited,
        );

        return view('public.collection-story', [
            'collection'    => $collection,
            'renderedItems' => $renderedItems,
            'shareLink'     => $link,
        ]);
    }

    /**
     * @param array<int, bool> $visited Collection IDs already entered on
     *  the current render path; guards against DAG cycles introduced
     *  outside the CollectionNestingService validator (TPL-07).
     */
    private function renderCollectionItems(
        Collection $collection,
        TemplateEngine $templateEngine,
        int $remainingDepth,
        ?User $owner,
        array &$visited,
    ): \Illuminate\Support\Collection {
        if (isset($visited[$collection->id])) {
            return collect();
        }
        $visited[$collection->id] = true;

        $collection->load(['items' => fn ($q) => $q->orderBy('sort_order')]);
        $collection->items->load('item');

        return $collection->items->map(function ($item) use ($templateEngine, $remainingDepth, $owner, &$visited) {
            $data = [
                'item'     => $item,
                'resolved' => $item->item,
                'rendered' => null,
                'children' => null,
            ];

            if ($item->item_type === 'prompt_version' && $item->item) {
                // Public page renders with the owner's visibility for includes;
                // strict=false so unfilled vars stay as literal {{var}} on the
                // page rather than 500ing.
                $result = $templateEngine->render($item->item->content, [], null, $owner);
                $data['rendered'] = $result['rendered'];
            } elseif ($item->item_type === 'collection' && $item->item && $remainingDepth > 1) {
                $data['children'] = $this->renderCollectionItems(
                    $item->item,
                    $templateEngine,
                    $remainingDepth - 1,
                    $owner,
                    $visited,
                );
            }

            return (object) $data;
        });
    }
}
