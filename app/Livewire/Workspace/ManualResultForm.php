<?php

namespace App\Livewire\Workspace;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use Livewire\Attributes\On;
use Livewire\Component;

class ManualResultForm extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;

    public bool $showForm = false;
    public string $responseText = '';
    public string $providerName = '';
    public string $modelName = '';
    public string $notes = '';
    public ?int $rating = null;

    public function mount(Prompt $prompt, ?PromptVersion $currentVersion = null)
    {
        $this->prompt = $prompt;
        $this->currentVersionId = $currentVersion?->id;
    }

    #[On('version-selected')]
    #[On('version-created')]
    public function onVersionChanged(int $versionId)
    {
        $this->currentVersionId = $versionId;
    }

    public function save()
    {
        $this->validate([
            'responseText' => 'required|string',
            'providerName' => 'nullable|string|max:100',
            'modelName' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        if (!$this->currentVersionId) {
            $this->addError('responseText', 'Save a version first before adding results.');
            return;
        }

        Result::create([
            'prompt_id' => $this->prompt->id,
            'prompt_version_id' => $this->currentVersionId,
            'source' => 'manual',
            'provider_name' => $this->providerName ?: null,
            'model_name' => $this->modelName ?: null,
            'response_text' => $this->responseText,
            'notes' => $this->notes ?: null,
            'rating' => $this->rating,
            'status' => 'success',
            'created_by' => auth()->id(),
        ]);

        $this->reset(['responseText', 'providerName', 'modelName', 'notes', 'rating']);
        $this->showForm = false;
        $this->dispatch('result-saved');
    }

    public function render()
    {
        return view('livewire.workspace.manual-result-form');
    }
}
