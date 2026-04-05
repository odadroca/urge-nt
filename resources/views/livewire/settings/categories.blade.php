<div>
    {{-- Delete Confirmation (editors+ only) --}}
    @if(auth()->user()->isEditor())
    @if($deleteConfirmId)
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
            <p class="text-sm text-red-700 dark:text-red-400">Delete this category? Prompts using it will become uncategorized.</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400">Cancel</button>
                <button wire:click="delete" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    @endif
    @endif

    {{-- Create/Edit Form (editors+ only) --}}
    @if(auth()->user()->isEditor())
    @if($showForm)
        <form wire:submit="{{ $editingId ? 'update' : 'create' }}" class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                <input wire:model="name" type="text" placeholder="e.g. Marketing"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($availableColors as $c)
                        <button type="button" wire:click="$set('color', '{{ $c }}')"
                                class="w-7 h-7 rounded-full border-2 transition {{ $color === $c ? 'border-gray-900 dark:border-gray-100 scale-110' : 'border-transparent hover:border-gray-400' }}"
                                style="background-color: {{ $colorHex[$c] ?? '#6b7280' }}"
                                title="{{ $c }}">
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                    {{ $editingId ? 'Update' : 'Create' }}
                </button>
            </div>
        </form>
    @else
        <button wire:click="$set('showForm', true)"
                class="mb-4 px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New Category
        </button>
    @endif
    @endif

    {{-- Categories List --}}
    @if($categories->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No categories yet.</p>
    @else
        <div class="space-y-2">
            @foreach($categories as $category)
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full" style="background-color: {{ $colorHex[$category->color] ?? '#6b7280' }}"></span>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $category->prompts_count }} prompts</span>
                    </div>
                    @if(auth()->user()->isEditor())
                    <div class="flex items-center gap-2">
                        <button wire:click="edit({{ $category->id }})"
                                class="px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Edit</button>
                        <button wire:click="confirmDelete({{ $category->id }})"
                                class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">Delete</button>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
