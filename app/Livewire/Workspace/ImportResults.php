<?php

namespace App\Livewire\Workspace;

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Services\ImportExportService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportResults extends Component
{
    use WithFileUploads;

    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public $files = [];
    public array $previews = [];
    public bool $showPreview = false;

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

    public function updatedFiles()
    {
        $this->previews = [];
        $service = app(ImportExportService::class);

        foreach ($this->files as $file) {
            $content = file_get_contents($file->getRealPath());
            $parsed = $service->parseMarkdownWithFrontmatter($content);

            $this->previews[] = [
                'filename' => $file->getClientOriginalName(),
                'meta' => $parsed['meta'],
                'body_preview' => mb_substr($parsed['body'], 0, 200),
                'raw' => $content,
            ];
        }

        $this->showPreview = count($this->previews) > 0;
    }

    public function confirmImport()
    {
        if (!$this->currentVersionId) {
            $this->addError('files', 'Save a version first before importing results.');
            return;
        }

        $version = PromptVersion::findOrFail($this->currentVersionId);
        $service = app(ImportExportService::class);
        $count = 0;

        foreach ($this->previews as $preview) {
            $service->importResult(
                $preview['raw'],
                $version,
                auth()->user(),
                $preview['filename']
            );
            $count++;
        }

        $this->reset(['files', 'previews', 'showPreview']);
        $this->dispatch('result-saved');
    }

    public function cancelImport()
    {
        $this->reset(['files', 'previews', 'showPreview']);
    }

    public function render()
    {
        return view('livewire.workspace.import-results');
    }
}
