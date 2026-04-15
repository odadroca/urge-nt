<?php

namespace App\Livewire\Settings;

use App\Models\EvaluationSetting;
use App\Models\LlmProvider;
use App\Models\Prompt;
use Livewire\Component;

class Evaluation extends Component
{
    public bool $enabled = false;
    public bool $autoEvaluate = false;
    public ?int $defaultProviderId = null;
    public string $promptSlug = '';
    public array $dimensions = [];
    public string $newDimensionName = '';
    public string $newDimensionDescription = '';

    public function mount(): void
    {
        $this->enabled = (bool) EvaluationSetting::get('enabled', false);
        $this->autoEvaluate = (bool) EvaluationSetting::get('auto_evaluate', false);
        $this->defaultProviderId = EvaluationSetting::get('default_provider_id');
        $this->promptSlug = EvaluationSetting::get('prompt_slug', 'system-evaluation-template');
        $this->dimensions = EvaluationSetting::get('dimensions', config('urge.evaluation.default_dimensions'));
    }

    public function save(): void
    {
        EvaluationSetting::set('enabled', $this->enabled);
        EvaluationSetting::set('auto_evaluate', $this->autoEvaluate);
        EvaluationSetting::set('default_provider_id', $this->defaultProviderId);
        EvaluationSetting::set('prompt_slug', $this->promptSlug);
        EvaluationSetting::set('dimensions', $this->dimensions);
        $this->dispatch('notify', message: 'Evaluation settings saved', type: 'success');
    }

    public function toggleDimension(int $index): void
    {
        $this->dimensions[$index]['enabled'] = !$this->dimensions[$index]['enabled'];
    }

    public function updateWeight(int $index, float $weight): void
    {
        $this->dimensions[$index]['weight'] = max(0, min(5, $weight));
    }

    public function addDimension(): void
    {
        if (!$this->newDimensionName) return;

        $this->dimensions[] = [
            'name'        => \Illuminate\Support\Str::slug($this->newDimensionName, '_'),
            'description' => $this->newDimensionDescription ?: $this->newDimensionName,
            'weight'      => 1.0,
            'enabled'     => true,
            'builtin'     => false,
        ];

        $this->newDimensionName = '';
        $this->newDimensionDescription = '';
    }

    public function removeDimension(int $index): void
    {
        if (isset($this->dimensions[$index]) && !($this->dimensions[$index]['builtin'] ?? true)) {
            array_splice($this->dimensions, $index, 1);
        }
    }

    public function render()
    {
        return view('livewire.settings.evaluation', [
            'providers'   => LlmProvider::where('is_active', true)->get(['id', 'name', 'model']),
            'evalPrompts' => Prompt::where('type', 'fragment')->get(['id', 'slug', 'name']),
        ]);
    }
}
