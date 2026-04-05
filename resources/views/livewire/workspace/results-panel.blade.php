<div class="p-3" x-data="{ compareIds: [], showCompare: false, readingResult: null }">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Results</h3>
            @if($results->count() > 1)
            <span x-show="compareIds.length === 0" x-cloak class="text-[10px] text-gray-400 dark:text-gray-500 italic">check to compare</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if($results->count() > 0)
            <button wire:click="exportAllResults" wire:loading.attr="disabled"
                    class="text-xs text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 disabled:opacity-50" title="Export all as ZIP">
                <span wire:loading.remove wire:target="exportAllResults">Export All</span>
                <span wire:loading wire:target="exportAllResults">Exporting...</span>
            </button>
            @endif
            <select wire:model.live="sortBy"
                    class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1 pr-6">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="rating_desc">Top rated</option>
                <option value="tokens_desc">Most tokens</option>
                <option value="duration_asc">Fastest</option>
            </select>
            <label class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <input type="checkbox" wire:model.live="showAllVersions" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
                All versions
            </label>
        </div>
    </div>

    {{-- Compare bar --}}
    @if($results->count() > 1)
    <div x-show="compareIds.length > 0" x-cloak
         class="mb-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-md px-3 py-2">
        <div class="flex items-center justify-between">
            <span class="text-xs text-indigo-700 dark:text-indigo-300 font-medium">
                <span x-text="compareIds.length"></span> selected
            </span>
            <div class="flex items-center gap-2">
                <button @click="compareIds = []" class="text-xs text-indigo-500 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">Clear</button>
                <button @click="showCompare = true" x-show="compareIds.length >= 2"
                        class="px-2 py-0.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white rounded hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium transition">
                    Compare
                </button>
            </div>
        </div>
    </div>
    @endif

    <div class="space-y-3">
        @forelse($groupedResults as $group)
            @if($group['type'] === 'pipeline')
                {{-- Pipeline run group --}}
                @php $pipelineData = $group['data']; @endphp
                <div class="rounded-lg border-2 border-purple-200 dark:border-purple-800 overflow-hidden" x-data="{ pipelineOpen: true }">
                    {{-- Pipeline header --}}
                    <div class="flex items-center justify-between px-3 py-2 bg-purple-50 dark:bg-purple-900/20">
                        <button @click="pipelineOpen = !pipelineOpen" class="flex items-center gap-2 hover:bg-purple-100 dark:hover:bg-purple-900/30 rounded px-1 -ml-1 transition">
                            <svg class="w-3 h-3 text-purple-500 dark:text-purple-400 transition-transform" :class="pipelineOpen ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs font-semibold text-purple-700 dark:text-purple-300">{{ $pipelineData['template']->name ?? 'Pipeline Run' }}</span>
                            <span class="text-[10px] text-purple-500 dark:text-purple-400">{{ $pipelineData['results']->count() }} channels</span>
                        </button>
                        <div class="flex items-center gap-2">
                            {{-- Collect All --}}
                            <div x-data="{ showRunCollPicker: false }" class="relative" @click.stop>
                                <button @click="showRunCollPicker = !showRunCollPicker"
                                        class="px-2 py-0.5 text-[10px] font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 border border-purple-300 dark:border-purple-700 rounded hover:bg-purple-100 dark:hover:bg-purple-800/30 transition">
                                    Collect All
                                </button>
                                <div x-show="showRunCollPicker" x-cloak @click.outside="showRunCollPicker = false"
                                     class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-40 overflow-y-auto">
                                    @forelse($collections as $coll)
                                    <button wire:click="collectPipelineRun('{{ $pipelineData['run_id'] }}', {{ $coll->id }})"
                                            @click="showRunCollPicker = false"
                                            class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                        {{ $coll->title }}
                                    </button>
                                    @empty
                                    <p class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 italic">No collections yet</p>
                                    @endforelse
                                </div>
                            </div>
                            <span class="text-[10px] text-purple-400 dark:text-purple-500">{{ $pipelineData['first_at']->diffForHumans() }}</span>
                        </div>
                    </div>

                    {{-- Pipeline results --}}
                    <div x-show="pipelineOpen" x-transition class="divide-y divide-purple-100 dark:divide-purple-800/50">
                        @foreach($pipelineData['results'] as $result)
                        <div class="bg-gray-50 dark:bg-gray-900 p-3" wire:key="result-{{ $result->id }}"
                             :class="compareIds.includes('{{ $result->id }}') ? 'ring-2 ring-inset ring-indigo-300 dark:ring-indigo-600' : ''">
                            @include('livewire.workspace.partials.result-card', ['result' => $result, 'results' => $results, 'collections' => $collections])
                        </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Standalone result --}}
                @php $result = $group['data']; @endphp
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-3" wire:key="result-{{ $result->id }}"
                     :class="compareIds.includes('{{ $result->id }}') ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : ''">
                    @include('livewire.workspace.partials.result-card', ['result' => $result, 'results' => $results, 'collections' => $collections])
                </div>
            @endif
        @empty
        <p class="text-xs text-gray-400 dark:text-gray-500 italic text-center py-4">
            No results yet. Paste one below or run with an LLM.
        </p>
        @endforelse
    </div>

    {{-- Compare modal --}}
    @if($results->count() > 1)
    @php
        $resultData = $results->mapWithKeys(function ($r) {
            return [(string) $r->id => [
                'id' => $r->id,
                'provider' => $r->provider_name ?: 'Manual',
                'model' => $r->model_name,
                'text' => $r->response_text,
                'rating' => $r->rating,
                'starred' => $r->starred,
                'duration_ms' => $r->duration_ms,
                'input_tokens' => $r->input_tokens,
                'output_tokens' => $r->output_tokens,
                'version' => $r->promptVersion->version_number ?? null,
                'notes' => $r->notes,
                'created_at' => $r->created_at->diffForHumans(),
            ]];
        });
    @endphp
    <div x-show="showCompare" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="showCompare = false"
         x-data="{ allResults: {{ $resultData->toJson() }} }">
        <div class="fixed inset-0 bg-black/50" @click="showCompare = false"></div>
        <div class="relative min-h-screen flex items-start justify-center p-4 pt-8">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-7xl overflow-hidden" @click.stop>
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 text-lg">Compare Results</h3>
                        {{-- AI Summarize --}}
                        <div x-show="compareIds.length === 2" x-data="{ showAiPicker: false }" class="relative">
                            <button @click="showAiPicker = !showAiPicker" wire:loading.attr="disabled" wire:target="aiSummarizeDifferences"
                                    class="px-2 py-1 text-xs text-purple-600 dark:text-purple-400 border border-purple-300 dark:border-purple-700 rounded-md hover:bg-purple-50 dark:hover:bg-purple-900/20 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="aiSummarizeDifferences">AI Summarize</span>
                                <span wire:loading wire:target="aiSummarizeDifferences">Analyzing...</span>
                            </button>
                            <div x-show="showAiPicker" x-cloak @click.outside="showAiPicker = false"
                                 class="absolute left-0 top-full mt-1 w-52 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-30 py-1">
                                <p class="px-3 py-1 text-xs text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700">Select provider</p>
                                @foreach(\App\Models\LlmProvider::where('is_active', true)->get() as $prov)
                                <button @click="$wire.aiSummarizeDifferences(compareIds[0], compareIds[1], {{ $prov->id }}); showAiPicker = false"
                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                    {{ $prov->name }} <span class="text-gray-400 dark:text-gray-500">({{ $prov->model }})</span>
                                </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <button @click="showCompare = false" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close comparison">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Side-by-side --}}
                <div class="p-4 sm:p-6 overflow-x-auto">
                    <div class="flex flex-col sm:flex-row gap-4" :style="window.innerWidth >= 640 ? 'min-width: ' + (compareIds.length * 320) + 'px' : ''">
                        <template x-for="rid in compareIds" :key="rid">
                            <div class="flex-1 min-w-[300px] border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                {{-- Column header --}}
                                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm" x-text="allResults[rid]?.provider"></span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500 font-mono ml-1" x-text="allResults[rid]?.model"></span>
                                        </div>
                                        <template x-if="allResults[rid]?.rating">
                                            <span class="text-yellow-500 text-sm" x-text="'★'.repeat(allResults[rid].rating) + '☆'.repeat(5 - allResults[rid].rating)"></span>
                                        </template>
                                    </div>
                                    <div class="flex gap-3 text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                        <template x-if="allResults[rid]?.duration_ms">
                                            <span x-text="(allResults[rid].duration_ms / 1000).toFixed(2) + 's'"></span>
                                        </template>
                                        <template x-if="allResults[rid]?.input_tokens || allResults[rid]?.output_tokens">
                                            <span x-text="(allResults[rid].input_tokens || '?') + ' in / ' + (allResults[rid].output_tokens || '?') + ' out'"></span>
                                        </template>
                                        <template x-if="allResults[rid]?.version">
                                            <span x-text="'v' + allResults[rid].version"></span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Response body --}}
                                <div class="p-4 max-h-[60vh] overflow-auto">
                                    <pre class="text-sm font-mono text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words leading-relaxed" x-text="allResults[rid]?.text"></pre>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- AI Summary --}}
                    @if($aiSummary)
                    <div class="mt-4 bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
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

    {{-- Reader modal --}}
    <div x-show="readingResult" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="if (readingResult && !showCompare) readingResult = null">
        <div class="fixed inset-0 bg-black/50" @click="readingResult = null"></div>
        <div class="relative min-h-screen flex items-start justify-center p-4 pt-8">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl" @click.stop>
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="readingResult?.provider"></span>
                        <span class="text-sm text-gray-400 dark:text-gray-500" x-text="readingResult?.model"></span>
                        <template x-if="readingResult?.role_label">
                            <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300" x-text="readingResult.role_label"></span>
                        </template>
                    </div>
                    <button @click="readingResult = null" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close reader">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                {{-- Body --}}
                <div class="p-6 max-h-[80vh] overflow-y-auto">
                    <pre class="text-sm whitespace-pre-wrap break-words leading-relaxed font-mono text-gray-800 dark:text-gray-200" x-text="readingResult?.text"></pre>
                </div>
                {{-- Footer meta --}}
                <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                    <div class="flex gap-3">
                        <template x-if="readingResult?.duration_ms">
                            <span x-text="(readingResult.duration_ms / 1000).toFixed(2) + 's'"></span>
                        </template>
                        <template x-if="readingResult?.input_tokens || readingResult?.output_tokens">
                            <span x-text="(readingResult.input_tokens || '?') + ' in / ' + (readingResult.output_tokens || '?') + ' out'"></span>
                        </template>
                    </div>
                    <span x-text="readingResult?.created_at"></span>
                </div>
            </div>
        </div>
    </div>
</div>
