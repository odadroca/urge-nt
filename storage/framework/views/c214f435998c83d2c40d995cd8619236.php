<div class="flex flex-col h-full" x-data="autocomplete()"
     @keydown.window.prevent.ctrl.s="$wire.saveVersion()"
     @keydown.window.prevent.meta.s="$wire.saveVersion()"
     @keydown.window.prevent.ctrl.enter="$dispatch('toggle-run-panel')"
     @keydown.window.prevent.meta.enter="$dispatch('toggle-run-panel')"
>
    
    <div class="flex items-center justify-between px-3 sm:px-4 py-2 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 gap-2"
         x-data="{ overflowOpen: false }">

        
        <div class="flex items-center gap-2 min-w-0 shrink">
            <h2 class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[8rem] sm:max-w-[14rem] lg:max-w-none"><?php echo e($prompt->name); ?></h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentVersionId): ?>
                <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
                    v<?php echo e(App\Models\PromptVersion::find($currentVersionId)?->version_number); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isDirty): ?>
                <span class="shrink-0 w-2 h-2 rounded-full bg-amber-500" title="Unsaved changes"></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="flex items-center gap-2 shrink-0">

            
            <div class="hidden lg:flex items-center gap-2" x-data="{ mode: <?php echo \Illuminate\Support\Js::from($editorMode)->toHtml() ?> }">
                
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

                
                <button type="button" wire:click="togglePreview"
                        class="px-2.5 py-1 text-xs font-medium rounded-md border transition"
                        :class="<?php echo \Illuminate\Support\Js::from($showPreview)->toHtml() ?>
                            ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700'
                            : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'"
                        title="Toggle live preview">
                    Preview
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentVersionId): ?>
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
                        title="Run with pipeline">Run Pipeline</button>
                
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\LlmProvider::where('is_active', true)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prov): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button wire:click="suggestImprovements(<?php echo e($prov->id); ?>)"
                                @click="showPicker = false"
                                class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                            <?php echo e($prov->name); ?> <span class="text-gray-400 dark:text-gray-500">(<?php echo e($prov->model); ?>)</span>
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="relative lg:hidden" x-data="{ mobileMode: <?php echo \Illuminate\Support\Js::from($editorMode)->toHtml() ?> }">
                <button @click="overflowOpen = !overflowOpen" type="button"
                        class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        title="More actions">
                    
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

                    
                    <button type="button" wire:click="togglePreview" @click="overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs transition"
                            :class="<?php echo \Illuminate\Support\Js::from($showPreview)->toHtml() ?>
                                ? 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 font-medium'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                        <?php echo e($showPreview ? 'Hide Preview' : 'Show Preview'); ?>

                    </button>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentVersionId): ?>
                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                    
                    <button wire:click="exportPrompt" wire:loading.attr="disabled" @click="overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="exportPrompt">Export Prompt</span>
                        <span wire:loading wire:target="exportPrompt">Exporting...</span>
                    </button>

                    
                    <button @click="
                                const rendered = await $wire.getRenderedContent();
                                if (rendered) { navigator.clipboard.writeText(rendered); }
                                overflowOpen = false;
                            "
                            class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Copy Rendered
                    </button>

                    
                    <button @click="$dispatch('toggle-run-panel'); overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-green-600 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Run LLM
                    </button>

                    
                    <button @click="$dispatch('toggle-template-panel'); overflowOpen = false"
                            class="w-full text-left px-3 py-1.5 text-xs text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Run Pipeline
                    </button>

                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>

                    
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\LlmProvider::where('is_active', true)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prov): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <button wire:click="suggestImprovements(<?php echo e($prov->id); ?>)"
                                    @click="overflowOpen = false"
                                    class="w-full text-left px-4 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                <?php echo e($prov->name); ?> <span class="text-gray-400 dark:text-gray-500">(<?php echo e($prov->model); ?>)</span>
                            </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <input wire:model="commitMessage" type="text" placeholder="Commit msg"
                   class="w-24 sm:w-32 lg:w-48 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1">
            <button wire:click="saveVersion" wire:loading.attr="disabled"
                    class="px-3 py-1.5 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 disabled:opacity-50 transition whitespace-nowrap">
                <span wire:loading.remove wire:target="saveVersion">Save Version</span>
                <span wire:loading wire:target="saveVersion">Saving...</span>
            </button>
        </div>
    </div>

    
    <div class="flex-1 overflow-hidden relative flex flex-col">
        <div class="flex-1 min-h-0 overflow-hidden relative">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editorMode === 'text'): ?>
        
        <textarea wire:model.live.debounce.300ms="content"
                  x-ref="editorTextarea"
                  @input="handleInput($event); positionDropdown($event.target)"
                  @keydown="handleKeydown($event)"
                  class="w-full h-full resize-none border-0 focus:ring-0 p-4 font-mono text-sm text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900"
                  placeholder="Write your prompt here...&#10;&#10;Use {'{'}{'{'} variable {'}'}{'}'}  for variables&#10;Use {'{'}{'{'}> slug {'}'}{'}'}  to include fragments"
                  spellcheck="false"></textarea>

        
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
        <?php else: ?>
        
        <div class="h-full overflow-auto p-4 bg-gray-50 dark:bg-gray-900" x-data="composer()" x-init="parseContent(<?php echo \Illuminate\Support\Js::from($content)->toHtml() ?>)">
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPreview): ?>
        <div class="flex-1 min-h-0 border-t border-amber-200 dark:border-amber-800 bg-amber-50/30 dark:bg-amber-900/10 flex flex-col overflow-hidden">
            
            <div class="px-4 py-2 border-b border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/20 shrink-0 flex items-center justify-between">
                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Live Preview</span>
                <div class="flex items-center gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewResult): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($previewResult['includes_resolved'])): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $previewResult['includes_resolved']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                    <?php echo e($slug); ?>

                                </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($previewResult['variables_missing'])): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $previewResult['variables_missing']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                    {'{'}{'{'} <?php echo e($var); ?> {'}'}{'}'}
                                </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($detectedVariables)): ?>
            <div class="px-4 py-2 border-b border-amber-200/50 dark:border-amber-800/50 shrink-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detectedVariables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <div class="flex items-center gap-2">
                        <label class="text-[11px] font-mono text-amber-700 dark:text-amber-400 whitespace-nowrap w-28 truncate" title="<?php echo e($var); ?>">
                            <?php echo e($var); ?>

                        </label>
                        <input type="text"
                               wire:model.live.debounce.500ms="previewVariables.<?php echo e($var); ?>"
                               placeholder="<?php echo e($variableMetadata[$var]['default'] ?? $var); ?>"
                               class="flex-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 px-2 focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="flex-1 min-h-0 overflow-auto p-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewError): ?>
                    <div class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
                        <span class="font-medium">Error:</span> <?php echo e($previewError); ?>

                    </div>
                <?php elseif($previewResult): ?>
                    <pre class="text-sm font-mono text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words"><?php echo e($previewResult['rendered']); ?></pre>
                <?php else: ?>
                    <p class="text-xs text-gray-400 dark:text-gray-500 italic">Start typing to see a live preview...</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aiSuggestions): ?>
    <div class="border-t border-gray-200 dark:border-gray-700 bg-purple-50 dark:bg-purple-900/10 shrink-0" x-data="{ expanded: true, copied: false, showSaveMenu: false }">
        <div @click="expanded = !expanded"
             class="w-full px-4 py-2 flex items-center justify-between text-xs font-medium text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/20 transition cursor-pointer select-none">
            <span>AI Suggestions</span>
            <div class="flex items-center gap-2">
                
                <button type="button" @click.stop="navigator.clipboard.writeText(<?php echo \Illuminate\Support\Js::from($aiSuggestions)->toHtml() ?>); copied = true; setTimeout(() => copied = false, 1500)"
                        class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-xs"
                        :title="copied ? 'Copied!' : 'Copy to clipboard'">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
                
                <button type="button" @click.stop="
                        const blob = new Blob([<?php echo \Illuminate\Support\Js::from($aiSuggestions)->toHtml() ?>], { type: 'text/markdown' });
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = <?php echo \Illuminate\Support\Js::from($prompt->slug . '-ai-suggestions.md')->toHtml() ?>;
                        a.click();
                        URL.revokeObjectURL(a.href);
                    "
                        class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200 text-xs"
                        title="Export as .md">Export</button>
                
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
                
                <button type="button" wire:click="$set('aiSuggestions', null)" @click.stop class="text-purple-400 hover:text-purple-600 dark:hover:text-purple-200" title="Dismiss">&times;</button>
                <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>
        <div x-show="expanded" x-cloak class="px-4 pb-3 max-h-48 overflow-auto">
            <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap"><?php echo e($aiSuggestions); ?></div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($detectedVariables) || !empty($detectedIncludes)): ?>
    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detectedVariables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                {'{'}{'{'} <?php echo e($var); ?> {'}'}{'}'}
            </span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detectedIncludes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                {'{'}{'{'}> <?php echo e($inc); ?> {'}'}{'}'}
            </span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($detectedVariables)): ?>
    <div x-data="{ showMeta: false }" class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0">
        <button @click="showMeta = !showMeta" type="button"
                class="w-full px-4 py-2 flex items-center justify-between text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <span>Variable Metadata</span>
            <svg class="w-3.5 h-3.5 transition-transform" :class="showMeta ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="showMeta" x-cloak class="px-4 pb-3 space-y-3 max-h-48 overflow-auto">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detectedVariables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <?php
                $meta = $variableMetadata[$var] ?? ['type' => 'string', 'default' => '', 'description' => ''];
            ?>
            <div class="bg-gray-50 dark:bg-gray-900 rounded-md p-2.5 space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-medium text-blue-700 dark:text-blue-400"><?php echo e($var); ?></span>
                    <select wire:change="setMetaField('<?php echo e($var); ?>', 'type', $event.target.value)"
                            class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-0.5 focus:border-indigo-500 focus:ring-indigo-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['string', 'text', 'enum', 'number', 'boolean']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <option value="<?php echo e($type); ?>" <?php echo e(($meta['type'] ?? 'string') === $type ? 'selected' : ''); ?>><?php echo e($type); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <input type="text" value="<?php echo e($meta['default'] ?? ''); ?>"
                           wire:change="setMetaField('<?php echo e($var); ?>', 'default', $event.target.value)"
                           placeholder="Default value"
                           class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="text" value="<?php echo e($meta['description'] ?? ''); ?>"
                           wire:change="setMetaField('<?php echo e($var); ?>', 'description', $event.target.value)"
                           placeholder="Description"
                           class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($meta['type'] ?? 'string') === 'enum'): ?>
                <input type="text" value="<?php echo e($meta['options_csv'] ?? (isset($meta['options']) ? implode(', ', $meta['options']) : '')); ?>"
                       wire:change="setMetaField('<?php echo e($var); ?>', 'options_csv', $event.target.value)"
                       placeholder="Options (comma-separated)"
                       class="w-full text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <div class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm border-t border-red-200 dark:border-red-800"><?php echo e($message); ?></div>
    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/editor.blade.php ENDPATH**/ ?>