<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UserManagement extends Component
{
    public ?int $deleteConfirmId = null;
    public bool $showCreateForm = false;
    public string $newName = '';
    public string $newEmail = '';
    public string $newPassword = '';
    public string $newRole = 'editor';

    public function mount(): void
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }
    }

    public function createUser(): void
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        $this->validate([
            'newName'     => 'required|string|max:255',
            'newEmail'    => 'required|email|unique:users,email',
            'newPassword' => 'required|string|min:8',
            'newRole'     => 'required|in:admin,editor,viewer',
        ]);

        User::create([
            'name'     => $this->newName,
            'email'    => $this->newEmail,
            'password' => Hash::make($this->newPassword),
            'role'     => $this->newRole,
        ]);

        $this->reset(['newName', 'newEmail', 'newPassword', 'newRole', 'showCreateForm']);
        $this->newRole = 'editor';
        $this->dispatch('notify', message: 'User created.', type: 'success');
    }

    public function changeRole(int $userId, string $role): void
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        if (!in_array($role, ['admin', 'editor', 'viewer'])) {
            return;
        }

        $user = User::findOrFail($userId);

        // Cannot change own role
        if ($user->id === auth()->id()) {
            return;
        }

        $user->update(['role' => $role]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteConfirmId = $id;
    }

    public function deleteUser(): void
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        if ($this->deleteConfirmId) {
            $user = User::findOrFail($this->deleteConfirmId);

            // Cannot delete self
            if ($user->id === auth()->id()) {
                $this->deleteConfirmId = null;
                return;
            }

            $user->delete();
            $this->deleteConfirmId = null;
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteConfirmId = null;
    }

    public function render()
    {
        return view('livewire.settings.user-management', [
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
