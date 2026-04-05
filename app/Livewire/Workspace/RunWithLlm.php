<?php

namespace App\Livewire\Workspace;

use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Services\LlmDispatchService;
use App\Services\TemplateEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class RunWithLlm extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public bool $showPanel = false;
    public array $selectedProviderIds = [];
    public array $variableValues = [];
    public bool $isRunning = false;

    public function mount(Prompt $prompt, ?PromptVersion $currentVersion = null)
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

    #[On('toggle-run-panel')]
    public function togglePanel(): void
    {
        $this->showPanel = !$this->showPanel;
    }

    public function run(): void
    {
        if (!$this->currentVersionId || empty($this->selectedProviderIds)) {
            return;
        }

        $this->isRunning = true;

        $version = PromptVersion::findOrFail($this->currentVersionId);
        $templateEngine = app(TemplateEngine::class);
        $dispatchService = app(LlmDispatchService::class);

        $renderResult = $templateEngine->render(
            $version->content,
            $this->variableValues,
            $version->variable_metadata,
            auth()->user()
        );
        $renderedContent = $renderResult['rendered'];

        foreach ($this->selectedProviderIds as $providerId) {
            $provider = LlmProvider::where('id', $providerId)->where('is_active', true)->first();
            if (!$provider) {
                continue;
            }

            $llmResult = $dispatchService->dispatch($provider, $renderedContent);

            Result::create([
                'prompt_id' => $this->prompt->id,
                'prompt_version_id' => $this->currentVersionId,
                'source' => 'api',
                'provider_name' => $provider->name,
                'model_name' => $llmResult->modelUsed,
                'llm_provider_id' => $provider->id,
                'rendered_content' => $renderedContent,
                'variables_used' => !empty($this->variableValues) ? $this->variableValues : null,
                'response_text' => $llmResult->text,
                'input_tokens' => $llmResult->inputTokens,
                'output_tokens' => $llmResult->outputTokens,
                'duration_ms' => $llmResult->durationMs,
                'status' => $llmResult->success ? 'success' : 'error',
                'error_message' => $llmResult->error,
                'created_by' => auth()->id(),
            ]);
        }

        $this->isRunning = false;
        $this->dispatch('result-saved');
        $this->dispatch('notify', message: count($this->selectedProviderIds) . ' provider(s) completed', type: 'success');
    }

    public function render()
    {
        $version = $this->currentVersionId ? PromptVersion::find($this->currentVersionId) : null;
        $variables = $version ? ($version->variables ?? []) : [];
        $metadata = $version ? ($version->variable_metadata ?? []) : [];

        return view('livewire.workspace.run-with-llm', [
            'providers' => LlmProvider::where('is_active', true)->orderBy('name')->get(),
            'variables' => $variables,
            'variableMetadata' => $metadata,
        ]);
    }
}
