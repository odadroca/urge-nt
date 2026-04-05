<div>
    @if($showPanel && $currentVersionId)
    <div class="border-t border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Run with LLM</h4>
            <button wire:click="$set('showPanel', false)" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
        </div>

        {{-- Provider Selection --}}
        @if($providers->isEmpty())
            <p class="text-xs text-gray-500 dark:text-gray-400">No active LLM providers. Configure them in Settings.</p>
        @else
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Providers</label>
                @foreach($providers as $provider)
                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="selectedProviderIds" value="{{ $provider->id }}"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        {{ $provider->name }} <span class="text-gray-400 dark:text-gray-500">({{ $provider->model }})</span>
                    </label>
                @endforeach
            </div>

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
                    @if(empty($selectedProviderIds)) disabled @endif
                    class="w-full px-3 py-2 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    @keydown.window.ctrl.enter.prevent="$wire.run()">
                <span wire:loading.remove wire:target="run">Run</span>
                <span wire:loading wire:target="run">Running...</span>
            </button>
        @endif
    </div>
    @endif
</div>
