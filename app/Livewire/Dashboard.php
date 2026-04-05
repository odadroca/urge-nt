<?php

namespace App\Livewire;

use App\Models\ApiKey;
use App\Models\LlmProvider;
use App\Models\Prompt;
use App\Models\Result;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public string $newPromptName = '';
    public string $newPromptType = 'prompt';
    public bool $showCreateForm = false;

    public function createPrompt()
    {
        $this->validate([
            'newPromptName' => 'required|string|max:255',
            'newPromptType' => 'in:prompt,fragment',
        ]);

        $prompt = Prompt::create([
            'name' => $this->newPromptName,
            'type' => $this->newPromptType,
            'created_by' => auth()->id(),
        ]);

        $prompt->load('creator');

        return $this->redirect($prompt->workspaceUrl(), navigate: true);
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'stats' => [
                'prompts'   => Prompt::count(),
                'fragments' => Prompt::where('type', 'fragment')->count(),
                'results'   => Result::count(),
                'starred'   => Result::where('starred', true)->count(),
                'providers' => LlmProvider::where('is_active', true)->count(),
                'api_keys'  => ApiKey::where('is_active', true)->count(),
            ],
            'recentPrompts' => Prompt::with('latestVersion', 'category', 'creator')
                ->orderByDesc('updated_at')
                ->limit(12)
                ->get(),
            'starredResults' => Result::where('starred', true)
                ->with(['prompt.creator', 'promptVersion'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(),
            'recentResults' => Result::with(['prompt.creator', 'promptVersion'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
