<div>
    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Metadata</h3>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->canManage() && $prompt->creator): ?>
    <div class="mb-2 text-xs text-gray-400 dark:text-gray-500">
        Shared by <span class="font-medium text-gray-600 dark:text-gray-300"><?php echo e($prompt->creator->name); ?></span>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="save" class="space-y-2">
        <div>
            <input wire:model="name" type="text" placeholder="Prompt name"
                   <?php echo e($this->canManage() ? '' : 'disabled'); ?>

                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5 <?php echo e($this->canManage() ? '' : 'opacity-60 cursor-not-allowed'); ?>">
        </div>

        <div>
            <select wire:model="type"
                    <?php echo e($this->canManage() ? '' : 'disabled'); ?>

                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5 <?php echo e($this->canManage() ? '' : 'opacity-60 cursor-not-allowed'); ?>">
                <option value="prompt">Prompt</option>
                <option value="fragment">Fragment</option>
            </select>
        </div>

        
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs py-1.5 px-2 focus:border-indigo-500 focus:ring-indigo-500 text-left">
                <span class="flex items-center gap-1.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryId && ($selectedCat = $categories->find($categoryId))): ?>
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: <?php echo e($selectedCat->color_hex); ?>"></span>
                        <?php echo e($selectedCat->name); ?>

                    <?php else: ?>
                        <span class="text-gray-400 dark:text-gray-500">No category</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" x-cloak x-transition
                 class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg max-h-48 overflow-y-auto">
                
                <button type="button" wire:click="$set('categoryId', null)" @click="open = false"
                        class="w-full text-left px-2 py-1.5 text-xs text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700">
                    No category
                </button>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <button type="button" wire:click="$set('categoryId', <?php echo e($cat->id); ?>)" @click="open = false"
                        class="w-full text-left px-2 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-1.5 <?php echo e($categoryId == $cat->id ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-700 dark:text-gray-300'); ?>">
                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: <?php echo e($cat->color_hex); ?>"></span>
                    <?php echo e($cat->name); ?>

                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->isEditor()): ?>
                <div class="border-t border-gray-100 dark:border-gray-700">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCategoryCreate): ?>
                    <div class="p-2 space-y-2" @click.stop>
                        <input wire:model="newCategoryName" type="text" placeholder="Category name"
                               class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs py-1 px-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <div class="flex flex-wrap gap-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $colorMap; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $color): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <button type="button" wire:click="$set('newCategoryColor', '<?php echo e($color); ?>')"
                                    class="w-4 h-4 rounded-full border-2 transition <?php echo e($newCategoryColor === $color ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent hover:border-gray-400'); ?>"
                                    style="background-color: <?php echo e((new \App\Models\Category(['color' => $color]))->color_hex); ?>"></button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" wire:click="createCategory"
                                    class="flex-1 px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 transition">Create</button>
                            <button type="button" wire:click="$set('showCategoryCreate', false)"
                                    class="px-2 py-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Cancel</button>
                        </div>
                    </div>
                    <?php else: ?>
                    <button type="button" wire:click="$set('showCategoryCreate', true)"
                            class="w-full text-left px-2 py-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 font-medium">
                        + New category
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div x-data="{
            input: '',
            tags: <?php echo \Illuminate\Support\Js::from($prompt->tags ?? [])->toHtml() ?>,
            allTags: <?php echo \Illuminate\Support\Js::from($allTags)->toHtml() ?>,
            showSuggestions: false,
            get suggestions() {
                if (!this.input) return [];
                const q = this.input.toLowerCase();
                return this.allTags.filter(t => t.includes(q) && !this.tags.map(x => x.toLowerCase()).includes(t));
            },
            addTag(tag) {
                tag = tag.toLowerCase().trim();
                if (tag && !this.tags.map(t => t.toLowerCase()).includes(tag)) {
                    this.tags.push(tag);
                }
                this.input = '';
                this.showSuggestions = false;
                this.sync();
            },
            removeTag(index) {
                this.tags.splice(index, 1);
                this.sync();
            },
            sync() {
                $wire.set('tagsInput', this.tags.join(', '));
            },
            handleKeydown(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    if (this.input.trim()) this.addTag(this.input);
                }
                if (e.key === 'Backspace' && !this.input && this.tags.length) {
                    this.removeTag(this.tags.length - 1);
                }
            }
        }" @click.outside="showSuggestions = false" class="relative">
            
            <div class="flex flex-wrap gap-1 mb-1" x-show="tags.length > 0">
                <template x-for="(tag, i) in tags" :key="i">
                    <span class="inline-flex items-center gap-0.5 text-xs px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded">
                        <span x-text="tag"></span>
                        <button type="button" @click="removeTag(i)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
                    </span>
                </template>
            </div>
            
            <input type="text" x-model="input" @focus="showSuggestions = true" @input="showSuggestions = true"
                   @keydown="handleKeydown" placeholder="Add tags..."
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
            
            <div x-show="showSuggestions && suggestions.length > 0" x-cloak
                 class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg max-h-32 overflow-y-auto">
                <template x-for="s in suggestions" :key="s">
                    <button type="button" @click="addTag(s)"
                            class="w-full text-left px-2 py-1 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            x-text="s"></button>
                </template>
            </div>
        </div>

        <div>
            <textarea wire:model="description" rows="2" placeholder="Description (optional)"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        </div>

        <button type="submit" class="w-full px-2 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-medium rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
            Update Metadata
        </button>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('metadata-saved')): ?>
            <p class="text-xs text-green-600 dark:text-green-400"><?php echo e(session('metadata-saved')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>

    <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
        <p class="text-xs text-gray-400 dark:text-gray-500">Slug: <span class="font-mono"><?php echo e($prompt->slug); ?></span></p>
        <p class="text-xs text-gray-400 dark:text-gray-500">Created: <?php echo e($prompt->created_at->format('M j, Y')); ?></p>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canManage() && $availableTeams->count() > 0): ?>
    <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sharing</h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prompt->isShared()): ?>
                <span class="inline-flex items-center gap-1 text-[10px] text-indigo-600 dark:text-indigo-400">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    Shared (<?php echo e(count($sharedTeamIds)); ?>)
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Private
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="space-y-1.5">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availableTeams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
            <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer">
                <span class="text-xs text-gray-600 dark:text-gray-400 truncate"><?php echo e($team->name); ?></span>
                <button type="button"
                        wire:click="<?php echo e(in_array($team->id, $sharedTeamIds) ? 'unshareFromTeam' : 'shareWithTeam'); ?>(<?php echo e($team->id); ?>)"
                        class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition-colors duration-200 <?php echo e(in_array($team->id, $sharedTeamIds) ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'); ?>">
                    <span class="inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform duration-200 mt-0.5 <?php echo e(in_array($team->id, $sharedTeamIds) ? 'translate-x-3.5 ml-[-2px]' : 'translate-x-0.5'); ?>"></span>
                </button>
            </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canManage()): ?>
    <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($confirmingDelete): ?>
            <p class="text-xs text-red-600 dark:text-red-400 mb-2">Delete this prompt and all its versions and results?</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="flex-1 px-2 py-1.5 text-xs text-gray-600 dark:text-gray-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancel</button>
                <button wire:click="deletePrompt" class="flex-1 px-2 py-1.5 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700 transition">Confirm</button>
            </div>
        <?php else: ?>
            <button wire:click="confirmDelete" class="w-full px-2 py-1.5 text-red-600 dark:text-red-400 text-xs font-medium rounded-md hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                Delete Prompt
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/prompt-metadata.blade.php ENDPATH**/ ?>