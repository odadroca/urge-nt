<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $activeTab = '';

    public function mount(): void
    {
        // Set default tab based on role — non-admins land on llm-providers
        $user = auth()->user();

        if ($user->isAdmin()) {
            $this->activeTab = 'api-keys';
        } else {
            $this->activeTab = 'llm-providers';
        }
    }

    /**
     * Return the list of tabs visible to the current user.
     */
    public function getVisibleTabsProperty(): array
    {
        $user = auth()->user();
        $tabs = [];

        if ($user->isAdmin()) {
            $tabs[] = 'api-keys';
        }

        // All authenticated users can see LLM Providers (read-only for non-admins)
        $tabs[] = 'llm-providers';

        // All authenticated users can see Categories (read-only for viewers, editable for editors+)
        $tabs[] = 'categories';

        $tabs[] = 'templates';

        $tabs[] = 'evaluation';

        if ($user->isAdmin()) {
            $tabs[] = 'users';
        }

        return $tabs;
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
