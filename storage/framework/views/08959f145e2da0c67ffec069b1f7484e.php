<div>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->isEditor()): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deleteConfirmId): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
            <p class="text-sm text-red-700 dark:text-red-400">Delete this category? Prompts using it will become uncategorized.</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400">Cancel</button>
                <button wire:click="delete" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->isEditor()): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showForm): ?>
        <form wire:submit="<?php echo e($editingId ? 'update' : 'create'); ?>" class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                <input wire:model="name" type="text" placeholder="e.g. Marketing"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <div class="flex flex-wrap gap-1.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $availableColors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <button type="button" wire:click="$set('color', '<?php echo e($c); ?>')"
                                class="w-7 h-7 rounded-full border-2 transition <?php echo e($color === $c ? 'border-gray-900 dark:border-gray-100 scale-110' : 'border-transparent hover:border-gray-400'); ?>"
                                style="background-color: <?php echo e($colorHex[$c] ?? '#6b7280'); ?>"
                                title="<?php echo e($c); ?>">
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                    <?php echo e($editingId ? 'Update' : 'Create'); ?>

                </button>
            </div>
        </form>
    <?php else: ?>
        <button wire:click="$set('showForm', true)"
                class="mb-4 px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New Category
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->isEmpty()): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">No categories yet.</p>
    <?php else: ?>
        <div class="space-y-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full" style="background-color: <?php echo e($colorHex[$category->color] ?? '#6b7280'); ?>"></span>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($category->name); ?></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($category->prompts_count); ?> prompts</span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->isEditor()): ?>
                    <div class="flex items-center gap-2">
                        <button wire:click="edit(<?php echo e($category->id); ?>)"
                                class="px-2 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Edit</button>
                        <button wire:click="confirmDelete(<?php echo e($category->id); ?>)"
                                class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">Delete</button>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/settings/categories.blade.php ENDPATH**/ ?>