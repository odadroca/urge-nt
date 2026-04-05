<?php

namespace App\Livewire\Settings;

use App\Models\ApiKey;
use App\Models\Prompt;
use App\Services\ApiKeyService;
use Livewire\Component;

class ApiKeys extends Component
{
    public string $newKeyName = '';
    public array $selectedPromptIds = [];
    public ?string $generatedKey = null;
    public bool $showCreateForm = false;
    public ?int $deleteConfirmId = null;

    public function create(ApiKeyService $service): void
    {
        $this->validate([
            'newKeyName' => 'required|string|max:255',
        ]);

        $result = $service->generateKey(
            auth()->user(),
            $this->newKeyName,
            $this->selectedPromptIds
        );

        $this->generatedKey = $result['key'];
        $this->newKeyName = '';
        $this->selectedPromptIds = [];
        $this->showCreateForm = false;
    }

    public function dismissKey(): void
    {
        $this->generatedKey = null;
    }

    public function toggleActive(int $id): void
    {
        $key = ApiKey::where('user_id', auth()->id())->findOrFail($id);
        $key->update(['is_active' => !$key->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteConfirmId = $id;
    }

    public function delete(): void
    {
        if ($this->deleteConfirmId) {
            ApiKey::where('user_id', auth()->id())
                ->where('id', $this->deleteConfirmId)
                ->delete();
            $this->deleteConfirmId = null;
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteConfirmId = null;
    }

    public function render()
    {
        return view('livewire.settings.api-keys', [
            'apiKeys' => ApiKey::where('user_id', auth()->id())
                ->with('prompts')
                ->orderByDesc('created_at')
                ->get(),
            'prompts' => Prompt::orderBy('name')->get(),
        ]);
    }
}
