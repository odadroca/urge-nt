<div class="flex flex-col h-full" x-data="autocomplete()"
     @keydown.window.prevent.ctrl.s="$wire.saveVersion()"
     @keydown.window.prevent.meta.s="$wire.saveVersion()"
     @keydown.window.prevent.ctrl.enter="$dispatch('toggle-run-panel')"
     @keydown.window.prevent.meta.enter="$dispatch('toggle-run-panel')"
>
    {{-- Toolbar --}}
    <div class="flex items-center justify-between px-3 sm:px-4 py-2 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 gap-2"
         x-data="{ overflowOpen: false }">

        {{-- Left: Prompt name, version, unsaved indicator (always visible) --}}
        <div class="flex items-center gap-2 min-w-0 shrink">
            <h2 class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[8rem] sm:max-w-[14rem] lg:max-w-none">{{ $prompt->name }}</h2>
            @if($currentVersionId)
                <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
                    v{{ App\Models\PromptVersion::find($currentVersionId)?->version_number }}
                </span>
            @endif
            @if($isDirty)
                <span class="shrink-0 w-2 h-2 rounded-full bg-amber-500" title="Unsaved changes"></span>
            @endif
        </div>

        {{-- Right: Actions --}}
        <div class="flex items-center gap-2 shrink-0">

            {{-- === Desktop-only secondary actions (hidden on mobile) === --}}
            <div class="hidden lg:flex items-center gap-2" x-data="{ mode: @js($editorMode) }">
                {{-- Editor mode toggle --}}
                <div class="inline-flex rounded-md shadow-sm">
                    <button type="button"
                            wire:click="switchMode('text')"
                            @click="mode = 'text'"
                            class="px-2.5 py-1 text-xs font-medium rounded-l-md border transition"
                            :class="mode === 'text'
                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700'
                                : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'">
                        Text
                    </button>
                    <button type="button"
                            wire:click="switchMode('visual')"
                            @click="mode = 'visual'"
                            class="px-2.5 py-1 text-xs font-medium rounded-r-md border-t border-r border-b transition"
                            :class="mode === 'visual'
                                ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700'
                                : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'">
                        Visual
                    </button>
                </div>

                {{-- Preview toggle --}}
                <button type="button" wire:click="togglePreview"
                        class="px-2.5 py-1 text-xs font-medium rounded-md border transition"
                        :class="@js($showPreview)
                            ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700'
                            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        title="Toggle live preview">
                    Preview
                </button>

                @if($currentVersionId)
                <button wire:click="exportPrompt" wire:loading.attr="disabled"
                        class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-300 dark:border-gray-600 rounded-md hover:border-indigo-300 dark:hover:border-indigo-700 transition disabled:opacity-50"
                        title="Export as .md">
                    <span wire:loading.remove wire:target="exportPrompt">Export</span>
                    <span wire:loading wire:target="exportPrompt">Exporting...</span>
                </button>
                <button x-data @click="
                            const rendered = await $wire.getRenderedContent();
                            if (rendered) { navigator.clipboard.writeText(rendered); }
                        "
                        class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-300 dark:border-gray-600 rounded-md hover:border-indigo-300 dark:hover:border-indigo-700 transition"
                        title="Copy rendered with defaults">Copy Rendered</button>
                <button x-data @click="$dispatch('toggle-run-panel')"
                        class="px-2 py-1 text-xs text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-300 border border-green-300 dark:border-green-700 rounded-md hover:border-green-400 dark:hover:border-green-600 transition"
                        title="Run with LLM providers">Run LLM</button>
                <button x-data @click="$dispatch('toggle-template-panel')"
                        class="px-2 py-1 text-xs text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 border border-purple-300 dark:border-purple-700 rounded-md hover:border-purple-400 dark:hover:border-purple-600 transition"
                        title="Run with pipeline template">Run Template</button>
                {{-- AI Suggest Improvements --}}
                <div x-data="{ showPicker: false }" class="relative">
                    <button @click="showPicker = !showPicker" wire:loading.attr="disabled" wire:target="suggestImprovements"
                            class="px-2 py-1 text-xs text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 border border-purple-300 dark:border-purple-700 rounded-md hover:border-purple-400 dark:hover:border-purple-600 transition disabled:opacity-50"
                            title="AI suggestions for improving this prompt">
                        <span wire:loading.remove wire:target="suggestImprovements">AI Suggest</span>
                        <span wire:loading wire:target="suggestImprovements">Analyzing...</span>
                    </button>
                    <div x-show="showPicker" x-cloak @click.outside="showPicker = false"
                         class="absolute right-0 top-full mt-1 w-52 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-40 py-1">
                        <p class="px-3 py-1 text-xs text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700">Select provider</p>
                        @foreach(\App\Models\LlmProvider::where('is_active', true)->get() as $prov)
                        <button wire:click="suggestImprovements({{ $prov->id }})"
                                @click="showPicker = false"
                                class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                            {{ $prov->name }} <span class="text-gray-400 dark:text-gray-500">({{ $prov->model }})</span>
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>{{-- end desktop-only secondary actions --}}

            {{-- === Mobile overflow menu (hidden on desktop) === --}}
            <div class="relative lg:hidden" x-data="{ mobileMode: @js($editorMode) }">
                <button @click="overflowOpen = !overflowOpen" type="button"
                        class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        title="More actions">
                    {{-- Three dots (horizontal ellipsis) icon --}}
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 10a2 2 0 104 0 2 2 0 00-4 0z"/>
                    </svg>
                </button>

                <div x-show="overflowOpen" x-cloak
                     @click.outside="overflowOpen = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 top-full mt-1 w-56 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-40 py-1">

                    {{-- Editor mode --}}
                    <p class="px-3 py-1 text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 font-semibold">Editor Mode</p>
                    <button type="button"
                            wire:click="switchMode('text')"
                            @click="mobileMode = 'text'; overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs transition"
                            :class="mobileMode === 'text'
                                ? 'text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 font-medium'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                        Text Mode
                    </button>
                    <button type="button"
                            wire:click="switchMode('visual')"
                            @click="mobileMode = 'visual'; overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs transition"
                            :class="mobileMode === 'visual'
                                ? 'text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 font-medium'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                        Visual Mode
                    </button>

                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                    {{-- Preview toggle --}}
                    <button type="button" wire:click="togglePreview" @click="overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs transition"
                            :class="@js($showPreview)
                                ? 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 font-medium'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                        {{ $showPreview ? 'Hide Preview' : 'Show Preview' }}
                    </button>

                    @if($currentVersionId)
                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                    {{-- Export --}}
                    <button wire:click="exportPrompt" wire:loading.attr="disabled" @click="overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="exportPrompt">Export Prompt</span>
                        <span wire:loading wire:target="exportPrompt">Exporting...</span>
                    </button>

                    {{-- Copy Rendered --}}
                    <button @click="
                                const rendered = await $wire.getRenderedContent();
                                if (rendered) { navigator.clipboard.writeText(rendered); }
                                overflowOpen = false;
                            "
                            class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Copy Rendered
                    </button>

                    {{-- Run LLM --}}
                    <button @click="$dispatch('toggle-run-panel'); overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-green-600 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Run LLM
                    </button>

                    {{-- Run Template --}}
                    <button @click="$dispatch('toggle-template-panel'); overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Run Template
                    </button>

                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                    {{-- AI Suggest -- with inline provider sub-menu --}}
                    <div x-data="{ showMobilePicker: false }">
                        <button @click.stop="showMobilePicker = !showMobilePicker" wire:loading.attr="disabled" wire:target="suggestImprovements"
                                class="w-full text-left px-3 py-1.5 text-xs text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-50 flex items-center justify-between">
                            <span>
                                <span wire:loading.remove wire:target="suggestImprovements">AI Suggest</span>
                                <span wire:loading wire:target="suggestImprovements">Analyzing...</span>
                            </span>
                            <svg class="w-3 h-3 transition-transform" :class="showMobilePicker ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <div x-show="showMobilePicker" x-cloak class="bg-gray-50 dark:bg-gray-900/50">
                            <p class="px-4 py-1 text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 font-semibold">Select provider</p>
                            @foreach(\App\Models\LlmProvider::where('is_active', true)->get() as $prov)
                            <button wire:click="suggestImprovements({{ $prov->id }})"
                                    @click="overflowOpen = false"
                                    class="w-full text-left px-4 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                {{ $prov->name }} <span class="text-gray-400 dark:text-gray-500">({{ $prov->model }})</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>{{-- end mobile overflow menu --}}

            {{-- === Always visible: commit input + save button === --}}
            <input wire:model="commitMessage" type="text" placeholder="Commit msg"
                   class="w-24 sm:w-32 lg:w-48 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1">
            <button wire:click="saveVersion" wire:loading.attr="disabled"
                    class="px-3 py-1.5 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 disabled:opacity-50 transition whitespace-nowrap">
                <span wire:loading.remove wire:target="saveVersion">Save Version</span>
                <span wire:loading wire:target="saveVersion">Saving...</span>
            </button>
        </div>
    </div>

    {{-- Editor area --}}
    <div class="flex-1 overflow-hidden relative flex flex-col">
        <div class="flex-1 min-h-0 overflow-hidden relative">
        @if($editorMode === 'text')
        {{-- Text editor with autocomplete --}}
        <textarea wire:model.live.debounce.300ms="content"
                  x-ref="editorTextarea"
                  @input="handleInput($event); positionDropdown($event.target)"
                  @keydown="handleKeydown($event)"
                  class="w-full h-full resize-none border-0 focus:ring-0 p-4 font-mono text-sm text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900"
                  placeholder="Write your prompt here...&#10;&#10;Use {'{'}{'{'} variable {'}'}{'}'}  for variables&#10;Use {'{'}{'{'}> slug {'}'}{'}'}  to include fragments"
                  spellcheck="false"></textarea>

        {{-- Autocomplete dropdown --}}
        <div x-ref="autocompleteDropdown"
             x-show="showDropdown" x-cloak
             class="absolute z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-auto min-w-[200px]"
             @click.outside="dismiss()">
            <template x-for="(item, idx) in filteredItems" :key="item.value">
                <button type="button"
                        @click="selectedIndex = idx; insertSelected()"
                        @mouseenter="selectedIndex = idx"
                        class="w-full text-left px-3 py-1.5 text-sm flex items-center gap-2 transition-colors"
                        :class="idx === selectedIndex
                            ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'">
                    <span x-show="triggerType === 'variable'" class="text-blue-500 dark:text-blue-400 text-xs font-mono">{<span>{</span></span>
                    <span x-show="triggerType === 'fragment'" class="text-purple-500 dark:text-purple-400 text-xs font-mono">{<span>{</span>&gt;</span>
                    <span>
                        <span class="font-mono" x-text="item.value"></span>
                        <span x-show="item.label !== item.value" class="text-xs text-gray-400 dark:text-gray-500 ml-1" x-text="item.label"></span>
                    </span>
                </button>
            </template>
            <div x-show="filteredItems.length === 0" class="px-3 py-2 text-xs text-gray-400 dark:text-gray-500 italic">No matches</div>
        </div>
        @else
        {{-- Visual composer --}}
        <div class="h-full overflow-auto p-4 bg-gray-50 dark:bg-gray-900" x-data="composer()" x-init="parseContent(@js($content))">
            <div class="space-y-2 mb-4" x-ref="composerBlocks" x-init="initSortable($refs.composerBlocks)">
                <template x-for="(block, index) in blocks" :key="block.id">
                    <div class="flex items-start gap-2 group">
                        <span class="composer-handle cursor-grab text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 mt-2 text-sm select-none">&#9776;</span>

                        <div class="flex-1" x-show="block.type === 'text'">
                            <textarea x-model="block.value"
                                      @input="$wire.set('content', serialize())"
                                      rows="2"
                                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm font-mono resize-y focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Text block..."></textarea>
                        </div>

                        <div class="flex-1" x-show="block.type === 'variable'">
                            <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                                <span class="text-blue-700 dark:text-blue-400 font-mono text-sm">{<span>{</span></span>
                                <input x-model="block.value"
                                       @input="$wire.set('content', serialize())"
                                       class="flex-1 bg-transparent border-0 text-sm font-mono text-blue-700 dark:text-blue-300 focus:ring-0 p-0"
                                       placeholder="variable_name">
                                <span class="text-blue-700 dark:text-blue-400 font-mono text-sm">}<span>}</span></span>
                            </div>
                        </div>

                        <div class="flex-1" x-show="block.type === 'include'">
                            <div class="flex items-center gap-2 px-3 py-2 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-md">
                                <span class="text-purple-700 dark:text-purple-400 font-mono text-sm">{<span>{</span>&gt;</span>
                                <input x-model="block.value"
                                       @input="$wire.set('content', serialize())"
                                       class="flex-1 bg-transparent border-0 text-sm font-mono text-purple-700 dark:text-purple-300 focus:ring-0 p-0"
                                       placeholder="fragment-slug">
                                <span class="text-purple-700 dark:text-purple-400 font-mono text-sm">}<span>}</span></span>
                            </div>
                        </div>

                        <button @click="removeBlock(index); $wire.set('content', serialize())"
                                class="text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition mt-2 text-sm">
                            &times;
                        </button>
                    </div>
                </template>
            </div>

            {{-- Add block buttons --}}
            <div class="flex items-center gap-2">
                <button @click="addTextBlock()" type="button"
                        class="px-2.5 py-1 text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    + Text
                </button>
                <button @click="addVariableBlock()" type="button"
                        class="px-2.5 py-1 text-xs bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/40">
                    + Variable
                </button>
                <button @click="addIncludeBlock()" type="button"
                        class="px-2.5 py-1 text-xs bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-400 rounded-md hover:bg-purple-100 dark:hover:bg-purple-900/40">
                    + Include
                </button>
            </div>
        </div>
        @endif
        </div>{{-- end inner editor wrapper --}}

        {{-- Live Preview Panel --}}
        @if($showPreview)
        <div class="flex-1 min-h-0 border-t border-amber-200 dark:border-amber-800 bg-amber-50/30 dark:bg-amber-900/10 flex flex-col overflow-hidden">
            {{-- Preview header --}}
            <div class="px-4 py-2 border-b border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/20 shrink-0 flex items-center justify-between">
                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Live Preview</span>
                <div class="flex items-center gap-2">
                    @if($previewResult)
                        @if(!empty($previewResult['includes_resolved']))
                            @foreach($previewResult['includes_resolved'] as $slug)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                    {{ $slug }}
                                </span>
                            @endforeach
                        @endif
                        @if(!empty($previewResult['variables_missing']))
                            @foreach($previewResult['variables_missing'] as $var)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                    {'{'}{'{'} {{ $var }} {'}'}{'}'}
                                </span>
                            @endforeach
                        @endif
                    @endif
                </div>
            </div>

            {{-- Variable fill form --}}
            @if(!empty($detectedVariables))
            <div class="px-4 py-2 border-b border-amber-200/50 dark:border-amber-800/50 shrink-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1.5">
                    @foreach($detectedVariables as $var)
                    <div class="flex items-center gap-2">
                        <label class="text-[11px] font-mono text-amber-700 dark:text-amber-400 whitespace-nowrap w-28 truncate" title="{{ $var }}">
                            {{ $var }}
                        </label>
                        <input type="text"
                               wire:model.live.debounce.500ms="previewVariables.{{ $var }}"
                               placeholder="{{ $variableMetadata[$var]['default'] ?? $var }}"
                               class="flex-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Rendered content --}}
            <div class="flex-1 min-h-0 overflow-auto p-4">
                @if($previewError)
                    <div class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
                        <span class="font-medium">Error:</span> {{ $previewError }}
                    </div>
                @elseif($previewResult)
                    <pre class="text-sm font-mono text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ $previewResult['rendered'] }}</pre>
                @else
                    <p class="text-xs text-gray-400 dark:text-gray-500 italic">Start typing to see a live preview...</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- AI Suggestions Panel --}}
    @if($aiSuggestions)
    <div class="border-t border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-900/10 shrink-0" x-data="{ expanded: true, copied: false, showSaveMenu: false }">
        <div @click="expanded = !expanded"
             class="w-full px-4 py-2 flex items-center justify-between text-xs font-medium text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/20 transition cursor-pointer select-none">
            <span>AI Suggestions</span>
            <div class="flex items-center gap-2">
                {{-- Copy --}}
                <button type="button" @click.stop="navigator.clipboard.writeText(@js($aiSuggestions)); copied = true; setTimeout(() => copied = false, 1500)"
                        class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-xs"
                        :title="copied ? 'Copied!' : 'Copy to clipboard'">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
                {{-- Export as .md --}}
                <button type="button" @click.stop="
                        const blob = new Blob([@js($aiSuggestions)], { type: 'text/markdown' });
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = @js($prompt->slug . '-ai-suggestions.md');
                        a.click();
                        URL.revokeObjectURL(a.href);
                    "
                        class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-xs"
                        title="Export as .md">Export</button>
                {{-- Save dropdown --}}
                <div class="relative" @click.stop>
                    <button type="button" @click="showSaveMenu = !showSaveMenu"
                            class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-xs"
                            title="Save suggestions">Save &#9662;</button>
                    <div x-show="showSaveMenu" x-cloak @click.outside="showSaveMenu = false"
                         class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1">
                        <button type="button" wire:click="saveSuggestionsAsVersion" @click="showSaveMenu = false"
                                class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Save as new version
                        </button>
                        <button type="button" wire:click="saveSuggestionsAsNewPrompt" @click="showSaveMenu = false"
                                class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Save as new prompt
                        </button>
                    </div>
                </div>
                {{-- Dismiss --}}
                <button type="button" wire:click="$set('aiSuggestions', null)" @click.stop class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200" title="Dismiss">&times;</button>
                <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>
        <div x-show="expanded" x-cloak class="px-4 pb-3 max-h-48 overflow-auto">
            <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $aiSuggestions }}</div>
        </div>
    </div>
    @endif

    {{-- Detected tokens bar --}}
    @if(!empty($detectedVariables) || !empty($detectedIncludes))
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 flex flex-wrap gap-2">
        @foreach($detectedVariables as $var)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                {'{'}{'{'} {{ $var }} {'}'}{'}'}
            </span>
        @endforeach
        @foreach($detectedIncludes as $inc)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                {'{'}{'{'}> {{ $inc }} {'}'}{'}'}
            </span>
        @endforeach
    </div>
    @endif

    {{-- Variable Metadata Panel --}}
    @if(!empty($detectedVariables))
    <div x-data="{ showMeta: false }" class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0">
        <button @click="showMeta = !showMeta" type="button"
                class="w-full px-4 py-2 flex items-center justify-between text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <span>Variable Metadata</span>
            <svg class="w-3.5 h-3.5 transition-transform" :class="showMeta ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="showMeta" x-cloak class="px-4 pb-3 space-y-3 max-h-48 overflow-auto">
            @foreach($detectedVariables as $var)
            @php
                $meta = $variableMetadata[$var] ?? ['type' => 'string', 'default' => '', 'description' => ''];
            @endphp
            <div class="bg-gray-50 dark:bg-gray-900 rounded-md p-2.5 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-medium text-blue-700 dark:text-blue-400">{{ $var }}</span>
                    <select wire:change="setMetaField('{{ $var }}', 'type', $event.target.value)"
                            class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-0.5 focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['string', 'text', 'enum', 'number', 'boolean'] as $type)
                        <option value="{{ $type }}" {{ ($meta['type'] ?? 'string') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <input type="text" value="{{ $meta['default'] ?? '' }}"
                           wire:change="setMetaField('{{ $var }}', 'default', $event.target.value)"
                           placeholder="Default value"
                           class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="text" value="{{ $meta['description'] ?? '' }}"
                           wire:change="setMetaField('{{ $var }}', 'description', $event.target.value)"
                           placeholder="Description"
                           class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                @if(($meta['type'] ?? 'string') === 'enum')
                <input type="text" value="{{ $meta['options_csv'] ?? (isset($meta['options']) ? implode(', ', $meta['options']) : '') }}"
                       wire:change="setMetaField('{{ $var }}', 'options_csv', $event.target.value)"
                       placeholder="Options (comma-separated)"
                       class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Errors --}}
    @error('content')
        <div class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm border-t border-red-200 dark:border-red-800">{{ $message }}</div>
    @enderror
</div>
