<?php

namespace App\Livewire\Settings;

use App\Models\LlmProvider;
use App\Services\LlmDispatchService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class LlmProviders extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $driver = 'openai';
    public string $apiKey = '';
    public string $model = '';
    public string $endpoint = '';
    public bool $isActive = true;
    public ?int $deleteConfirmId = null;
    public ?int $testingId = null;
    public ?string $testResult = null;
    public ?string $testStatus = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'driver' => 'required|in:openai,anthropic,mistral,gemini,ollama,openrouter',
            'model' => 'required|string|max:255',
            'apiKey' => $this->driver === 'ollama' ? 'nullable|string' : ($this->editingId ? 'nullable|string' : 'required|string'),
            'endpoint' => 'nullable|string|max:500',
        ];
    }

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->validate();

        LlmProvider::create([
            'name' => $this->name,
            'driver' => $this->driver,
            'api_key' => $this->apiKey ?: null,
            'model' => $this->model,
            'endpoint' => $this->endpoint ?: null,
            'is_active' => $this->isActive,
        ]);

        $this->resetForm();
        $this->dispatch('notify', message: 'Provider created', type: 'success');
    }

    public function edit(int $id): void
    {
        $this->authorizeAdmin();
        $provider = LlmProvider::findOrFail($id);
        $this->editingId = $id;
        $this->name = $provider->name;
        $this->driver = $provider->driver;
        $this->apiKey = '';
        $this->model = $provider->model;
        $this->endpoint = $provider->endpoint ?? '';
        $this->isActive = $provider->is_active;
        $this->showForm = true;
    }

    public function update(): void
    {
        $this->authorizeAdmin();
        $this->validate();

        $provider = LlmProvider::findOrFail($this->editingId);
        $data = [
            'name' => $this->name,
            'driver' => $this->driver,
            'model' => $this->model,
            'endpoint' => $this->endpoint ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->apiKey !== '') {
            $data['api_key'] = $this->apiKey;
        }

        $provider->update($data);
        $this->resetForm();
        $this->dispatch('notify', message: 'Provider updated', type: 'success');
    }

    public function testConnection(int $id): void
    {
        $this->authorizeAdmin();
        $provider = LlmProvider::findOrFail($id);

        try {
            $service = app(LlmDispatchService::class);
            $result = $service->dispatch($provider, 'Say "Connection successful" in exactly two words.');

            $this->testingId = $id;
            if ($result->success) {
                $this->testStatus = 'success';
                $this->testResult = "Connected: {$result->modelUsed} ({$result->durationMs}ms)";
            } else {
                $this->testStatus = 'error';
                $this->testResult = "Failed: {$result->error}";
            }
        } catch (\Throwable $e) {
            $this->testingId = $id;
            $this->testStatus = 'error';
            $this->testResult = "Error: {$e->getMessage()}";
        }
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeAdmin();
        $provider = LlmProvider::findOrFail($id);
        $provider->update(['is_active' => !$provider->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAdmin();
        $this->deleteConfirmId = $id;
    }

    public function delete(): void
    {
        $this->authorizeAdmin();
        if ($this->deleteConfirmId) {
            LlmProvider::destroy($this->deleteConfirmId);
            $this->deleteConfirmId = null;
            $this->dispatch('notify', message: 'Provider deleted', type: 'success');
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteConfirmId = null;
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()->isAdmin()) {
            throw new AuthorizationException('Admin access required.');
        }
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'driver', 'apiKey', 'model', 'endpoint', 'isActive', 'testingId', 'testResult', 'testStatus']);
        $this->isActive = true;
        $this->driver = 'openai';
    }

    public function render()
    {
        return view('livewire.settings.llm-providers', [
            'providers' => LlmProvider::orderBy('name')->get(),
        ]);
    }
}
