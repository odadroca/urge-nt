<?php

namespace App\Livewire\Settings;

use App\Models\LlmProvider;
use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateChannel;
use Livewire\Component;

class PipelineTemplates extends Component
{
    // Template form
    public string $newName = '';
    public string $newDescription = '';
    public bool $showCreateForm = false;

    // Expanded template
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

    public function createTemplate(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newDescription' => 'nullable|string',
        ]);

        PipelineTemplate::create([
            'name' => $this->newName,
            'description' => $this->newDescription ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['newName', 'newDescription', 'showCreateForm']);
        $this->dispatch('notify', message: 'Template created.', type: 'success');
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->resetChannelForm();
        $this->editingChannelId = null;
    }

    public function toggleActive(int $id): void
    {
        $template = PipelineTemplate::findOrFail($id);
        $template->update(['is_active' => !$template->is_active]);
    }

    public function deleteTemplate(int $id): void
    {
        PipelineTemplate::findOrFail($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
        $this->dispatch('notify', message: 'Template deleted.', type: 'success');
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

        PipelineTemplateChannel::create([
            'pipeline_template_id' => $this->expandedId,
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
        $channel = PipelineTemplateChannel::findOrFail($channelId);
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

        $channel = PipelineTemplateChannel::findOrFail($this->editingChannelId);
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
        PipelineTemplateChannel::findOrFail($channelId)->delete();
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
        $templates = PipelineTemplate::withCount('channels')->orderBy('name')->get();

        $expandedTemplate = null;
        if ($this->expandedId) {
            $expandedTemplate = PipelineTemplate::with(['channels.llmProvider'])->find($this->expandedId);
        }

        return view('livewire.settings.pipeline-templates', [
            'templates' => $templates,
            'expandedTemplate' => $expandedTemplate,
            'providers' => LlmProvider::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
