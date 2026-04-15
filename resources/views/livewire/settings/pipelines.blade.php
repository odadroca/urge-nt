<div>
    {{-- Create button --}}
    <div class="mb-4">
        @if($showCreateForm)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <input wire:model="newName" type="text" placeholder="Pipeline name"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <textarea wire:model="newDescription" placeholder="Description (optional)" rows="2"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <div class="flex items-center gap-2">
                <button wire:click="createPipeline"
                        class="px-3 py-1.5 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium">
                    Create
                </button>
                <button wire:click="$set('showCreateForm', false)"
                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Cancel
                </button>
            </div>
        </div>
        @else
        <button wire:click="$set('showCreateForm', true)"
                class="px-3 py-1.5 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium">
            + New Pipeline
        </button>
        @endif
    </div>

    {{-- Pipelines list --}}
    <div class="space-y-3">
        @forelse($pipelines as $pipeline)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden {{ $expandedId === $pipeline->id ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : '' }}">
            <div class="p-4 flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                 wire:click="toggleExpand({{ $pipeline->id }})">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $pipeline->is_active ? 'bg-green-400' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                    <div class="min-w-0">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $pipeline->name }}</h4>
                        @if($pipeline->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $pipeline->description }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $pipeline->channels_count }} channels</span>
                    <button wire:click.stop="toggleActive({{ $pipeline->id }})"
                            class="text-xs {{ $pipeline->is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }} hover:underline">
                        {{ $pipeline->is_active ? 'Active' : 'Inactive' }}
                    </button>
                    <button wire:click.stop="deletePipeline({{ $pipeline->id }})" wire:confirm="Delete this pipeline and all its channels?"
                            class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300">Delete</button>
                </div>
            </div>

            {{-- Expanded: channels --}}
            @if($expandedId === $pipeline->id && $expandedPipeline)
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 space-y-4">
                {{-- Existing channels --}}
                @if($expandedPipeline->channels->isNotEmpty())
                <div class="space-y-2">
                    @foreach($expandedPipeline->channels as $channel)
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        @if($editingChannelId === $channel->id)
                        {{-- Edit form --}}
                        <div class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="editChannelRoleLabel" type="text" placeholder="Role label"
                                       class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <select wire:model="editChannelProviderId"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">No provider</option>
                                    @foreach($providers as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->model }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <textarea wire:model="editChannelSystemPrompt" rows="2" placeholder="System prompt"
                                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <div class="flex items-center gap-2">
                                <select wire:model="editChannelTrigger"
                                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="parallel">Parallel</option>
                                    <option value="synthesis">Synthesis</option>
                                </select>
                                <input wire:model="editChannelSortOrder" type="number" min="0" placeholder="Order"
                                       class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                <button wire:click="saveEditChannel" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Save</button>
                                <button wire:click="$set('editingChannelId', null)" class="text-xs text-gray-400 dark:text-gray-500">Cancel</button>
                            </div>
                        </div>
                        @else
                        {{-- Display --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $channel->role_label }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wide
                                        {{ $channel->trigger === 'synthesis'
                                            ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'
                                            : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' }}">
                                        {{ $channel->trigger }}
                                    </span>
                                    @if($channel->llmProvider)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $channel->llmProvider->name }}</span>
                                    @else
                                        <span class="text-xs text-amber-500 dark:text-amber-400 italic">no provider</span>
                                    @endif
                                </div>
                                @if($channel->system_prompt)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $channel->system_prompt }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button wire:click="startEditChannel({{ $channel->id }})"
                                        class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">Edit</button>
                                <button wire:click="deleteChannel({{ $channel->id }})" wire:confirm="Remove this channel?"
                                        class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300">Remove</button>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Add channel form --}}
                <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-md border border-indigo-200 dark:border-indigo-800 p-3 space-y-2">
                    <h5 class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wide">Add Channel</h5>
                    <div class="grid grid-cols-2 gap-2">
                        <input wire:model="channelRoleLabel" type="text" placeholder="Role label (e.g. strengths)"
                               class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <select wire:model="channelProviderId"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">No provider</option>
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->model }})</option>
                            @endforeach
                        </select>
                    </div>
                    <textarea wire:model="channelSystemPrompt" rows="2" placeholder="System prompt (optional)"
                              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <div class="flex items-center gap-2">
                        <select wire:model="channelTrigger"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="parallel">Parallel</option>
                            <option value="synthesis">Synthesis</option>
                        </select>
                        <input wire:model="channelSortOrder" type="number" min="0" placeholder="Order"
                               class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                        <button wire:click="addChannel"
                                class="px-3 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium">
                            Add
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @empty
        <div class="text-center py-8">
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No pipelines yet. Create one to define reusable LLM execution pipelines.</p>
        </div>
        @endforelse
    </div>
</div>
