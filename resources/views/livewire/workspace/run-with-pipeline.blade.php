<div>
    @if($showPanel && $currentVersionId)
    <div class="border-t border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Run Pipeline</h4>
            <button wire:click="$set('showPanel', false)" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
        </div>

        @if($pipelines->isEmpty())
            <p class="text-xs text-gray-500 dark:text-gray-400">No active pipelines. Create them in Settings &rarr; Pipelines.</p>
        @else
            {{-- Pipeline Selection --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Pipeline</label>
                <select wire:model.live="selectedPipelineId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select a pipeline...</option>
                    @foreach($pipelines as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Channel preview --}}
            @if($selectedPipeline)
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Channels</label>
                @foreach($selectedPipeline->channels as $channel)
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0
                        {{ $channel->trigger === 'synthesis' ? 'bg-purple-400' : 'bg-blue-400' }}"></span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $channel->role_label }}</span>
                    @if($channel->llmProvider)
                        <span class="text-gray-400 dark:text-gray-500">{{ $channel->llmProvider->name }}</span>
                    @else
                        <span class="text-amber-500 italic">no provider</span>
                    @endif
                    <span class="text-[10px] px-1 py-0.5 rounded uppercase tracking-wide
                        {{ $channel->trigger === 'synthesis'
                            ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'
                            : 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' }}">
                        {{ $channel->trigger }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Variable Fill --}}
            @if(count($variables) > 0)
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Variables</label>
                    @foreach($variables as $var)
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                                {{ $var }}
                                @if(!empty($variableMetadata[$var]['description']))
                                    <span class="text-gray-400"> &mdash; {{ $variableMetadata[$var]['description'] }}</span>
                                @endif
                            </label>
                            <input wire:model="variableValues.{{ $var }}" type="text"
                                   placeholder="{{ $variableMetadata[$var]['default'] ?? $var }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Run Button --}}
            <button wire:click="run"
                    wire:loading.attr="disabled"
                    @if(!$selectedPipelineId) disabled @endif
                    class="w-full px-3 py-2 text-xs font-medium bg-purple-600 text-white rounded-md hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="run">Run Pipeline</span>
                <span wire:loading wire:target="run">Running pipeline...</span>
            </button>
        @endif
    </div>
    @endif
</div>
