<div class="p-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Teams</h1>
        <button wire:click="$toggle('showCreateForm')"
                class="px-3 py-1.5 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition whitespace-nowrap">
            + New Team
        </button>
    </div>

    {{-- Create form --}}
    @if($showCreateForm)
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <form wire:submit="createTeam" class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team name</label>
                <input wire:model="newTeamName" type="text" autofocus
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                       placeholder="e.g. Family, Work, Project X...">
                @error('newTeamName') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                Create
            </button>
            <button type="button" wire:click="$toggle('showCreateForm')" class="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm hover:text-gray-700 dark:hover:text-gray-300">
                Cancel
            </button>
        </form>
    </div>
    @endif

    {{-- Teams grid --}}
    @if($teams->count() > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($teams as $team)
        <a href="{{ route('team.detail', $team) }}" wire:navigate
           class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $team->name }}</h3>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium
                    {{ $team->pivot->role === 'owner'
                        ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                    {{ $team->pivot->role }}
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    {{ $team->members_count }} {{ Str::plural('member', $team->members_count) }}
                </span>
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    {{ $team->prompts_count }} {{ Str::plural('prompt', $team->prompts_count) }}
                </span>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
        </svg>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-3">No teams yet. Create one to share prompts with others.</p>
        <button wire:click="$set('showCreateForm', true)"
                class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            Create your first team
        </button>
    </div>
    @endif
</div>
