<?php

namespace App\Livewire\Workspace;

use App\Models\PipelineTemplate;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Services\PipelineTemplateService;
use Livewire\Attributes\On;
use Livewire\Component;

class RunWithTemplate extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public bool $showPanel = false;
    public ?int $selectedTemplateId = null;
    public array $variableValues = [];
    public bool $isRunning = false;

    public function mount(Prompt $prompt, ?PromptVersion $currentVersion = null): void
    {
        $this->prompt = $prompt;
        $this->currentVersionId = $currentVersion?->id;
    }

    #[On('version-selected')]
    #[On('version-created')]
    public function onVersionChanged(int $versionId): void
    {
        $this->currentVersionId = $versionId;
    }

    #[On('toggle-template-panel')]
    public function togglePanel(): void
    {
        $this->showPanel = !$this->showPanel;
    }

    public function run(PipelineTemplateService $service): void
    {
        if (!$this->currentVersionId || !$this->selectedTemplateId) {
            return;
        }

        $this->isRunning = true;

        $template = PipelineTemplate::where('id', $this->selectedTemplateId)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            $this->isRunning = false;
            $this->dispatch('notify', message: 'Template not found or inactive.', type: 'error');
            return;
        }

        $version = PromptVersion::findOrFail($this->currentVersionId);

        $resultIds = $service->run($template, $version, $this->variableValues, auth()->id());

        $this->isRunning = false;
        $this->dispatch('result-saved');
        $this->dispatch('notify', message: count($resultIds) . ' result(s) from template run.', type: 'success');
    }

    public function render()
    {
        $version = $this->currentVersionId ? PromptVersion::find($this->currentVersionId) : null;
        $variables = $version ? ($version->variables ?? []) : [];
        $metadata = $version ? ($version->variable_metadata ?? []) : [];

        $selectedTemplate = $this->selectedTemplateId
            ? PipelineTemplate::with('channels.llmProvider')->find($this->selectedTemplateId)
            : null;

        return view('livewire.workspace.run-with-template', [
            'templates' => PipelineTemplate::where('is_active', true)->orderBy('name')->get(),
            'variables' => $variables,
            'variableMetadata' => $metadata,
            'selectedTemplate' => $selectedTemplate,
        ]);
    }
}
