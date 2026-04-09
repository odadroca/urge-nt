<section x-data="{ confirming: false }">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            <?php echo e(__('Delete Account')); ?>

        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            <?php echo e(__('Once deleted, all your data will be permanently removed.')); ?>

        </p>
    </header>

    <div class="mt-4">
        <button @click="confirming = true" x-show="!confirming"
                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
            Delete Account
        </button>

        <form method="post" action="<?php echo e(route('profile.destroy')); ?>" x-show="confirming" x-cloak class="space-y-4">
            <?php echo csrf_field(); ?>
            <?php echo method_field('delete'); ?>

            <p class="text-sm text-red-600 dark:text-red-400">
                This action cannot be undone. Enter your password to confirm.
            </p>

            <div>
                <input name="password" type="password" placeholder="Your password"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password', 'userDeletion'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex gap-2">
                <button type="button" @click="confirming = false"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    Permanently Delete
                </button>
            </div>
        </form>
    </div>
</section>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/profile/partials/delete-user-form.blade.php ENDPATH**/ ?>