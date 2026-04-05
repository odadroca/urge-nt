<?php

namespace App\Services;

use App\Models\CollectionItem;

class CollectionNestingService
{
    /**
     * Validate that nesting childId inside parentId is allowed.
     *
     * @throws \RuntimeException
     */
    public function validateNesting(int $parentId, int $childId): void
    {
        if ($parentId === $childId) {
            throw new \RuntimeException('Cannot nest a collection inside itself.');
        }

        // Circular reference: child must not appear in parent's ancestor chain
        $ancestors = $this->getAncestorIds($parentId);
        if (in_array($childId, $ancestors, true)) {
            throw new \RuntimeException('Circular reference detected.');
        }

        // Depth check
        if (!config('urge.unlimited_collection_depth')) {
            $maxDepth = config('urge.max_collection_depth', 5);
            $depthAbove = $this->getDepthFromRoot($parentId);
            $depthBelow = $this->getMaxChildDepth($childId);
            $totalDepth = $depthAbove + 1 + $depthBelow;

            if ($totalDepth > $maxDepth) {
                throw new \RuntimeException('Maximum nesting depth exceeded.');
            }
        }
    }

    /**
     * Get all ancestor collection IDs by walking upward through the DAG.
     */
    public function getAncestorIds(int $collectionId): array
    {
        $ancestors = [];
        $queue = [$collectionId];

        while (!empty($queue)) {
            $current = array_shift($queue);

            $parentIds = CollectionItem::where('item_type', 'collection')
                ->where('item_id', $current)
                ->pluck('collection_id')
                ->all();

            foreach ($parentIds as $parentId) {
                if (!in_array($parentId, $ancestors, true)) {
                    $ancestors[] = $parentId;
                    $queue[] = $parentId;
                }
            }
        }

        return $ancestors;
    }

    /**
     * Get the maximum depth below this collection (0 if no nested collections).
     */
    public function getMaxChildDepth(int $collectionId): int
    {
        $childCollectionIds = CollectionItem::where('collection_id', $collectionId)
            ->where('item_type', 'collection')
            ->pluck('item_id')
            ->all();

        if (empty($childCollectionIds)) {
            return 0;
        }

        $maxDepth = 0;
        foreach ($childCollectionIds as $childId) {
            $depth = 1 + $this->getMaxChildDepth($childId);
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    /**
     * Get how deep this collection is from any root (max across all parent paths in the DAG).
     */
    public function getDepthFromRoot(int $collectionId): int
    {
        $parentIds = CollectionItem::where('item_type', 'collection')
            ->where('item_id', $collectionId)
            ->pluck('collection_id')
            ->all();

        if (empty($parentIds)) {
            return 0;
        }

        $maxDepth = 0;
        foreach ($parentIds as $parentId) {
            $depth = 1 + $this->getDepthFromRoot($parentId);
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }
}
