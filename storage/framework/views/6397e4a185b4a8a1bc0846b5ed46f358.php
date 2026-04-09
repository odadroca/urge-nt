<div class="p-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$showForm): ?>
        <button wire:click="$toggle('showForm')"
                class="w-full px-3 py-2 text-sm text-indigo-600 dark:text-indigo-400 border border-dashed border-indigo-300 dark:border-indigo-700 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
            + Paste Result
        </button>
    <?php else: ?>
        <form wire:submit="save" class="space-y-2">
            <div class="flex gap-2">
                <input wire:model="providerName" type="text" placeholder="Provider (e.g. ChatGPT)"
                       class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                <input wire:model="modelName" type="text" placeholder="Model"
                       class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
            </div>

            <textarea wire:model="responseText" rows="6" placeholder="Paste the LLM response here..."
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['responseText'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 dark:text-red-400 text-xs"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <textarea wire:model="notes" rows="2" placeholder="Notes (optional)"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">Rating:</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 5; $i++): ?>
                    <button type="button" wire:click="$set('rating', <?php echo e($i); ?>)"
                            class="p-1 text-base <?php echo e(($rating ?? 0) >= $i ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600'); ?>"
                            aria-label="Rate <?php echo e($i); ?> star<?php echo e($i > 1 ? 's' : ''); ?>">&#9733;</button>
                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="$toggle('showForm')" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 dark:bg-indigo-500 text-white text-xs font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                        Save Result
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/manual-result-form.blade.php ENDPATH**/ ?>