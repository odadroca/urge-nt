<?php

namespace App\Livewire\Workspace;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Result;
use App\Services\AiAssistantService;
use App\Services\ImportExportService;
use Livewire\Attributes\On;
use Livewire\Component;

class ResultsPanel extends Component
{
    public Prompt $prompt;
    public ?int $currentVersionId = null;
    public bool $showAllVersions = false;
    public string $sortBy = 'newest';
    public ?string $aiSummary = null;
    public bool $aiSummarizing = false;

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

    #[On('result-saved')]
    public function refreshResults()
    {
        // Just re-render
    }

    public function toggleStar(int $resultId)
    {
        $result = Result::findOrFail($resultId);
        $result->update(['starred' => !$result->starred]);
    }

    public function updateRating(int $resultId, int $rating)
    {
        $result = Result::findOrFail($resultId);
        $result->update(['rating' => $rating]);
    }

    public function deleteResult(int $resultId)
    {
        Result::findOrFail($resultId)->delete();
    }

    public function exportResult(int $resultId)
    {
        $result = Result::with(['prompt', 'promptVersion'])->findOrFail($resultId);
        $service = app(ImportExportService::class);
        $content = $service->exportResult($result);
        $filename = ($result->prompt->slug ?? 'result') . '-v' . ($result->promptVersion->version_number ?? 0) . '-' . $result->id . '.md';

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

    public function exportAllResults()
    {
        $query = Result::where('prompt_id', $this->prompt->id)
            ->with(['prompt', 'promptVersion'])
            ->orderByDesc('created_at');

        if (!$this->showAllVersions && $this->currentVersionId) {
            $query->where('prompt_version_id', $this->currentVersionId);
        }

        $results = $query->get();
        if ($results->isEmpty()) {
            return;
        }

        $service = app(ImportExportService::class);
        $filename = $this->prompt->slug . '-results.zip';

        return response()->streamDownload(function () use ($results, $service) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'urge_export_');
            $zip = new \ZipArchive();
            $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($results as $i => $result) {
                $name = ($result->provider_name ?: 'result') . '-' . $result->id . '.md';
                $zip->addFromString($name, $service->exportResult($result));
            }

            $zip->close();
            readfile($tmpFile);
            unlink($tmpFile);
        }, $filename);
    }

    public function aiSummarizeDifferences(int $resultIdA, int $resultIdB, int $providerId): void
    {
        $a = Result::findOrFail($resultIdA);
        $b = Result::findOrFail($resultIdB);
        $provider = LlmProvider::findOrFail($providerId);

        $this->aiSummarizing = true;
        $service = app(AiAssistantService::class);
        $result = $service->summarizeDifferences($a->response_text, $b->response_text, $provider);
        $this->aiSummarizing = false;

        $this->aiSummary = $result->success ? $result->text : "Error: {$result->error}";
    }

    public function addResultToCollection(int $resultId, int $collectionId)
    {
        CollectionItem::firstOrCreate([
            'collection_id' => $collectionId,
            'item_type' => 'result',
            'item_id' => $resultId,
        ], [
            'sort_order' => CollectionItem::where('collection_id', $collectionId)->max('sort_order') + 1,
        ]);
    }

    public function collectPipelineRun(string $runId, int $collectionId)
    {
        $results = Result::where('pipeline_run_id', $runId)->get();
        foreach ($results as $result) {
            CollectionItem::firstOrCreate([
                'collection_id' => $collectionId,
                'item_type' => 'result',
                'item_id' => $result->id,
            ], [
                'sort_order' => CollectionItem::where('collection_id', $collectionId)->max('sort_order') + 1,
            ]);
        }
        $this->dispatch('notify', message: $results->count() . ' results added to collection.', type: 'success');
    }

    public function render()
    {
        $query = Result::where('prompt_id', $this->prompt->id)
            ->with(['promptVersion', 'pipelineTemplate']);

        if (!$this->showAllVersions && $this->currentVersionId) {
            $query->where('prompt_version_id', $this->currentVersionId);
        }

        $query = match ($this->sortBy) {
            'oldest'       => $query->orderBy('created_at'),
            'rating_desc'  => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'tokens_desc'  => $query->orderByDesc('output_tokens')->orderByDesc('created_at'),
            'duration_asc' => $query->orderBy('duration_ms')->orderByDesc('created_at'),
            default        => $query->orderByDesc('created_at'),
        };

        $allResults = $query->get();

        // Group pipeline results by pipeline_run_id (deterministic)
        $grouped = collect();
        $pipelineBuffer = [];

        foreach ($allResults as $result) {
            if ($result->pipeline_run_id) {
                $key = $result->pipeline_run_id;
                if (!isset($pipelineBuffer[$key])) {
                    $pipelineBuffer[$key] = [
                        'template' => $result->pipelineTemplate,
                        'results' => collect(),
                        'first_at' => $result->created_at,
                        'run_id' => $result->pipeline_run_id,
                    ];
                }
                $pipelineBuffer[$key]['results']->push($result);
            } else {
                // Flush any pending pipeline groups before adding standalone
                foreach ($pipelineBuffer as $buf) {
                    $grouped->push(['type' => 'pipeline', 'data' => $buf]);
                }
                $pipelineBuffer = [];
                $grouped->push(['type' => 'standalone', 'data' => $result]);
            }
        }
        // Flush remaining pipeline groups
        foreach ($pipelineBuffer as $buf) {
            $grouped->push(['type' => 'pipeline', 'data' => $buf]);
        }

        return view('livewire.workspace.results-panel', [
            'results' => $allResults,
            'groupedResults' => $grouped,
            'collections' => Collection::orderBy('title')->get(['id', 'title']),
        ]);
    }
}
