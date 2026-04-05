<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeamDetail extends Component
{
    public Team $team;
    public string $inviteQuery = '';
    public bool $confirmingDelete = false;

    public function mount(Team $team)
    {
        $isMember = $team->members()->where('users.id', auth()->id())->exists();

        if (! $isMember && ! auth()->user()->isAdmin()) {
            abort(404);
        }

        $this->team = $team;
    }

    public function getIsTeamOwnerProperty(): bool
    {
        return $this->team->members()
            ->where('users.id', auth()->id())
            ->wherePivot('role', 'owner')
            ->exists() || auth()->user()->isAdmin();
    }

    public function inviteMember()
    {
        if (! $this->isTeamOwner) {
            return;
        }

        $this->validate([
            'inviteQuery' => 'required|string',
        ]);

        $user = User::where('email', $this->inviteQuery)
            ->orWhere('name', $this->inviteQuery)
            ->first();

        if (! $user) {
            $this->dispatch('notify', message: 'User not found', type: 'error');
            return;
        }

        if ($this->team->members()->where('users.id', $user->id)->exists()) {
            $this->dispatch('notify', message: "{$user->name} is already a member", type: 'error');
            return;
        }

        $this->team->members()->attach($user->id, ['role' => 'member']);
        $this->inviteQuery = '';

        $this->dispatch('notify', message: "{$user->name} added to team", type: 'success');
    }

    public function removeMember(int $userId)
    {
        if (! $this->isTeamOwner) {
            return;
        }

        // Cannot remove yourself if sole owner
        if ($userId === auth()->id()) {
            $ownerCount = $this->team->owners()->count();
            if ($ownerCount <= 1) {
                $this->dispatch('notify', message: 'Cannot remove the sole owner', type: 'error');
                return;
            }
        }

        $this->team->members()->detach($userId);
        $this->dispatch('notify', message: 'Member removed', type: 'success');
    }

    public function leaveTeam()
    {
        $pivot = $this->team->members()
            ->where('users.id', auth()->id())
            ->first();

        if ($pivot && $pivot->pivot->role === 'owner' && $this->team->owners()->count() <= 1) {
            $this->dispatch('notify', message: 'Cannot leave as sole owner. Transfer ownership first.', type: 'error');
            return;
        }

        $this->team->members()->detach(auth()->id());

        return $this->redirect(route('teams'), navigate: true);
    }

    public function confirmDelete()
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
    }

    public function deleteTeam()
    {
        if (! $this->isTeamOwner) {
            return;
        }

        $this->team->delete();

        return $this->redirect(route('teams'), navigate: true);
    }

    public function getTitle(): string
    {
        return $this->team->name;
    }

    public function render()
    {
        $members = $this->team->members()
            ->withPivot('role')
            ->orderByRaw("CASE WHEN team_user.role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $sharedPrompts = $this->team->prompts()
            ->with('creator', 'latestVersion')
            ->orderByDesc('updated_at')
            ->get();

        return view('livewire.team-detail', [
            'members' => $members,
            'sharedPrompts' => $sharedPrompts,
        ])->title($this->team->name);
    }
}
