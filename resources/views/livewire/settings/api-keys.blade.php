<div>
    {{-- Generated Key Modal --}}
    @if($generatedKey)
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <p class="text-sm font-semibold text-green-800 dark:text-green-300 mb-1">API Key Created Successfully</p>
            <p class="text-xs text-green-700 dark:text-green-400 mb-2">Copy this key now — it will not be shown again.</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 p-2 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-700 rounded text-xs font-mono text-gray-900 dark:text-gray-100 break-all select-all">{{ $generatedKey }}</code>
                <button x-data @click="navigator.clipboard.writeText(@js($generatedKey)); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 1500)"
                        class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 transition">Copy</button>
                <button wire:click="dismissKey" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition">Done</button>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation --}}
    @if($deleteConfirmId)
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
            <p class="text-sm text-red-700 dark:text-red-400">Delete this API key? This cannot be undone.</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button wire:click="delete" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    @endif

    {{-- Create Form --}}
    @if($showCreateForm)
        <form wire:submit="create" class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
            <div>
                <input wire:model="newKeyName" type="text" placeholder="Key name (e.g. Claude Desktop)"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('newKeyName') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Scope to prompts (optional)</label>
                <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">
                    @foreach($prompts as $prompt)
                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="selectedPromptIds" value="{{ $prompt->id }}"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            {{ $prompt->name }} <span class="text-gray-400 dark:text-gray-500">({{ $prompt->slug }})</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for access to all prompts.</p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$toggle('showCreateForm')" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">Generate Key</button>
            </div>
        </form>
    @else
        <button wire:click="$toggle('showCreateForm')"
                class="mb-4 px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New API Key
        </button>
    @endif

    {{-- Keys List --}}
    @if($apiKeys->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No API keys yet. Create one to get started.</p>
    @else
        <div class="space-y-2">
            @foreach($apiKeys as $key)
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $key->name }}</span>
                            <span class="px-1.5 py-0.5 text-xs rounded {{ $key->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                {{ $key->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $key->key_preview }}...</span>
                            <button x-data @click="navigator.clipboard.writeText(@js($key->key_preview . '...')); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy ID', 1500)"
                                    class="text-indigo-500 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">Copy ID</button>
                            @if($key->last_used_at)
                                <span>Last used: {{ $key->last_used_at->diffForHumans() }}</span>
                            @else
                                <span>Never used</span>
                            @endif
                            @if($key->prompts->isNotEmpty())
                                <span>Scoped to {{ $key->prompts->count() }} prompt(s)</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleActive({{ $key->id }})"
                                class="px-2 py-1 text-xs {{ $key->is_active ? 'text-amber-600 dark:text-amber-400 hover:text-amber-700' : 'text-green-600 dark:text-green-400 hover:text-green-700' }}">
                            {{ $key->is_active ? 'Disable' : 'Enable' }}
                        </button>
                        <button wire:click="confirmDelete({{ $key->id }})"
                                class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                            Delete
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
