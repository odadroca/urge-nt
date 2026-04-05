<div>
    {{-- Delete Confirmation (admin only) --}}
    @if(auth()->user()->isAdmin())
    @if($deleteConfirmId)
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
            <p class="text-sm text-red-700 dark:text-red-400">Delete this LLM provider? Results linked to it will keep their data.</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button wire:click="delete" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    @endif
    @endif

    {{-- Test Connection Result (admin only) --}}
    @if(auth()->user()->isAdmin())
    @if($testingId && $testResult)
        <div class="mb-4 p-3 rounded-lg border text-sm {{ $testStatus === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-400' }}">
            {{ $testResult }}
            <button wire:click="$set('testingId', null)" class="ml-2 text-xs opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif
    @endif

    {{-- Create/Edit Form (admin only) --}}
    @if(auth()->user()->isAdmin())
    @if($showForm)
        <form wire:submit="{{ $editingId ? 'update' : 'create' }}" class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                    <input wire:model="name" type="text" placeholder="e.g. OpenAI GPT-4o"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Driver</label>
                    <select wire:model.live="driver"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="mistral">Mistral</option>
                        <option value="gemini">Gemini</option>
                        <option value="ollama">Ollama</option>
                        <option value="openrouter">OpenRouter</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Model</label>
                    <input wire:model="model" type="text" placeholder="e.g. gpt-4o-mini"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('model') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @if($driver !== 'ollama')
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">API Key {{ $editingId ? '(leave blank to keep current)' : '' }}</label>
                    <input wire:model="apiKey" type="password" placeholder="{{ $editingId ? '••••••••' : 'sk-...' }}"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('apiKey') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                @endif
            </div>

            @if($driver === 'openai' || $driver === 'ollama')
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Endpoint {{ $driver === 'ollama' ? '(default: http://localhost:11434)' : '(optional, for custom/proxy endpoints)' }}
                </label>
                <input wire:model="endpoint" type="text" placeholder="{{ $driver === 'ollama' ? 'http://localhost:11434' : 'https://api.openai.com' }}"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            @endif

            <div class="flex items-center gap-2">
                <input wire:model="isActive" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                <label class="text-sm text-gray-700 dark:text-gray-300">Active</label>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                    {{ $editingId ? 'Update' : 'Create' }}
                </button>
            </div>
        </form>
    @else
        <button wire:click="$set('showForm', true)"
                class="mb-4 px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New Provider
        </button>
    @endif
    @endif

    {{-- Providers List --}}
    @if($providers->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No LLM providers configured.{{ auth()->user()->isAdmin() ? ' Add one to enable AI features.' : '' }}</p>
    @else
        <div class="space-y-2">
            @foreach($providers as $provider)
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $provider->name }}</span>
                            <span class="px-1.5 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">{{ $provider->driver }}</span>
                            <span class="px-1.5 py-0.5 text-xs rounded {{ $provider->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                {{ $provider->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $provider->model }}</span>
                            @if($provider->endpoint)
                                <span>{{ $provider->endpoint }}</span>
                            @endif
                        </div>
                    </div>
                    @if(auth()->user()->isAdmin())
                    <div class="flex items-center gap-2">
                        <button wire:click="testConnection({{ $provider->id }})"
                                wire:loading.attr="disabled"
                                class="px-2 py-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">
                            <span wire:loading.remove wire:target="testConnection({{ $provider->id }})">Test</span>
                            <span wire:loading wire:target="testConnection({{ $provider->id }})">Testing...</span>
                        </button>
                        <button wire:click="toggleActive({{ $provider->id }})"
                                class="px-2 py-1 text-xs {{ $provider->is_active ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $provider->is_active ? 'Disable' : 'Enable' }}
                        </button>
                        <button wire:click="edit({{ $provider->id }})"
                                class="px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $provider->id }})"
                                class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                            Delete
                        </button>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
