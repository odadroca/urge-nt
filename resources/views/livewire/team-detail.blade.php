<div class="p-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('teams') }}" wire:navigate class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $team->name }}</h1>
        </div>
        @if(! $this->isTeamOwner)
        <button wire:click="leaveTeam" wire:confirm="Leave this team?"
                class="px-3 py-1.5 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
            Leave Team
        </button>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Members --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Members ({{ $members->count() }})</h2>
                </div>

                {{-- Invite form (owners only) --}}
                @if($this->isTeamOwner)
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <form wire:submit="inviteMember" class="flex gap-2">
                        <input wire:model="inviteQuery" type="text"
                               class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="Add member by email or name...">
                        <button type="submit" class="px-3 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition whitespace-nowrap">
                            Add
                        </button>
                    </form>
                </div>
                @endif

                {{-- Members list --}}
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($members as $member)
                    <li class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm font-medium shrink-0">
                                {{ strtoupper(substr($member->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ $member->name }}
                                    @if($member->id === auth()->id())
                                        <span class="text-xs text-gray-400 dark:text-gray-500">(you)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $member->email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium
                                {{ $member->pivot->role === 'owner'
                                    ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                {{ $member->pivot->role }}
                            </span>
                            @if($this->isTeamOwner && $member->id !== auth()->id())
                            <button wire:click="removeMember({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from the team?"
                                    class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Shared Prompts --}}
            @if($sharedPrompts->count() > 0)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Shared Prompts ({{ $sharedPrompts->count() }})</h2>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($sharedPrompts as $prompt)
                    <li>
                        <a href="{{ $prompt->workspaceUrl() }}" wire:navigate
                           class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $prompt->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    by {{ $prompt->creator->name }} &middot; {{ $prompt->updated_at->diffForHumans() }}
                                </p>
                            </div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 shrink-0 ml-2">
                                {{ $prompt->type }}
                            </span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        {{-- Sidebar: Team Info & Danger Zone --}}
        <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Team Info</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400 text-xs">Created</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $team->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400 text-xs">Members</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $members->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400 text-xs">Shared Prompts</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $sharedPrompts->count() }}</dd>
                    </div>
                </dl>
            </div>

            @if($this->isTeamOwner)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-red-200 dark:border-red-900/30 p-4">
                <h3 class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-3">Danger Zone</h3>
                @if($confirmingDelete)
                    <p class="text-xs text-red-600 dark:text-red-400 mb-3">This will permanently delete the team, remove all members, and unshare all prompts.</p>
                    <div class="flex gap-2">
                        <button wire:click="cancelDelete" class="flex-1 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Cancel
                        </button>
                        <button wire:click="deleteTeam" class="flex-1 px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                            Confirm Delete
                        </button>
                    </div>
                @else
                    <button wire:click="confirmDelete" class="w-full px-3 py-1.5 text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition border border-red-200 dark:border-red-900/30">
                        Delete Team
                    </button>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
