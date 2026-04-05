<?php

namespace App\Livewire\Workspace;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Services\AiAssistantService;
use App\Services\VersioningService;
use Livewire\Attributes\On;
use Livewire\Component;

class VersionSidebar extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public ?int $currentBranchId = null;
    public bool $showCreateForm = false;
    public string $commitMessage = '';
    public ?string $aiSummary = null;
    public string $newBranchName = '';
    public bool $showBranchCreate = false;

    public function mount(Prompt $prompt, ?PromptVersion $currentVersion = null)
    {
        $this->prompt = $prompt;
        $this->currentVersionId = $currentVersion?->id;
        $this->currentBranchId = $currentVersion?->branch_id ?? $prompt->default_branch_id;
    }

    public function selectVersion(int $versionId)
    {
        $this->currentVersionId = $versionId;
        $this->dispatch('version-selected', versionId: $versionId);
    }

    public function switchBranch(int $branchId)
    {
        $this->currentBranchId = $branchId;
        $branch = PromptBranch::findOrFail($branchId);
        if ($branch->head_version_id) {
            $this->selectVersion($branch->head_version_id);
        }
        $this->dispatch('branch-switched', branchId: $branchId);
    }

    public function createBranch(VersioningService $versioningService)
    {
        $this->validate(['newBranchName' => 'required|string|max:100']);

        $currentVersion = $this->currentVersionId ? PromptVersion::find($this->currentVersionId) : null;
        $branch = $versioningService->createBranch(
            $this->prompt, $this->newBranchName, auth()->user(), $currentVersion
        );
        $this->currentBranchId = $branch->id;
        $this->newBranchName = '';
        $this->showBranchCreate = false;
        if ($branch->head_version_id) {
            $this->currentVersionId = $branch->head_version_id;
        }
        $this->dispatch('branch-switched', branchId: $branch->id);
        $this->dispatch('notify', message: "Branch '{$branch->name}' created", type: 'success');
    }

    public function deleteBranch(int $branchId, VersioningService $versioningService)
    {
        $branch = PromptBranch::findOrFail($branchId);
        if ($branch->is_default) {
            $this->dispatch('notify', message: 'Cannot delete the default branch', type: 'error');
            return;
        }

        $versioningService->deleteBranch($branch);

        // Switch to default branch
        $this->prompt->refresh();
        $defaultBranch = $this->prompt->defaultBranch;
        if ($defaultBranch) {
            $this->switchBranch($defaultBranch->id);
        }
        $this->dispatch('notify', message: "Branch deleted", type: 'success');
    }

    public function setDefaultBranch(int $branchId, VersioningService $versioningService)
    {
        $branch = PromptBranch::findOrFail($branchId);
        $versioningService->setDefaultBranch($this->prompt, $branch);
        $this->prompt->refresh();
        $this->dispatch('notify', message: "'{$branch->name}' is now the default branch", type: 'success');
    }

    #[On('version-created')]
    public function onVersionCreated(int $versionId)
    {
        $this->prompt->refresh();
        $this->prompt->load('versions');
        $this->currentVersionId = $versionId;
        $this->showCreateForm = false;
        $this->commitMessage = '';
    }

    public function aiSummarizeVersionDiff(int $versionIdA, int $versionIdB, int $providerId): void
    {
        $a = PromptVersion::findOrFail($versionIdA);
        $b = PromptVersion::findOrFail($versionIdB);
        $provider = LlmProvider::findOrFail($providerId);

        $service = app(AiAssistantService::class);
        $result = $service->summarizeDifferences($a->content, $b->content, $provider);

        $this->aiSummary = $result->success ? $result->text : "Error: {$result->error}";
        $this->dispatch('notify',
            message: $result->success ? 'AI summary ready' : 'AI summary failed',
            type: $result->success ? 'success' : 'error'
        );
    }

    public function addVersionToCollection(int $versionId, int $collectionId)
    {
        CollectionItem::firstOrCreate([
            'collection_id' => $collectionId,
            'item_type' => 'prompt_version',
            'item_id' => $versionId,
        ], [
            'sort_order' => CollectionItem::where('collection_id', $collectionId)->max('sort_order') + 1,
        ]);
    }

    public function render()
    {
        $versionsQuery = $this->prompt->versions();
        if ($this->currentBranchId) {
            $versionsQuery = PromptVersion::where('branch_id', $this->currentBranchId)
                ->orderByDesc('branch_version_number');
        }

        return view('livewire.workspace.version-sidebar', [
            'versions' => $versionsQuery->get(),
            'branches' => $this->prompt->branches()->withCount('versions')->get(),
            'collections' => Collection::orderBy('title')->get(['id', 'title']),
        ]);
    }
}
