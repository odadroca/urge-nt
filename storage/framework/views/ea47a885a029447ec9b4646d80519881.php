<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPanel && $currentVersionId): ?>
    <div class="border-t border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Run with LLM</h4>
            <button wire:click="$set('showPanel', false)" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($providers->isEmpty()): ?>
            <p class="text-xs text-gray-500 dark:text-gray-400">No active LLM providers. Configure them in Settings.</p>
        <?php else: ?>
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Providers</label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $providers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $provider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model="selectedProviderIds" value="<?php echo e($provider->id); ?>"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        <?php echo e($provider->name); ?> <span class="text-gray-400 dark:text-gray-500">(<?php echo e($provider->model); ?>)</span>
                    </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($variables) > 0): ?>
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Variables</label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $variables; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $var): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                                <?php echo e($var); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($variableMetadata[$var]['description'])): ?>
                                    <span class="text-gray-400"> &mdash; <?php echo e($variableMetadata[$var]['description']); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </label>
                            <input wire:model="variableValues.<?php echo e($var); ?>" type="text"
                                   placeholder="<?php echo e($variableMetadata[$var]['default'] ?? $var); ?>"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1.5">
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <button wire:click="run"
                    wire:loading.attr="disabled"
                    <?php if(empty($selectedProviderIds)): ?> disabled <?php endif; ?>
                    class="w-full px-3 py-2 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    @keydown.window.ctrl.enter.prevent="$wire.run()">
                <span wire:loading.remove wire:target="run">Run</span>
                <span wire:loading wire:target="run">Running...</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/run-with-llm.blade.php ENDPATH**/ ?>