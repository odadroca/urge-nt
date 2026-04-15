<div class="space-y-6">
    {{-- Toggles --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Evaluation</h3>
        <label class="flex items-center gap-3">
            <input type="checkbox" wire:model.live="enabled" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
            <span class="text-sm text-gray-700 dark:text-gray-300">Enable evaluation</span>
        </label>
        <label class="flex items-center gap-3">
            <input type="checkbox" wire:model.live="autoEvaluate" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
            <span class="text-sm text-gray-700 dark:text-gray-300">Auto-evaluate after run_prompt</span>
        </label>
    </div>

    {{-- Source --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Evaluation Source</h3>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Evaluation Prompt</label>
            <select wire:model="promptSlug" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm">
                @foreach($evalPrompts as $p)
                    <option value="{{ $p->slug }}">{{ $p->name }} ({{ $p->slug }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Default Evaluator Provider</label>
            <select wire:model="defaultProviderId" class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm">
                <option value="">— Select provider —</option>
                @foreach($providers as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->model }})</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Dimensions --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Dimensions</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 dark:text-gray-400">
                    <th class="text-left py-1 w-8">On</th>
                    <th class="text-left py-1">Name</th>
                    <th class="text-left py-1">Description</th>
                    <th class="text-left py-1 w-20">Weight</th>
                    <th class="w-8"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($dimensions as $i => $dim)
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="py-2">
                        <input type="checkbox" wire:click="toggleDimension({{ $i }})" @checked($dim['enabled'])
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
                    </td>
                    <td class="py-2 text-gray-700 dark:text-gray-300">{{ $dim['name'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400 text-xs">{{ $dim['description'] }}</td>
                    <td class="py-2">
                        <input type="number" wire:change="updateWeight({{ $i }}, $event.target.value)"
                            value="{{ $dim['weight'] }}" step="0.1" min="0" max="5"
                            class="w-16 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs">
                    </td>
                    <td class="py-2">
                        @if(!($dim['builtin'] ?? true))
                        <button wire:click="removeDimension({{ $i }})" class="text-red-400 hover:text-red-300 text-xs">&times;</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex items-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
            <input wire:model="newDimensionName" placeholder="Dimension name" class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs px-2 py-1">
            <input wire:model="newDimensionDescription" placeholder="Description" class="flex-1 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-xs px-2 py-1">
            <button wire:click="addDimension" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded">+ Add</button>
        </div>
    </div>

    <button wire:click="save" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-6 py-2 rounded-lg">
        Save Settings
    </button>
</div>
