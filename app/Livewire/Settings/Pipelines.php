<?php

namespace App\Livewire\Settings;

use App\Models\LlmProvider;
use App\Models\Pipeline;
use App\Models\PipelineChannel;
use Livewire\Component;

class Pipelines extends Component
{
    // Pipeline form
    public string $newName = '';
    public string $newDescription = '';
    public bool $showCreateForm = false;

    // Expanded pipeline
    public ?int $expandedId = null;

    // Channel form
    public string $channelRoleLabel = '';
    public ?int $channelProviderId = null;
    public string $channelSystemPrompt = '';
    public string $channelTrigger = 'parallel';
    public int $channelSortOrder = 0;

    // Edit channel
    public ?int $editingChannelId = null;
    public string $editChannelRoleLabel = '';
    public ?int $editChannelProviderId = null;
    public string $editChannelSystemPrompt = '';
    public string $editChannelTrigger = 'parallel';
    public int $editChannelSortOrder = 0;

    public function createPipeline(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newDescription' => 'nullable|string',
        ]);

        Pipeline::create([
            'name' => $this->newName,
            'description' => $this->newDescription ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['newName', 'newDescription', 'showCreateForm']);
        $this->dispatch('notify', message: 'Pipeline created.', type: 'success');
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->resetChannelForm();
        $this->editingChannelId = null;
    }

    public function toggleActive(int $id): void
    {
        $pipeline = Pipeline::findOrFail($id);
        $pipeline->update(['is_active' => !$pipeline->is_active]);
    }

    public function deletePipeline(int $id): void
    {
        Pipeline::findOrFail($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
        $this->dispatch('notify', message: 'Pipeline deleted.', type: 'success');
    }

    public function addChannel(): void
    {
        $this->validate([
            'channelRoleLabel' => 'required|string|max:255',
            'channelProviderId' => 'nullable|integer|exists:llm_providers,id',
            'channelSystemPrompt' => 'nullable|string',
            'channelTrigger' => 'required|in:parallel,synthesis',
            'channelSortOrder' => 'integer|min:0',
        ]);

        PipelineChannel::create([
            'pipeline_id' => $this->expandedId,
            'role_label' => $this->channelRoleLabel,
            'llm_provider_id' => $this->channelProviderId,
            'system_prompt' => $this->channelSystemPrompt ?: null,
            'trigger' => $this->channelTrigger,
            'sort_order' => $this->channelSortOrder,
        ]);

        $this->resetChannelForm();
        $this->dispatch('notify', message: 'Channel added.', type: 'success');
    }

    public function startEditChannel(int $channelId): void
    {
        $channel = PipelineChannel::findOrFail($channelId);
        $this->editingChannelId = $channelId;
        $this->editChannelRoleLabel = $channel->role_label;
        $this->editChannelProviderId = $channel->llm_provider_id;
        $this->editChannelSystemPrompt = $channel->system_prompt ?? '';
        $this->editChannelTrigger = $channel->trigger;
        $this->editChannelSortOrder = $channel->sort_order;
    }

    public function saveEditChannel(): void
    {
        $this->validate([
            'editChannelRoleLabel' => 'required|string|max:255',
            'editChannelProviderId' => 'nullable|integer|exists:llm_providers,id',
            'editChannelSystemPrompt' => 'nullable|string',
            'editChannelTrigger' => 'required|in:parallel,synthesis',
            'editChannelSortOrder' => 'integer|min:0',
        ]);

        $channel = PipelineChannel::findOrFail($this->editingChannelId);
        $channel->update([
            'role_label' => $this->editChannelRoleLabel,
            'llm_provider_id' => $this->editChannelProviderId,
            'system_prompt' => $this->editChannelSystemPrompt ?: null,
            'trigger' => $this->editChannelTrigger,
            'sort_order' => $this->editChannelSortOrder,
        ]);

        $this->editingChannelId = null;
    }

    public function deleteChannel(int $channelId): void
    {
        PipelineChannel::findOrFail($channelId)->delete();
        $this->dispatch('notify', message: 'Channel removed.', type: 'success');
    }

    private function resetChannelForm(): void
    {
        $this->channelRoleLabel = '';
        $this->channelProviderId = null;
        $this->channelSystemPrompt = '';
        $this->channelTrigger = 'parallel';
        $this->channelSortOrder = 0;
    }

    public function render()
    {
        $pipelines = Pipeline::withCount('channels')->orderBy('name')->get();

        $expandedPipeline = null;
        if ($this->expandedId) {
            $expandedPipeline = Pipeline::with(['channels.llmProvider'])->find($this->expandedId);
        }

        return view('livewire.settings.pipelines', [
            'pipelines' => $pipelines,
            'expandedPipeline' => $expandedPipeline,
            'providers' => LlmProvider::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
