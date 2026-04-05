<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Teams')]
class Teams extends Component
{
    public string $newTeamName = '';
    public bool $showCreateForm = false;

    public function createTeam()
    {
        $this->validate([
            'newTeamName' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'name' => $this->newTeamName,
            'created_by' => auth()->id(),
        ]);

        $team->members()->attach(auth()->id(), ['role' => 'owner']);

        $this->newTeamName = '';
        $this->showCreateForm = false;

        $this->dispatch('notify', message: "Team \"{$team->name}\" created", type: 'success');
    }

    public function render()
    {
        $teams = auth()->user()->teams()
            ->withCount('members', 'prompts')
            ->withPivot('role')
            ->orderBy('name')
            ->get();

        return view('livewire.teams', [
            'teams' => $teams,
        ]);
    }
}
