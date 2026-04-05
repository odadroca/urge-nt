<div class="p-3" x-data="diffViewer">
    {{-- Branch switcher --}}
    @if($branches->count() > 0)
    <div class="mb-3" x-data="{ branchOpen: false }">
        <div class="flex items-center gap-1.5">
            <button @click="branchOpen = !branchOpen"
                    class="flex-1 flex items-center justify-between px-2.5 py-1.5 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span class="font-medium text-gray-700 dark:text-gray-300 truncate">{{ $branches->firstWhere('id', $currentBranchId)?->name ?? 'main' }}</span>
                </div>
                <svg class="w-3.5 h-3.5 text-gray-400 transition" :class="branchOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <button wire:click="$set('showBranchCreate', true)"
                    class="p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition"
                    title="New branch">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        </div>

        {{-- Branch dropdown --}}
        <div x-show="branchOpen" x-cloak @click.outside="branchOpen = false"
             class="mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-48 overflow-y-auto">
            @foreach($branches as $branch)
            <div class="flex items-center justify-between px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 group/branch">
                <button wire:click="switchBranch({{ $branch->id }})" @click="branchOpen = false"
                        class="flex-1 text-left text-xs truncate {{ $branch->id === $currentBranchId ? 'text-indigo-600 dark:text-indigo-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                    {{ $branch->name }}
                    <span class="text-gray-400 dark:text-gray-500">({{ $branch->versions_count }})</span>
                    @if($branch->is_default)
                        <span class="text-[10px] text-green-500 dark:text-green-400 ml-1">default</span>
                    @endif
                </button>
                @if(!$branch->is_default)
                <div class="flex items-center gap-1 opacity-0 group-hover/branch:opacity-100 transition shrink-0">
                    <button wire:click="setDefaultBranch({{ $branch->id }})" wire:confirm="Set '{{ $branch->name }}' as the default branch?"
                            class="text-[10px] text-gray-400 hover:text-indigo-500" title="Set as default">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </button>
                    <button wire:click="deleteBranch({{ $branch->id }})" wire:confirm="Delete branch '{{ $branch->name }}'? Versions will be preserved."
                            class="text-[10px] text-gray-400 hover:text-red-500" title="Delete branch">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Create branch form --}}
        @if($showBranchCreate)
        <div class="mt-2 flex items-center gap-1.5">
            <input wire:model="newBranchName" wire:keydown.enter="createBranch"
                   type="text" placeholder="branch-name" maxlength="100"
                   class="flex-1 text-xs px-2 py-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 focus:border-indigo-500 dark:text-gray-200">
            <button wire:click="createBranch"
                    class="px-2 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700 transition">
                Create
            </button>
            <button wire:click="$set('showBranchCreate', false)"
                    class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                Cancel
            </button>
        </div>
        @if($errors->has('newBranchName'))
            <p class="text-xs text-red-500 mt-1">{{ $errors->first('newBranchName') }}</p>
        @endif
        @endif
    </div>
    @endif

    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Versions</h3>
        <div class="flex items-center gap-2">
            @if($versions->count() > 1)
            <span x-show="diffSelection.length === 0" x-cloak class="text-[10px] text-gray-400 dark:text-gray-500 italic">check 2 to diff</span>
            @endif
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $versions->count() }}</span>
        </div>
    </div>

    <div class="space-y-1">
        @forelse($versions as $version)
        @php
            $branchName = $branches->firstWhere('id', $currentBranchId)?->name ?? 'main';
        @endphp
        <div class="flex items-center gap-1 group">
            {{-- Diff checkbox --}}
            @if($versions->count() > 1)
            <input type="checkbox"
                   value="{{ $version->id }}"
                   x-model="diffSelection"
                   :disabled="diffSelection.length >= 2 && !diffSelection.includes('{{ $version->id }}')"
                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 w-3 h-3 disabled:opacity-30 cursor-pointer disabled:cursor-not-allowed shrink-0">
            @endif

            <button wire:click="selectVersion({{ $version->id }})"
                    class="flex-1 text-left px-2.5 py-1.5 rounded-md text-sm transition
                        {{ $currentVersionId === $version->id ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                <div class="flex items-center justify-between">
                    <span>{{ $branchName }}#{{ $version->branch_version_number ?? $version->version_number }} <span class="text-xs text-gray-400 dark:text-gray-500">(v{{ $version->version_number }})</span></span>
                    @if($prompt->pinned_version_id === $version->id)
                        <span class="text-xs text-indigo-500 dark:text-indigo-400" title="Pinned">&#x1f4cc;</span>
                    @elseif($loop->first && !$prompt->pinned_version_id)
                        <span class="w-2 h-2 rounded-full bg-green-400" title="Active (latest)"></span>
                    @endif
                </div>
                @if($version->commit_message)
                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ $version->commit_message }}</p>
                @endif
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $version->created_at->diffForHumans() }}</p>
            </button>

            {{-- Add to collection --}}
            @if($collections->count() > 0)
            <div class="relative shrink-0" x-data="{ open: false }">
                <button @click.stop="open = !open" class="p-0.5 rounded bg-gray-50 dark:bg-gray-800 text-indigo-500 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition" title="Add to collection">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-32 overflow-y-auto">
                    @foreach($collections as $coll)
                    <button wire:click="addVersionToCollection({{ $version->id }}, {{ $coll->id }})"
                            @click="open = false"
                            class="w-full text-left px-3 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                        {{ $coll->title }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @empty
        <p class="text-xs text-gray-400 dark:text-gray-500 italic">No versions yet. Write content and save.</p>
        @endforelse
    </div>

    {{-- Diff bar --}}
    @if($versions->count() > 1)
    <div x-show="diffSelection.length > 0" x-cloak class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            <span x-text="diffSelection.length"></span> selected
            <span x-show="diffSelection.length < 2" class="text-gray-400 dark:text-gray-500">&mdash; pick one more</span>
        </p>
        <div class="flex items-center gap-2">
            <button x-show="diffSelection.length === 2"
                    @click="
                        const versions = @js($versions->pluck('content', 'id')->toArray());
                        const labels = @js($versions->pluck('version_number', 'id')->toArray());
                        const id1 = diffSelection[0], id2 = diffSelection[1];
                        openDiff(versions[id1], versions[id2], 'v' + labels[id1], 'v' + labels[id2]);
                    "
                    class="px-2.5 py-1 text-xs bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition font-medium">
                Quick Diff
            </button>
            <button @click="diffSelection = []"
                    class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                Clear
            </button>
        </div>
    </div>

    {{-- Diff modal --}}
    <div x-show="showDiff" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="closeDiff()">
        <div class="fixed inset-0 bg-black/50" @click="closeDiff()"></div>
        <div class="relative min-h-screen flex items-start justify-center p-4 pt-16">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl overflow-hidden" @click.stop>
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">
                            <span x-text="oldLabel"></span> &rarr; <span x-text="newLabel"></span>
                        </h3>
                        <div class="inline-flex rounded-md shadow-sm">
                            <button @click="toggleMode('words')" type="button"
                                    class="px-2 py-0.5 text-xs rounded-l-md border transition"
                                    :class="diffMode === 'words'
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700'
                                        : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600'">
                                Words
                            </button>
                            <button @click="toggleMode('chars')" type="button"
                                    class="px-2 py-0.5 text-xs rounded-r-md border-t border-r border-b transition"
                                    :class="diffMode === 'chars'
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700'
                                        : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600'">
                                Chars
                            </button>
                        </div>
                        {{-- AI Summarize --}}
                        <div x-data="{ showAiPicker: false }" class="relative">
                            <button @click="showAiPicker = !showAiPicker" wire:loading.attr="disabled" wire:target="aiSummarizeVersionDiff"
                                    class="px-2 py-1 text-xs text-purple-600 dark:text-purple-400 border border-purple-300 dark:border-purple-700 rounded-md hover:bg-purple-50 dark:hover:bg-purple-900/20 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="aiSummarizeVersionDiff">AI Summary</span>
                                <span wire:loading wire:target="aiSummarizeVersionDiff">Analyzing...</span>
                            </button>
                            <div x-show="showAiPicker" x-cloak @click.outside="showAiPicker = false"
                                 class="absolute left-0 top-full mt-1 w-52 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-30 py-1">
                                <p class="px-3 py-1 text-xs text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700">Select provider</p>
                                @foreach(\App\Models\LlmProvider::where('is_active', true)->get() as $prov)
                                <button @click="$wire.aiSummarizeVersionDiff(diffSelection[0], diffSelection[1], {{ $prov->id }}); showAiPicker = false"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                    {{ $prov->name }} <span class="text-gray-400 dark:text-gray-500">({{ $prov->model }})</span>
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <button @click="closeDiff()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Stats --}}
                <div class="px-6 py-2 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 flex gap-4">
                    <span class="text-green-600 dark:text-green-400">+<span x-text="stats.added"></span> added</span>
                    <span class="text-red-600 dark:text-red-400">-<span x-text="stats.removed"></span> removed</span>
                </div>

                {{-- Diff content --}}
                <div class="p-6 max-h-[70vh] overflow-auto">
                    <pre class="font-mono text-sm whitespace-pre-wrap break-words leading-relaxed" x-html="unifiedHtml"></pre>

                    {{-- AI Summary --}}
                    @if($aiSummary)
                    <div x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="mt-4 bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-medium text-purple-700 dark:text-purple-300">AI Comparison Summary</h4>
                            <button wire:click="$set('aiSummary', null)" class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-sm">&times;</button>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $aiSummary }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
