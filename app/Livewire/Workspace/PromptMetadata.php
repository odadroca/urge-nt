<?php

namespace App\Livewire\Workspace;

use App\Models\Category;
use App\Models\Prompt;
use Livewire\Component;

class PromptMetadata extends Component
{
    public Prompt $prompt;
    public string $name;
    public string $description;
    public string $type;
    public ?int $categoryId;
    public string $tagsInput;

    public string $newCategoryName = '';
    public string $newCategoryColor = 'blue';
    public bool $showCategoryCreate = false;

    public function mount(Prompt $prompt)
    {
        $this->prompt = $prompt;
        $this->name = $prompt->name;
        $this->description = $prompt->description ?? '';
        $this->type = $prompt->type;
        $this->categoryId = $prompt->category_id;
        $this->tagsInput = $prompt->tags ? implode(', ', $prompt->tags) : '';
    }

    public bool $confirmingDelete = false;

    public function confirmDelete()
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
    }

    public function deletePrompt()
    {
        if (! $this->canManage()) {
            return;
        }

        $this->prompt->delete();

        return $this->redirect(route('browse'), navigate: true);
    }

    public function canManage(): bool
    {
        return $this->prompt->created_by === auth()->id() || auth()->user()->isAdmin();
    }

    public function shareWithTeam(int $teamId)
    {
        if (! $this->canManage()) {
            return;
        }

        // Verify user owns/belongs to this team
        $team = auth()->user()->teams()->where('teams.id', $teamId)->first();
        if (! $team) {
            return;
        }

        $this->prompt->teams()->syncWithoutDetaching([$teamId]);
        $this->prompt->update(['visibility' => 'shared']);
        $this->prompt->refresh();

        $this->dispatch('notify', message: "Shared with {$team->name}", type: 'success');
    }

    public function unshareFromTeam(int $teamId)
    {
        if (! $this->canManage()) {
            return;
        }

        $this->prompt->teams()->detach($teamId);
        $this->prompt->refresh();

        // Revert to private if no teams left
        if ($this->prompt->teams()->count() === 0) {
            $this->prompt->update(['visibility' => 'private']);
        }

        $this->dispatch('notify', message: 'Team removed', type: 'success');
    }

    public function createCategory()
    {
        if (! auth()->user()->isEditor()) {
            return;
        }

        $this->validate([
            'newCategoryName' => 'required|string|max:255',
            'newCategoryColor' => 'required|string',
        ]);

        $category = Category::create([
            'name' => $this->newCategoryName,
            'color' => $this->newCategoryColor,
        ]);

        $this->categoryId = $category->id;
        $this->newCategoryName = '';
        $this->newCategoryColor = 'blue';
        $this->showCategoryCreate = false;

        $this->dispatch('notify', message: 'Category created', type: 'success');
    }

    public function save()
    {
        $this->categoryId = $this->categoryId ?: null;

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'in:prompt,fragment',
            'categoryId' => 'nullable|exists:categories,id',
        ]);

        $tags = null;
        if ($this->tagsInput) {
            $tags = array_values(array_unique(array_filter(
                array_map(fn($t) => strtolower(trim($t, " \t\n\r\0\x0B\"'")), explode(',', $this->tagsInput))
            )));
            if (empty($tags)) {
                $tags = null;
            }
        }

        $updateData = [
            'description' => $this->description ?: null,
            'category_id' => $this->categoryId,
            'tags' => $tags,
        ];

        // Only owner/admin can rename or change type
        if ($this->canManage()) {
            $updateData['name'] = $this->name;
            $updateData['type'] = $this->type;
        }

        $this->prompt->update($updateData);

        // Refresh tagsInput to show cleaned tags
        $this->tagsInput = $tags ? implode(', ', $tags) : '';

        $this->dispatch('notify', message: 'Metadata updated', type: 'success');
    }

    public function render()
    {
        $allTags = Prompt::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->map(fn($t) => strtolower($t))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $availableTeams = auth()->user()->teams()->get();
        $sharedTeamIds = $this->prompt->teams()->pluck('teams.id')->toArray();

        return view('livewire.workspace.prompt-metadata', [
            'categories' => Category::orderBy('name')->get(),
            'allTags' => $allTags,
            'colorMap' => [
                'gray', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
                'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia',
                'pink', 'rose',
            ],
            'availableTeams' => $availableTeams,
            'sharedTeamIds' => $sharedTeamIds,
        ]);
    }
}
