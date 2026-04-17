<?php if (isset($component)) { $__componentOriginal8c0e86a062c1c5bb6d0e151b7076f3fd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c0e86a062c1c5bb6d0e151b7076f3fd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.public','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.public'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="min-h-screen flex items-center justify-center bg-gray-900 p-4">
        <div class="w-full max-w-md bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h1 class="text-xl font-bold text-indigo-400 text-center mb-2">URGE</h1>
            <h2 class="text-lg font-semibold text-gray-100 text-center mb-6">Authorize Application</h2>

            <div class="bg-gray-900 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-300 mb-2">
                    <span class="text-gray-500">Application:</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($client_name): ?>
                        <span class="font-medium"><?php echo e($client_name); ?></span>
                        <span class="font-mono text-xs text-gray-500 block mt-0.5 break-all"><?php echo e($client_id); ?></span>
                    <?php else: ?>
                        <span class="font-mono text-xs break-all"><?php echo e($client_id); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                <p class="text-sm text-gray-300">
                    <span class="text-gray-500">Requesting access to:</span>
                </p>
                <ul class="mt-2 space-y-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $scopes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <li class="text-xs text-indigo-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <?php echo e($s); ?>

                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>

            <form method="POST" action="<?php echo e(url('/oauth/authorize')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="client_id" value="<?php echo e($client_id); ?>">
                <input type="hidden" name="redirect_uri" value="<?php echo e($redirect_uri); ?>">
                <input type="hidden" name="scope" value="<?php echo e($scope); ?>">
                <input type="hidden" name="state" value="<?php echo e($state); ?>">
                <input type="hidden" name="code_challenge" value="<?php echo e($code_challenge); ?>">
                <input type="hidden" name="code_challenge_method" value="<?php echo e($code_challenge_method); ?>">
                <input type="hidden" name="resource" value="<?php echo e($resource); ?>">

                <div class="flex gap-3">
                    <button type="submit" name="decision" value="deny"
                        class="flex-1 bg-gray-700 hover:bg-gray-600 text-gray-300 py-2 rounded-lg font-medium">
                        Deny
                    </button>
                    <button type="submit" name="decision" value="approve"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium">
                        Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c0e86a062c1c5bb6d0e151b7076f3fd)): ?>
<?php $attributes = $__attributesOriginal8c0e86a062c1c5bb6d0e151b7076f3fd; ?>
<?php unset($__attributesOriginal8c0e86a062c1c5bb6d0e151b7076f3fd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c0e86a062c1c5bb6d0e151b7076f3fd)): ?>
<?php $component = $__componentOriginal8c0e86a062c1c5bb6d0e151b7076f3fd; ?>
<?php unset($__componentOriginal8c0e86a062c1c5bb6d0e151b7076f3fd); ?>
<?php endif; ?>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/oauth/authorize.blade.php ENDPATH**/ ?>