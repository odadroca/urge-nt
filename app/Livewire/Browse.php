<?php

namespace App\Livewire;

use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\Result;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Browse')]
class Browse extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = 'prompts';

    #[Url]
    public ?int $categoryFilter = null;

    #[Url]
    public string $tagFilter = '';

    #[Url]
    public string $browseScope = 'mine';

    public string $newPromptName = '';
    public string $newPromptType = 'prompt';
    public bool $showCreateForm = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTab()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingTagFilter()
    {
        $this->resetPage();
    }

    public function updatingBrowseScope()
    {
        $this->resetPage();
    }

    public function switchScope(string $scope)
    {
        $this->browseScope = $scope;
        $this->resetPage();
    }

    public function createPrompt()
    {
        $this->validate([
            'newPromptName' => 'required|string|max:255',
            'newPromptType' => 'in:prompt,fragment',
        ]);

        $prompt = Prompt::create([
            'name' => $this->newPromptName,
            'type' => $this->newPromptType,
            'created_by' => auth()->id(),
        ]);

        $prompt->load('creator');

        return $this->redirect($prompt->workspaceUrl(), navigate: true);
    }

    public function addPromptToCollection(int $promptId, int $collectionId)
    {
        $prompt = Prompt::findOrFail($promptId);
        $version = $prompt->activeVersion;

        if (!$version) {
            $this->dispatch('notify', message: 'Prompt has no versions yet', type: 'error');
            return;
        }

        CollectionItem::firstOrCreate([
            'collection_id' => $collectionId,
            'item_type' => 'prompt_version',
            'item_id' => $version->id,
        ], [
            'sort_order' => CollectionItem::where('collection_id', $collectionId)->max('sort_order') + 1,
        ]);

        $this->dispatch('notify', message: 'Added to collection', type: 'success');
    }

    public function addPromptsToCollection(array $promptIds, int $collectionId)
    {
        $added = 0;

        foreach ($promptIds as $promptId) {
            $prompt = Prompt::find($promptId);
            if (!$prompt) continue;

            $version = $prompt->activeVersion;
            if (!$version) continue;

            CollectionItem::firstOrCreate([
                'collection_id' => $collectionId,
                'item_type' => 'prompt_version',
                'item_id' => $version->id,
            ], [
                'sort_order' => CollectionItem::where('collection_id', $collectionId)->max('sort_order') + 1,
            ]);

            $added++;
        }

        $this->dispatch('notify', message: "Added {$added} item(s) to collection", type: 'success');
        $this->dispatch('selection-cleared');
    }

    public function render()
    {
        $user = auth()->user();
        $categories = Category::orderBy('name')->get();
        $prompts = null;
        $starredResults = null;

        if ($this->tab === 'prompts' || $this->tab === 'fragments') {
            $query = Prompt::query()->orderByDesc('updated_at');

            // Apply browse scope
            if ($this->browseScope === 'mine') {
                $query->where('created_by', $user->id);
            } elseif (str_starts_with($this->browseScope, 'team:')) {
                $teamSlug = substr($this->browseScope, 5);
                $query->visibleTo($user)
                    ->whereHas('teams', fn ($q) => $q->where('slug', $teamSlug));
            } else {
                // 'all' — show everything visible to the user
                $query->visibleTo($user);
            }

            if ($this->tab === 'fragments') {
                $query->where('type', 'fragment');
            } else {
                $query->where('type', 'prompt');
            }

            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            if ($this->categoryFilter) {
                $query->where('category_id', $this->categoryFilter);
            }

            if ($this->tagFilter) {
                $query->whereJsonContains('tags', $this->tagFilter);
            }

            $prompts = $query->with('latestVersion', 'category', 'creator')
                ->withCount('results')
                ->paginate(24);
        } elseif ($this->tab === 'starred') {
            $starredResults = Result::where('starred', true)
                ->with(['prompt.creator', 'promptVersion'])
                ->orderByDesc('created_at')
                ->paginate(24);
        }

        // Gather all unique tags for filter chips (scoped to visible prompts)
        $allTags = Prompt::visibleTo($user)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        // Condensed stats for quick-start header (user's own)
        $stats = [
            'prompts'   => Prompt::where('created_by', $user->id)->where('type', 'prompt')->count(),
            'fragments' => Prompt::where('created_by', $user->id)->where('type', 'fragment')->count(),
            'results'   => Result::count(),
        ];

        // Last-edited prompt for "Continue editing" link (user's own)
        $lastPrompt = Prompt::with('latestVersion', 'creator')
            ->where('created_by', $user->id)
            ->orderByDesc('updated_at')
            ->first();

        // User's teams for sidebar/scope switching
        $userTeams = $user->teams()->get();

        // Scope counts for sidebar badges
        $scopeCounts = [
            'mine' => Prompt::where('created_by', $user->id)->count(),
            'all' => Prompt::visibleTo($user)->count(),
        ];
        foreach ($userTeams as $team) {
            $scopeCounts['team:' . $team->slug] = Prompt::visibleTo($user)
                ->whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
                ->count();
        }

        return view('livewire.browse', [
            'prompts' => $prompts,
            'categories' => $categories,
            'allTags' => $allTags,
            'starredResults' => $starredResults,
            'stats' => $stats,
            'lastPrompt' => $lastPrompt,
            'userTeams' => $userTeams,
            'scopeCounts' => $scopeCounts,
            'collections' => Collection::orderBy('title')->get(['id', 'title']),
        ]);
    }
}
