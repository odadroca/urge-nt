<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPanel && $currentVersionId): ?>
    <div class="border-t border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Run Template</h4>
            <button wire:click="$set('showPanel', false)" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($templates->isEmpty()): ?>
            <p class="text-xs text-gray-500 dark:text-gray-400">No active templates. Create them in Settings &rarr; Templates.</p>
        <?php else: ?>
            
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Template</label>
                <select wire:model.live="selectedTemplateId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select a template...</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedTemplate): ?>
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Channels</label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedTemplate->channels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0
                        <?php echo e($channel->trigger === 'synthesis' ? 'bg-purple-400' : 'bg-blue-400'); ?>"></span>
                    <span class="font-medium text-gray-700 dark:text-gray-300"><?php echo e($channel->role_label); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($channel->llmProvider): ?>
                        <span class="text-gray-400 dark:text-gray-500"><?php echo e($channel->llmProvider->name); ?></span>
                    <?php else: ?>
                        <span class="text-amber-500 italic">no provider</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="text-[10px] px-1 py-0.5 rounded uppercase tracking-wide
                        <?php echo e($channel->trigger === 'synthesis'
                            ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'
                            : 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'); ?>">
                        <?php echo e($channel->trigger); ?>

                    </span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
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
                    <?php if(!$selectedTemplateId): ?> disabled <?php endif; ?>
                    class="w-full px-3 py-2 text-xs font-medium bg-purple-600 text-white rounded-md hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="run">Run Template</span>
                <span wire:loading wire:target="run">Running pipeline...</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/run-with-template.blade.php ENDPATH**/ ?>