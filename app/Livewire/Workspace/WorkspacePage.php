<?php

namespace App\Livewire\Workspace;

use App\Models\Prompt;
use App\Models\PromptBranch;
use App\Models\PromptVersion;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class WorkspacePage extends Component
{
    public Prompt $prompt;
    public ?PromptVersion $currentVersion = null;
    public ?PromptBranch $currentBranch = null;
    public bool $isOwner = false;

    public function mount(string $username, string $slug)
    {
        $owner = User::where('slug', $username)->firstOrFail();

        $prompt = Prompt::where('created_by', $owner->id)
            ->where('slug', $slug)
            ->firstOrFail();

        // Verify current user can see this prompt
        $canSee = Prompt::visibleTo(auth()->user())
            ->where('id', $prompt->id)
            ->exists();

        if (! $canSee) {
            abort(404);
        }

        $this->prompt = $prompt->load(['versions', 'category', 'creator', 'defaultBranch']);
        $this->currentVersion = $prompt->active_version;
        $this->currentBranch = $prompt->defaultBranch;
        $this->isOwner = $prompt->created_by === auth()->id();
    }

    #[On('version-selected')]
    public function selectVersion(int $versionId)
    {
        $this->currentVersion = PromptVersion::findOrFail($versionId);
    }

    #[On('version-created')]
    public function onVersionCreated(int $versionId)
    {
        $this->prompt->refresh();
        $this->prompt->load('versions');
        $this->currentVersion = PromptVersion::findOrFail($versionId);
    }

    #[On('branch-switched')]
    public function onBranchSwitched(int $branchId)
    {
        $this->currentBranch = PromptBranch::findOrFail($branchId);
        if ($this->currentBranch->headVersion) {
            $this->currentVersion = $this->currentBranch->headVersion;
        }
    }

    #[On('result-saved')]
    public function onResultSaved()
    {
        // ResultsPanel listens to this event too and refreshes
    }

    public function getTitle(): string
    {
        return $this->prompt->name;
    }

    public function render()
    {
        return view('livewire.workspace.workspace-page')
            ->title($this->prompt->name);
    }
}
