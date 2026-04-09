<div x-data="{ readingResult: null }">
    
    <div class="mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateForm): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <input wire:model="newTitle" type="text" placeholder="Collection title"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <textarea wire:model="newDescription" placeholder="Description (optional)" rows="2"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <div class="flex items-center gap-2">
                <button wire:click="createCollection"
                        class="px-3 py-1.5 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium">
                    Create
                </button>
                <button wire:click="$set('showCreateForm', false)"
                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Cancel
                </button>
            </div>
        </div>
        <?php else: ?>
        <button wire:click="$set('showCreateForm', true)"
                class="px-3 py-1.5 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium">
            + New Collection
        </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $collections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden <?php echo e($expandedId === $collection->id ? 'ring-2 ring-indigo-300 dark:ring-indigo-600' : ''); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingId === $collection->id): ?>
            
            <div class="p-4 space-y-2">
                <input wire:model="editTitle" type="text"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <textarea wire:model="editDescription" rows="2"
                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <div class="flex gap-2">
                    <button wire:click="saveEdit" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Save</button>
                    <button wire:click="$set('editingId', null)" class="text-xs text-gray-400 dark:text-gray-500">Cancel</button>
                </div>
            </div>
            <?php else: ?>
            
            <div class="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition" wire:click="toggleExpand(<?php echo e($collection->id); ?>)">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 text-sm"><?php echo e($collection->title); ?></h3>
                    <div class="flex items-center gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($collection->share_links_count > 0): ?>
                            <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-medium">shared</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($collection->items_count); ?> items</span>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($collection->description): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2"><?php echo e($collection->description); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="px-4 pb-3 flex items-center gap-2">
                <button wire:click.stop="openShareModal(<?php echo e($collection->id); ?>)"
                        class="text-xs text-indigo-500 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium">Share</button>
                
                <div x-data="{ open: false }" class="relative" x-on:click.stop>
                    <button x-on:click="open = !open"
                            class="text-xs text-violet-500 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-medium">Nest</button>
                    <div x-show="open" x-on:click.away="open = false" x-transition
                         class="absolute left-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-40 overflow-y-auto">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $collections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $target): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($target->id !== $collection->id): ?>
                            <button wire:click="addCollectionToCollection(<?php echo e($collection->id); ?>, <?php echo e($target->id); ?>)"
                                    x-on:click="open = false"
                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                                <?php echo e($target->title); ?>

                            </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
                <button wire:click="startEditing(<?php echo e($collection->id); ?>)"
                        class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">Edit</button>
                <button wire:click="deleteCollection(<?php echo e($collection->id); ?>)" wire:confirm="Delete this collection?"
                        class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300">Delete</button>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="col-span-full text-center py-8">
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No collections yet. Create one to organize your prompts and results.</p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedCollection): ?>
    <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100"><?php echo e($expandedCollection->title); ?></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedCollection->description): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo e($expandedCollection->description); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($expandedCollection->items->count()); ?> <?php echo e(Str::plural('chapter', $expandedCollection->items->count())); ?></span>
                <button wire:click="$set('expandedId', null)" class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">&times; Close</button>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedCollection->items->isEmpty()): ?>
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No items yet. Add items from the workspace.</p>
        </div>
        <?php else: ?>
        <div class="relative" x-data wire:sortable="reorderItems">
            
            <div class="absolute left-7 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></div>

            <?php $chapterIndex = 0; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groupedExpandedItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($group['type'] === 'pipeline_group'): ?>
                    
                    <?php $pipelineGroup = $group['data']; $chapterIndex++; ?>
                    <div class="relative group" x-data="{ pipelineOpen: true }">
                        
                        <div class="absolute left-5 top-5 w-5 h-5 rounded-full border-2 flex items-center justify-center text-[9px] font-bold z-10
                            border-purple-400 dark:border-purple-500 bg-purple-50 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400">
                            <?php echo e($chapterIndex); ?>

                        </div>

                        <div class="ml-14 mr-5 my-0 py-4 border-b border-gray-100 dark:border-gray-700/50">
                            
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2 cursor-pointer select-none" @click="pipelineOpen = !pipelineOpen">
                                    <svg class="w-3 h-3 text-purple-400 dark:text-purple-500 transition-transform duration-150" :class="pipelineOpen ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wide bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">Pipeline</span>
                                    <span class="text-sm font-medium text-purple-700 dark:text-purple-300"><?php echo e($pipelineGroup['template']->name ?? 'Pipeline Run'); ?></span>
                                    <span class="text-[10px] text-purple-500 dark:text-purple-400"><?php echo e($pipelineGroup['items']->count()); ?> channels</span>
                                </div>
                            </div>

                            
                            <div x-show="pipelineOpen" x-transition class="rounded-lg border-2 border-purple-200 dark:border-purple-800 overflow-hidden">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $pipelineGroup['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <?php $resolved = $item->item; ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?>wire:key="item-<?php echo e($item->id); ?>" wire:sortable.item="<?php echo e($item->id); ?>"
                                     class="p-3 <?php echo e(!$loop->last ? 'border-b border-purple-100 dark:border-purple-800/50' : ''); ?>"
                                     x-data="{ expanded: false }">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved): ?>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <div class="flex items-center gap-2">
                                                <span wire:sortable.handle class="cursor-grab text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 text-xs select-none shrink-0" @click.stop>&#9776;</span>
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($resolved->provider_name ?: 'Manual'); ?></span>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->role_label): ?>
                                                    <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300"><?php echo e($resolved->role_label); ?></span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->model_name): ?>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($resolved->model_name); ?></span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button @click="readingResult = <?php echo e(Js::from([
                                                    'id' => $resolved->id,
                                                    'provider' => $resolved->provider_name ?: 'Manual',
                                                    'model' => $resolved->model_name,
                                                    'text' => $resolved->response_text,
                                                    'role_label' => $resolved->role_label,
                                                    'duration_ms' => $resolved->duration_ms,
                                                    'input_tokens' => $resolved->input_tokens,
                                                    'output_tokens' => $resolved->output_tokens,
                                                    'created_at' => $resolved->created_at->diffForHumans(),
                                                ])); ?>"
                                                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Read</button>
                                                <button wire:click="removeItem(<?php echo e($item->id); ?>)"
                                                        class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300 opacity-0 group-hover:opacity-100 transition">Remove</button>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-3 mb-1.5 text-[10px] text-gray-400 dark:text-gray-500">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->promptVersion): ?>
                                                <span class="font-mono">v<?php echo e($resolved->promptVersion->version_number); ?></span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->input_tokens || $resolved->output_tokens): ?>
                                                <span><?php echo e(number_format($resolved->input_tokens ?? 0)); ?> in / <?php echo e(number_format($resolved->output_tokens ?? 0)); ?> out</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->duration_ms): ?>
                                                <span><?php echo e(number_format($resolved->duration_ms / 1000, 1)); ?>s</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        
                                        <div class="bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                                            <div class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words p-3 leading-relaxed"
                                                 :class="expanded ? '' : 'max-h-32 overflow-hidden'"><?php echo e($resolved->response_text); ?></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strlen($resolved->response_text ?? '') > 300): ?>
                                            <button @click="expanded = !expanded"
                                                    class="w-full px-3 py-1.5 text-[10px] font-medium text-indigo-600 dark:text-indigo-400 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-750 transition">
                                                <span x-text="expanded ? 'Show less' : 'Show full response'"></span>
                                            </button>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm italic text-gray-400 dark:text-gray-500">Deleted item</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <?php $item = $group['data']; $resolved = $item->item; $chapterIndex++; ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?>wire:key="item-<?php echo e($item->id); ?>" wire:sortable.item="<?php echo e($item->id); ?>"
                         class="relative group">

                        
                        <div class="absolute left-5 top-5 w-5 h-5 rounded-full border-2 flex items-center justify-center text-[9px] font-bold z-10
                            <?php echo e($item->item_type === 'prompt_version'
                                ? 'border-blue-400 dark:border-blue-500 bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400'
                                : ($item->item_type === 'collection'
                                    ? 'border-violet-400 dark:border-violet-500 bg-violet-50 dark:bg-violet-900/40 text-violet-600 dark:text-violet-400'
                                    : 'border-green-400 dark:border-green-500 bg-green-50 dark:bg-green-900/40 text-green-600 dark:text-green-400')); ?>">
                            <?php echo e($chapterIndex); ?>

                        </div>

                        
                        <div class="ml-14 mr-5 my-0 py-4 border-b border-gray-100 dark:border-gray-700/50"
                             x-data="{ open: true, expanded: false }">
                            <div class="flex items-start justify-between gap-2" :class="open ? 'mb-2' : ''">
                                <div class="flex items-center gap-2 min-w-0 cursor-pointer select-none" x-on:click="open = !open">
                                    <span wire:sortable.handle class="cursor-grab text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 text-xs select-none shrink-0" x-on:click.stop>&#9776;</span>

                                    
                                    <svg class="w-3 h-3 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-150" :class="open ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>

                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wide shrink-0
                                        <?php echo e($item->item_type === 'prompt_version'
                                            ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                                            : ($item->item_type === 'collection'
                                                ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400'
                                                : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400')); ?>">
                                        <?php echo e($item->item_type === 'prompt_version' ? 'Prompt' : ($item->item_type === 'collection' ? 'Collection' : 'Result')); ?>

                                    </span>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->item_type === 'prompt_version' && $resolved): ?>
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            <?php echo e($resolved->prompt->name ?? 'Unknown'); ?>

                                        </span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-mono shrink-0">
                                            v<?php echo e($resolved->version_number); ?>

                                        </span>
                                    <?php elseif($item->item_type === 'collection' && $resolved): ?>
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            <?php echo e($resolved->title); ?>

                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                            <?php echo e($resolved->items->count()); ?> items
                                        </span>
                                    <?php elseif($item->item_type === 'result' && $resolved): ?>
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            <?php echo e($resolved->prompt->name ?? 'Unknown'); ?>

                                        </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->role_label): ?>
                                            <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 shrink-0"><?php echo e($resolved->role_label); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                            via <?php echo e($resolved->provider_name ?: 'Manual'); ?><?php echo e($resolved->model_name ? " ({$resolved->model_name})" : ''); ?>

                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm italic text-gray-400 dark:text-gray-500">Deleted item</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->item_type === 'result' && $resolved): ?>
                                    <button @click="readingResult = <?php echo e(Js::from([
                                        'id' => $resolved->id,
                                        'provider' => $resolved->provider_name ?: 'Manual',
                                        'model' => $resolved->model_name,
                                        'text' => $resolved->response_text,
                                        'role_label' => $resolved->role_label,
                                        'duration_ms' => $resolved->duration_ms,
                                        'input_tokens' => $resolved->input_tokens,
                                        'output_tokens' => $resolved->output_tokens,
                                        'created_at' => $resolved->created_at->diffForHumans(),
                                    ])); ?>"
                                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Read</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <button wire:click="removeItem(<?php echo e($item->id); ?>)"
                                            class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300 opacity-0 group-hover:opacity-100 transition shrink-0">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved): ?>
                            <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->item_type === 'prompt_version'): ?>
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                                        <pre class="text-xs font-mono text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words p-3 leading-relaxed"
                                             :class="expanded ? '' : 'max-h-32 overflow-hidden'"><?php echo e($resolved->content); ?></pre>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strlen($resolved->content) > 300): ?>
                                        <button x-on:click="expanded = !expanded"
                                                class="w-full px-3 py-1.5 text-[10px] font-medium text-indigo-600 dark:text-indigo-400 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-750 transition">
                                            <span x-text="expanded ? 'Show less' : 'Show full prompt'"></span>
                                        </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->variables && count($resolved->variables) > 0): ?>
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $resolved->variables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 font-mono">{{ $var }}</span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php elseif($item->item_type === 'result'): ?>
                                    
                                    <div class="flex items-center gap-3 mb-1.5 text-[10px] text-gray-400 dark:text-gray-500">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->pipelineTemplate): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-semibold"><?php echo e($resolved->pipelineTemplate->name); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->promptVersion): ?>
                                            <span class="font-mono">v<?php echo e($resolved->promptVersion->version_number); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->rating): ?>
                                            <span class="text-amber-500">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 5; $i++): ?>
                                                    <span class="<?php echo e($i <= $resolved->rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'); ?>">&#9733;</span>
                                                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->input_tokens || $resolved->output_tokens): ?>
                                            <span><?php echo e(number_format($resolved->input_tokens ?? 0)); ?> in / <?php echo e(number_format($resolved->output_tokens ?? 0)); ?> out</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->duration_ms): ?>
                                            <span><?php echo e(number_format($resolved->duration_ms / 1000, 1)); ?>s</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                                        <div class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words p-3 leading-relaxed"
                                             :class="expanded ? '' : 'max-h-32 overflow-hidden'"><?php echo e($resolved->response_text); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strlen($resolved->response_text ?? '') > 300): ?>
                                        <button x-on:click="expanded = !expanded"
                                                class="w-full px-3 py-1.5 text-[10px] font-medium text-indigo-600 dark:text-indigo-400 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-750 transition">
                                            <span x-text="expanded ? 'Show less' : 'Show full response'"></span>
                                        </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                <?php elseif($item->item_type === 'collection'): ?>
                                    
                                    <div class="bg-violet-50 dark:bg-violet-900/10 rounded-md border border-violet-200 dark:border-violet-800 overflow-hidden">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->description): ?>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 p-3 leading-relaxed"><?php echo e($resolved->description); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->items->isNotEmpty()): ?>
                                            <div class="px-3 <?php echo e($resolved->description ? 'pb-3' : 'py-3'); ?> space-y-1">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $resolved->items->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $childItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                                    <div class="flex items-center gap-1.5 text-[10px] text-gray-500 dark:text-gray-400">
                                                        <span class="w-1 h-1 rounded-full shrink-0
                                                            <?php echo e($childItem->item_type === 'prompt_version' ? 'bg-blue-400' : ($childItem->item_type === 'collection' ? 'bg-violet-400' : 'bg-green-400')); ?>"></span>
                                                        <span class="truncate">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($childItem->item_type === 'prompt_version' && $childItem->item): ?>
                                                                <?php echo e($childItem->item->prompt->name ?? 'Prompt'); ?> v<?php echo e($childItem->item->version_number); ?>

                                                            <?php elseif($childItem->item_type === 'collection' && $childItem->item): ?>
                                                                <?php echo e($childItem->item->title); ?>

                                                            <?php elseif($childItem->item_type === 'result' && $childItem->item): ?>
                                                                <?php echo e($childItem->item->prompt->name ?? 'Result'); ?> (<?php echo e($childItem->item->provider_name ?: 'Manual'); ?>)
                                                            <?php else: ?>
                                                                <?php echo e(ucfirst($childItem->item_type)); ?>

                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolved->items->count() > 5): ?>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 italic">+ <?php echo e($resolved->items->count() - 5); ?> more</p>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 italic p-3">Empty collection</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->notes): ?>
                            <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mt-2 text-xs text-amber-700 dark:text-amber-400 italic bg-amber-50 dark:bg-amber-900/20 rounded px-2.5 py-1.5 border border-amber-200 dark:border-amber-800/50">
                                <?php echo e($item->notes); ?>

                            </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showShareModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeShareModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-xl w-full max-w-md mx-4">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">Share Collection</h3>
                <button wire:click="closeShareModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
            </div>

            <div class="p-5 space-y-4">
                
                <div class="space-y-3">
                    <input wire:model="shareLabel" type="text" placeholder="Label (optional, e.g. 'For client review')"
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div class="flex items-center gap-3">
                        <select wire:model="shareExpiry"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="never">Never expires</option>
                            <option value="1h">1 hour</option>
                            <option value="24h">24 hours</option>
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                        </select>
                        <button wire:click="generateShareLink"
                                class="px-3 py-1.5 text-sm bg-indigo-600 dark:bg-indigo-500 text-white rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 font-medium whitespace-nowrap">
                            Generate Link
                        </button>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($justCreatedShareUrl): ?>
                <div x-data="{ copied: false }" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-3">
                    <p class="text-xs text-green-700 dark:text-green-400 font-medium mb-2">Link created!</p>
                    <div class="flex items-center gap-2">
                        <input type="text" value="<?php echo e($justCreatedShareUrl); ?>" readonly
                               class="flex-1 rounded-md border-green-300 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300 text-xs focus:border-green-500 focus:ring-green-500">
                        <button x-on:click="navigator.clipboard.writeText('<?php echo e($justCreatedShareUrl); ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="px-2.5 py-1.5 text-xs bg-green-600 text-white rounded-md hover:bg-green-700 font-medium whitespace-nowrap">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($shareLinks->isNotEmpty()): ?>
                <div>
                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Active Links</h4>
                    <div class="space-y-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $shareLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-900 rounded-md border border-gray-200 dark:border-gray-700 text-xs">
                            <div class="flex-1 min-w-0">
                                <p class="text-gray-700 dark:text-gray-300 font-medium truncate">
                                    <?php echo e($link->label ?: 'Untitled link'); ?>

                                </p>
                                <p class="text-gray-400 dark:text-gray-500">
                                    <?php echo e($link->access_count); ?> views
                                    &middot;
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link->expires_at): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link->isExpired()): ?>
                                            <span class="text-red-400">Expired</span>
                                        <?php else: ?>
                                            expires <?php echo e($link->expires_at->diffForHumans()); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php else: ?>
                                        never expires
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 ml-2">
                                <button x-data x-on:click="navigator.clipboard.writeText('<?php echo e($link->getUrl()); ?>')"
                                        class="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400" title="Copy link">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <button wire:click="revokeShareLink(<?php echo e($link->id); ?>)" wire:confirm="Revoke this share link?"
                                        class="text-red-400 hover:text-red-600 dark:hover:text-red-300" title="Revoke">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div x-show="readingResult" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="if (readingResult) readingResult = null">
        <div class="fixed inset-0 bg-black/50" @click="readingResult = null"></div>
        <div class="relative min-h-screen flex items-start justify-center p-4 pt-8">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-3xl" @click.stop>
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
                <div class="p-6 max-h-[80vh] overflow-y-auto">
                    <pre class="text-sm whitespace-pre-wrap break-words leading-relaxed font-mono text-gray-800 dark:text-gray-200" x-text="readingResult?.text"></pre>
                </div>
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
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/browse/collection-list.blade.php ENDPATH**/ ?>