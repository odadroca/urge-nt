<div class="p-6 max-w-7xl mx-auto">
    {{-- Quick-start header --}}
    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
        <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Browse</h1>
            <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full">{{ $stats['prompts'] }} prompts</span>
                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full">{{ $stats['fragments'] }} fragments</span>
                <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full">{{ $stats['results'] }} results</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($lastPrompt)
                <a href="{{ $lastPrompt->workspaceUrl() }}" wire:navigate
                   class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 truncate max-w-[200px]">
                    Continue: <span class="font-medium">{{ Str::limit($lastPrompt->name, 20) }}</span>
                </a>
            @endif
            <button wire:click="$toggle('showCreateForm')" class="px-3 py-1.5 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition whitespace-nowrap">
                + New Prompt
            </button>
        </div>
    </div>

    {{-- Inline Create Form --}}
    @if($showCreateForm)
    <div class="mb-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <form wire:submit="createPrompt" class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                <input wire:model="newPromptName" type="text" autofocus
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                       placeholder="My new prompt...">
                @error('newPromptName') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                <select wire:model="newPromptType" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="prompt">Prompt</option>
                    <option value="fragment">Fragment</option>
                </select>
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

    {{-- Onboarding (zero prompts) --}}
    @if($stats['prompts'] + $stats['fragments'] === 0)
    <div class="mb-6 bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-900/10 dark:to-gray-800 rounded-xl border border-indigo-100 dark:border-indigo-800/30 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Welcome to URGE</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">Your prompt engineering workspace. Here's how it works:</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-center">
                <div class="text-2xl mb-1">1</div>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Create Prompt</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Write your prompt template with variables</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-center">
                <div class="text-2xl mb-1">2</div>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Add Variables</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Use {{ '{' . '{' }}name{{ '}' . '}' }} syntax for dynamic content</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-center">
                <div class="text-2xl mb-1">3</div>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Run with LLM</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Test against configured providers</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 text-center">
                <div class="text-2xl mb-1">4</div>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Compare Results</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Rate, star, and compare responses</p>
            </div>
        </div>
        <button wire:click="$set('showCreateForm', true)"
                class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            Create your first prompt
        </button>
    </div>
    @endif

    {{-- Mobile scope strip (horizontal scroll) --}}
    @if($userTeams->count() > 0)
    <div class="sm:hidden flex items-center gap-2 overflow-x-auto pb-3 mb-3 -mx-1 px-1 scrollbar-hide">
        <button wire:click="switchScope('mine')"
                class="shrink-0 px-3 py-1.5 text-xs font-medium rounded-full transition {{ $browseScope === 'mine' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
            My Prompts
            <span class="ml-1 opacity-70">{{ $scopeCounts['mine'] ?? 0 }}</span>
        </button>
        @foreach($userTeams as $team)
        <button wire:click="switchScope('team:{{ $team->slug }}')"
                class="shrink-0 px-3 py-1.5 text-xs font-medium rounded-full transition {{ $browseScope === 'team:' . $team->slug ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
            {{ $team->name }}
            <span class="ml-1 opacity-70">{{ $scopeCounts['team:' . $team->slug] ?? 0 }}</span>
        </button>
        @endforeach
        <button wire:click="switchScope('all')"
                class="shrink-0 px-3 py-1.5 text-xs font-medium rounded-full transition {{ $browseScope === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
            All
            <span class="ml-1 opacity-70">{{ $scopeCounts['all'] ?? 0 }}</span>
        </button>
    </div>
    @endif

    {{-- Sidebar + Main content --}}
    <div class="flex gap-6">
        {{-- Desktop sidebar --}}
        @if($userTeams->count() > 0)
        <aside class="hidden sm:block w-48 shrink-0">
            <nav class="space-y-0.5 sticky top-20">
                <button wire:click="switchScope('mine')"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition
                            {{ $browseScope === 'mine'
                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        My Prompts
                    </span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $browseScope === 'mine' ? 'bg-indigo-100 dark:bg-indigo-800/40 text-indigo-600 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">{{ $scopeCounts['mine'] ?? 0 }}</span>
                </button>

                @if($userTeams->count() > 0)
                <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>
                <p class="px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Teams</p>
                @foreach($userTeams as $team)
                <button wire:click="switchScope('team:{{ $team->slug }}')"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition
                            {{ $browseScope === 'team:' . $team->slug
                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span class="truncate">{{ $team->name }}</span>
                    </span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $browseScope === 'team:' . $team->slug ? 'bg-indigo-100 dark:bg-indigo-800/40 text-indigo-600 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">{{ $scopeCounts['team:' . $team->slug] ?? 0 }}</span>
                </button>
                @endforeach
                @endif

                <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>

                <button wire:click="switchScope('all')"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-md transition
                            {{ $browseScope === 'all'
                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                        All Prompts
                    </span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $browseScope === 'all' ? 'bg-indigo-100 dark:bg-indigo-800/40 text-indigo-600 dark:text-indigo-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">{{ $scopeCounts['all'] ?? 0 }}</span>
                </button>
            </nav>
        </aside>
        @endif

        {{-- Main content --}}
        <div class="flex-1 min-w-0" x-data="{ selectMode: false, selectedIds: [], bulkCollPicker: false }"
             @selection-cleared.window="selectedIds = []; selectMode = false; bulkCollPicker = false">
            {{-- Tabs --}}
            <div class="flex items-center gap-4 mb-4 flex-wrap">
                <button wire:click="$set('tab', 'prompts')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md {{ $tab === 'prompts' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                    Prompts
                </button>
                <button wire:click="$set('tab', 'fragments')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md {{ $tab === 'fragments' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                    Fragments
                </button>
                <button wire:click="$set('tab', 'collections')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md {{ $tab === 'collections' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                    Collections
                </button>
                <button wire:click="$set('tab', 'starred')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md {{ $tab === 'starred' ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                    Starred
                </button>

                <div class="ml-auto flex items-center gap-2">
                    {{-- Select mode toggle (prompts/fragments tabs only) --}}
                    @if($tab === 'prompts' || $tab === 'fragments')
                    <button @click="selectMode = !selectMode; if (!selectMode) { selectedIds = []; bulkCollPicker = false; }"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md transition"
                            :class="selectMode
                                ? 'bg-indigo-600 dark:bg-indigo-500 text-white'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke-linecap="round" stroke-linejoin="round"></rect>
                            <path x-show="selectMode" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"></path>
                        </svg>
                        <span x-text="selectMode ? 'Selecting' : 'Select'"></span>
                    </button>
                    @endif

                    {{-- Category filter --}}
                    @if($tab === 'prompts' || $tab === 'fragments')
                    <select wire:model.live="categoryFilter"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                        <option value="">All categories</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @endif

                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search..."
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 w-64">
                </div>
            </div>

            {{-- Tag filter chips --}}
            @if(($tab === 'prompts' || $tab === 'fragments') && $allTags->count() > 0)
            <div class="flex flex-wrap gap-1.5 mb-4">
                @if($tagFilter)
                <button wire:click="$set('tagFilter', '')"
                        class="text-xs px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-300 dark:hover:bg-gray-600">
                    Clear filter &times;
                </button>
                @endif
                @foreach($allTags as $tag)
                <button wire:click="$set('tagFilter', '{{ $tag }}')"
                        class="text-xs px-2 py-0.5 rounded-full transition
                            {{ $tagFilter === $tag
                                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-300 dark:ring-indigo-700'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    {{ $tag }}
                </button>
                @endforeach
            </div>
            @endif

            {{-- Active filters summary --}}
            @if(($tab === 'prompts' || $tab === 'fragments') && ($search || $categoryFilter || $tagFilter))
            <div class="flex items-center gap-2 mb-4 text-xs text-gray-500 dark:text-gray-400">
                <span>Filtering by:</span>
                @if($search)<span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">"{{ $search }}"</span>@endif
                @if($categoryFilter)<span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">{{ $categories->find($categoryFilter)?->name }}</span>@endif
                @if($tagFilter)<span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">#{{ $tagFilter }}</span>@endif
                <button wire:click="$set('search', ''); $set('categoryFilter', null); $set('tagFilter', '')"
                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Clear all</button>
            </div>
            @endif

            {{-- Content by tab --}}
            @if($tab === 'collections')
                <livewire:browse.collection-list />
            @elseif($tab === 'starred')
                {{-- Starred results --}}
                @if($starredResults && $starredResults->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($starredResults as $result)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-amber-500 text-sm">&#9733;</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $result->provider_name ?: 'Manual' }}</span>
                            @if($result->model_name)
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $result->model_name }}</span>
                            @endif
                        </div>
                        @if($result->prompt)
                        <a href="{{ $result->prompt->workspaceUrl() }}" wire:navigate
                           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $result->prompt->name }} v{{ $result->promptVersion->version_number ?? '?' }}
                        </a>
                        @endif
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-3">{{ $result->response_text }}</p>
                        @if($result->rating)
                        <div class="flex items-center gap-0.5 mt-2">
                            @for($i = 1; $i <= 5; $i++)
                            <span class="text-xs {{ $result->rating >= $i ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600' }}">&#9733;</span>
                            @endfor
                        </div>
                        @endif
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $result->created_at->diffForHumans() }}</p>
                    </div>
                    @endforeach
                </div>
                <div class="mt-6">{{ $starredResults->links() }}</div>
                @else
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No starred results yet. Star your best results from the workspace to find them here.</p>
                </div>
                @endif
            @else
                {{-- Prompts/Fragments grid --}}
                @if($prompts && $prompts->count() > 0)

                {{-- Bulk selection action bar --}}
                <div x-show="selectMode && selectedIds.length > 0" x-cloak
                     class="mb-4 bg-indigo-600 dark:bg-indigo-500 text-white rounded-lg px-4 py-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium" x-text="selectedIds.length + ' selected'"></span>
                        <div class="relative">
                            <button @click="bulkCollPicker = !bulkCollPicker"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-white text-indigo-600 text-xs font-medium rounded-md hover:bg-indigo-50 transition">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Add to Collection
                            </button>
                            <div x-show="bulkCollPicker" x-cloak @click.outside="bulkCollPicker = false"
                                 class="absolute left-0 top-full mt-1 w-52 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-30 py-1 max-h-48 overflow-y-auto">
                                @forelse($collections as $coll)
                                <button @click="$wire.addPromptsToCollection(selectedIds, {{ $coll->id }}); bulkCollPicker = false"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                    {{ $coll->title }}
                                </button>
                                @empty
                                <p class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 italic">No collections yet</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="selectedIds = @js($prompts->pluck('id')->toArray())"
                                class="px-2 py-0.5 text-xs bg-white/20 rounded hover:bg-white/30 transition">Select All</button>
                        <button @click="selectedIds = []; selectMode = false; bulkCollPicker = false"
                                class="text-xs text-white/80 hover:text-white">Cancel</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 {{ $userTeams->count() > 0 ? 'xl:grid-cols-3' : 'lg:grid-cols-3' }} gap-4">
                    @foreach($prompts as $prompt)
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg border p-4 transition cursor-pointer"
                         x-data="{ showCollPicker: false }"
                         :class="selectMode && selectedIds.includes({{ $prompt->id }})
                             ? 'border-indigo-400 dark:border-indigo-500 ring-2 ring-indigo-300 dark:ring-indigo-600'
                             : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm'"
                         @click="if (selectMode) {
                             let idx = selectedIds.indexOf({{ $prompt->id }});
                             if (idx > -1) { selectedIds.splice(idx, 1); } else { selectedIds.push({{ $prompt->id }}); }
                         }">

                        {{-- Selection checkbox (select mode only) --}}
                        <div x-show="selectMode" x-cloak class="absolute top-3 right-3 z-10">
                            <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition"
                                 :class="selectedIds.includes({{ $prompt->id }})
                                     ? 'bg-indigo-600 dark:bg-indigo-500 border-indigo-600 dark:border-indigo-500'
                                     : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'">
                                <svg x-show="selectedIds.includes({{ $prompt->id }})" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>

                        <a x-show="!selectMode" href="{{ $prompt->workspaceUrl() }}" wire:navigate class="block">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $prompt->name }}</h3>
                                @if($prompt->category)
                                    <span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $prompt->category->color_hex }}15; color: {{ $prompt->category->color_hex }}">
                                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $prompt->category->color_hex }}"></span>
                                        {{ $prompt->category->name }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $prompt->latestVersion ? 'v' . $prompt->latestVersion->version_number : 'No versions' }}
                                &middot; {{ $prompt->updated_at->diffForHumans() }}
                                @if($prompt->results_count > 0)
                                &middot; <span class="text-indigo-600 dark:text-indigo-400">{{ $prompt->results_count }} results</span>
                                @endif
                            </p>
                            @if($prompt->description)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">{{ $prompt->description }}</p>
                            @endif
                            @if($prompt->tags)
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($prompt->tags as $tag)
                                        <span class="text-xs px-1.5 py-0.5 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                            {{-- Owner badge (shown when not in "mine" scope) --}}
                            @if($browseScope !== 'mine' && $prompt->creator && $prompt->created_by !== auth()->id())
                            <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700/50">
                                <span class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-[10px] font-medium text-gray-600 dark:text-gray-300 shrink-0">{{ strtoupper(substr($prompt->creator->name, 0, 1)) }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500 truncate">by {{ $prompt->creator->name }}</span>
                            </div>
                            @endif
                        </a>
                        {{-- Card content visible in select mode (non-clickable summary) --}}
                        <div x-show="selectMode" x-cloak>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $prompt->name }}</h3>
                                @if($prompt->category)
                                    <span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $prompt->category->color_hex }}15; color: {{ $prompt->category->color_hex }}">
                                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $prompt->category->color_hex }}"></span>
                                        {{ $prompt->category->name }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $prompt->latestVersion ? 'v' . $prompt->latestVersion->version_number : 'No versions' }}
                                &middot; {{ $prompt->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        {{-- Collect button (normal mode only) --}}
                        @if($prompt->latestVersion)
                        <div x-show="!selectMode" class="flex justify-end mt-2 relative">
                            <button @click.prevent.stop="showCollPicker = !showCollPicker"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded hover:bg-indigo-100 dark:hover:bg-indigo-800/30 transition">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Collect
                            </button>
                            <div x-show="showCollPicker" x-cloak @click.outside="showCollPicker = false"
                                 class="absolute bottom-full right-0 mb-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-40 overflow-y-auto">
                                @forelse($collections as $coll)
                                <button wire:click="addPromptToCollection({{ $prompt->id }}, {{ $coll->id }})"
                                        @click="showCollPicker = false"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                    {{ $coll->title }}
                                </button>
                                @empty
                                <p class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 italic">No collections yet</p>
                                @endforelse
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="mt-6">{{ $prompts->links() }}</div>
                @else
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        @if($search || $categoryFilter || $tagFilter)
                            No {{ $tab }} match your filters.
                            <button wire:click="$set('search', ''); $set('categoryFilter', null); $set('tagFilter', '')"
                                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 underline">Clear filters</button>
                        @else
                            No {{ $tab }} yet.
                        @endif
                    </p>
                </div>
                @endif
            @endif
        </div>
    </div>
</div>
