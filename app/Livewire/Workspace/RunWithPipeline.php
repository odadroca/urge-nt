<?php

namespace App\Livewire\Workspace;

use App\Models\Pipeline;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Services\PipelineService;
use Livewire\Attributes\On;
use Livewire\Component;

class RunWithPipeline extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public bool $showPanel = false;
    public ?int $selectedPipelineId = null;
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

    public function run(PipelineService $service): void
    {
        if (!$this->currentVersionId || !$this->selectedPipelineId) {
            return;
        }

        $this->isRunning = true;

        $pipeline = Pipeline::where('id', $this->selectedPipelineId)
            ->where('is_active', true)
            ->first();

        if (!$pipeline) {
            $this->isRunning = false;
            $this->dispatch('notify', message: 'Pipeline not found or inactive.', type: 'error');
            return;
        }

        $version = PromptVersion::findOrFail($this->currentVersionId);

        $resultIds = $service->run($pipeline, $version, $this->variableValues, auth()->id());

        $this->isRunning = false;
        $this->dispatch('result-saved');
        $this->dispatch('notify', message: count($resultIds) . ' result(s) from pipeline run.', type: 'success');
    }

    public function render()
    {
        $version = $this->currentVersionId ? PromptVersion::find($this->currentVersionId) : null;
        $variables = $version ? ($version->variables ?? []) : [];
        $metadata = $version ? ($version->variable_metadata ?? []) : [];

        $selectedPipeline = $this->selectedPipelineId
            ? Pipeline::with('channels.llmProvider')->find($this->selectedPipelineId)
            : null;

        return view('livewire.workspace.run-with-pipeline', [
            'pipelines' => Pipeline::where('is_active', true)->orderBy('name')->get(),
            'variables' => $variables,
            'variableMetadata' => $metadata,
            'selectedPipeline' => $selectedPipeline,
        ]);
    }
}
