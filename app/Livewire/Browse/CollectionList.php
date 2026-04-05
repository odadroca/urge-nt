<?php

namespace App\Livewire\Browse;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\CollectionShareLink;
use App\Services\CollectionNestingService;
use App\Services\ShareLinkService;
use Livewire\Component;

class CollectionList extends Component
{
    public string $newTitle = '';
    public string $newDescription = '';
    public bool $showCreateForm = false;
    public ?int $expandedId = null;
    public ?int $editingId = null;
    public string $editTitle = '';
    public string $editDescription = '';

    // Share modal
    public bool $showShareModal = false;
    public ?int $sharingCollectionId = null;
    public string $shareLabel = '';
    public string $shareExpiry = 'never';
    public ?string $justCreatedShareUrl = null;

    public function createCollection()
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newDescription' => 'nullable|string',
        ]);

        Collection::create([
            'title' => $this->newTitle,
            'description' => $this->newDescription ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['newTitle', 'newDescription', 'showCreateForm']);
    }

    public function toggleExpand(int $id)
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function startEditing(int $id)
    {
        $collection = Collection::findOrFail($id);
        $this->editingId = $id;
        $this->editTitle = $collection->title;
        $this->editDescription = $collection->description ?? '';
    }

    public function saveEdit()
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
        ]);

        $collection = Collection::findOrFail($this->editingId);
        $collection->update([
            'title' => $this->editTitle,
            'description' => $this->editDescription ?: null,
        ]);

        $this->reset(['editingId', 'editTitle', 'editDescription']);
    }

    public function deleteCollection(int $id)
    {
        Collection::findOrFail($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
    }

    public function addCollectionToCollection(int $childId, int $parentId, CollectionNestingService $nestingService)
    {
        try {
            $nestingService->validateNesting($parentId, $childId);
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
            return;
        }

        CollectionItem::firstOrCreate([
            'collection_id' => $parentId,
            'item_type' => 'collection',
            'item_id' => $childId,
        ], [
            'sort_order' => CollectionItem::where('collection_id', $parentId)->max('sort_order') + 1,
        ]);

        $this->dispatch('notify', message: 'Collection added.', type: 'success');
    }

    public function removeItem(int $itemId)
    {
        CollectionItem::findOrFail($itemId)->delete();
    }

    public function reorderItems(array $order)
    {
        foreach ($order as $index => $itemId) {
            CollectionItem::where('id', $itemId)->update(['sort_order' => $index]);
        }
    }

    public function openShareModal(int $collectionId)
    {
        $this->sharingCollectionId = $collectionId;
        $this->showShareModal = true;
        $this->shareLabel = '';
        $this->shareExpiry = 'never';
        $this->justCreatedShareUrl = null;
    }

    public function closeShareModal()
    {
        $this->showShareModal = false;
        $this->sharingCollectionId = null;
        $this->justCreatedShareUrl = null;
    }

    public function generateShareLink(ShareLinkService $service)
    {
        $collection = Collection::findOrFail($this->sharingCollectionId);
        $expiry = $this->shareExpiry === 'never' ? null : $this->shareExpiry;

        $link = $service->createLink(
            $collection,
            auth()->user(),
            $this->shareLabel ?: null,
            $expiry,
        );

        $this->justCreatedShareUrl = $link->getUrl();
        $this->shareLabel = '';
        $this->shareExpiry = 'never';
        $this->dispatch('notify', message: 'Share link created!', type: 'success');
    }

    public function revokeShareLink(int $linkId)
    {
        CollectionShareLink::where('id', $linkId)
            ->whereHas('collection.creator', fn ($q) => $q->where('users.id', auth()->id()))
            ->delete();

        $this->dispatch('notify', message: 'Share link revoked.', type: 'success');
    }

    public function render()
    {
        $collections = Collection::withCount(['items', 'shareLinks'])
            ->orderBy('title')
            ->get();

        $expandedCollection = null;
        $groupedExpandedItems = collect();
        if ($this->expandedId) {
            $expandedCollection = Collection::with(['items' => function ($q) {
                $q->orderBy('sort_order')->with(['item' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\PromptVersion::class => ['prompt'],
                        \App\Models\Result::class => ['prompt', 'promptVersion', 'pipelineTemplate'],
                        \App\Models\Collection::class => ['items'],
                    ]);
                }]);
            }])->find($this->expandedId);

            if ($expandedCollection) {
                // Group adjacent result items by pipeline_run_id
                $runBuffer = [];
                foreach ($expandedCollection->items as $item) {
                    if ($item->item_type === 'result' && $item->item?->pipeline_run_id) {
                        $rid = $item->item->pipeline_run_id;
                        if (!isset($runBuffer[$rid])) {
                            $runBuffer[$rid] = [
                                'template' => $item->item->pipelineTemplate,
                                'items' => collect(),
                                'run_id' => $rid,
                            ];
                        }
                        $runBuffer[$rid]['items']->push($item);
                    } else {
                        foreach ($runBuffer as $buf) {
                            $groupedExpandedItems->push(['type' => 'pipeline_group', 'data' => $buf]);
                        }
                        $runBuffer = [];
                        $groupedExpandedItems->push(['type' => 'single', 'data' => $item]);
                    }
                }
                foreach ($runBuffer as $buf) {
                    $groupedExpandedItems->push(['type' => 'pipeline_group', 'data' => $buf]);
                }
            }
        }

        $shareLinks = $this->sharingCollectionId
            ? CollectionShareLink::where('collection_id', $this->sharingCollectionId)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('livewire.browse.collection-list', [
            'collections' => $collections,
            'expandedCollection' => $expandedCollection,
            'groupedExpandedItems' => $groupedExpandedItems,
            'shareLinks' => $shareLinks,
        ]);
    }
}
