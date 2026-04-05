<div class="border-t border-gray-200 dark:border-gray-700 p-3">
    {{-- Upload trigger --}}
    <div class="flex items-center gap-2">
        <label class="flex-1 cursor-pointer">
            <span class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-md hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Import Results (.md)
            </span>
            <input type="file" wire:model="files" accept=".md,.txt" multiple class="hidden">
        </label>
    </div>

    @error('files') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror

    {{-- Preview modal --}}
    @if($showPreview)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data @keydown.escape.window="$wire.cancelImport()">
        <div class="fixed inset-0 bg-black/50" wire:click="cancelImport"></div>
        <div class="relative min-h-screen flex items-start justify-center p-4 pt-16">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl overflow-hidden" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Import Preview ({{ count($previews) }} file{{ count($previews) > 1 ? 's' : '' }})</h3>
                    <button wire:click="cancelImport" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6 max-h-[60vh] overflow-auto space-y-4">
                    @foreach($previews as $preview)
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-md p-3 border border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $preview['filename'] }}</p>
                        @if(!empty($preview['meta']))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($preview['meta'] as $key => $value)
                            <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded">{{ $key }}: {{ $value }}</span>
                            @endforeach
                        </div>
                        @endif
                        <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-pre-wrap">{{ $preview['body_preview'] }}{{ strlen($preview['body_preview']) >= 200 ? '...' : '' }}</p>
                    </div>
                    @endforeach
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                    <button wire:click="cancelImport"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        Cancel
                    </button>
                    <button wire:click="confirmImport"
                            class="px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium transition">
                        Import {{ count($previews) }} Result{{ count($previews) > 1 ? 's' : '' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
