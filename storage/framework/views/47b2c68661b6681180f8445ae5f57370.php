<div>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($generatedKey): ?>
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <p class="text-sm font-semibold text-green-800 dark:text-green-300 mb-1">API Key Created Successfully</p>
            <p class="text-xs text-green-700 dark:text-green-400 mb-2">Copy this key now — it will not be shown again.</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 p-2 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-700 rounded text-xs font-mono text-gray-900 dark:text-gray-100 break-all select-all"><?php echo e($generatedKey); ?></code>
                <button x-data @click="navigator.clipboard.writeText(<?php echo \Illuminate\Support\Js::from($generatedKey)->toHtml() ?>); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 1500)"
                        class="px-3 py-1.5 text-xs bg-gray-600 text-white rounded hover:bg-gray-700 transition">Copy</button>
                <button wire:click="dismissKey" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition">Done</button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deleteConfirmId): ?>
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
            <p class="text-sm text-red-700 dark:text-red-400">Delete this API key? This cannot be undone.</p>
            <div class="flex gap-2">
                <button wire:click="cancelDelete" class="px-3 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button wire:click="delete" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCreateForm): ?>
        <form wire:submit="create" class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
            <div>
                <input wire:model="newKeyName" type="text" placeholder="Key name (e.g. Claude Desktop)"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newKeyName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Scope to prompts (optional)</label>
                <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $prompts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prompt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                            <input type="checkbox" wire:model="selectedPromptIds" value="<?php echo e($prompt->id); ?>"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            <?php echo e($prompt->name); ?> <span class="text-gray-400 dark:text-gray-500">(<?php echo e($prompt->slug); ?>)</span>
                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for access to all prompts.</p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="$toggle('showCreateForm')" class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-1.5 text-xs bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">Generate Key</button>
            </div>
        </form>
    <?php else: ?>
        <button wire:click="$toggle('showCreateForm')"
                class="mb-4 px-4 py-2 text-sm bg-indigo-600 dark:bg-indigo-500 text-white font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New API Key
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($apiKeys->isEmpty()): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">No API keys yet. Create one to get started.</p>
    <?php else: ?>
        <div class="space-y-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $apiKeys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($key->name); ?></span>
                            <span class="px-1.5 py-0.5 text-xs rounded <?php echo e($key->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'); ?>">
                                <?php echo e($key->is_active ? 'Active' : 'Inactive'); ?>

                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono"><?php echo e($key->key_preview); ?>...</span>
                            <button x-data @click="navigator.clipboard.writeText(<?php echo \Illuminate\Support\Js::from($key->key_preview . '...')->toHtml() ?>); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy ID', 1500)"
                                    class="text-indigo-500 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">Copy ID</button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key->last_used_at): ?>
                                <span>Last used: <?php echo e($key->last_used_at->diffForHumans()); ?></span>
                            <?php else: ?>
                                <span>Never used</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key->prompts->isNotEmpty()): ?>
                                <span>Scoped to <?php echo e($key->prompts->count()); ?> prompt(s)</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="toggleActive(<?php echo e($key->id); ?>)"
                                class="px-2 py-1 text-xs <?php echo e($key->is_active ? 'text-amber-600 dark:text-amber-400 hover:text-amber-700' : 'text-green-600 dark:text-green-400 hover:text-green-700'); ?>">
                            <?php echo e($key->is_active ? 'Disable' : 'Enable'); ?>

                        </button>
                        <button wire:click="confirmDelete(<?php echo e($key->id); ?>)"
                                class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                            Delete
                        </button>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/settings/api-keys.blade.php ENDPATH**/ ?>