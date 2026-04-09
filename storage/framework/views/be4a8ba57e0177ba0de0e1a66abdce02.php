<div class="h-[calc(100vh-3.5rem)] flex flex-col overflow-hidden" x-data="{ showVersions: false, showResults: false, showMeta: false }">
    
    <div class="lg:hidden flex items-center gap-2 px-3 py-1.5 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0">
        <button @click="showVersions = !showVersions; showResults = false"
                class="px-3 py-1 text-xs font-medium rounded-md border transition"
                :class="showVersions ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700' : 'text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600'"
                aria-label="Toggle version sidebar">
            Versions
        </button>
        <button @click="showResults = !showResults; showVersions = false"
                class="px-3 py-1 text-xs font-medium rounded-md border transition"
                :class="showResults ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700' : 'text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600'"
                aria-label="Toggle results panel">
            Results
        </button>
        <button @click="showMeta = true"
                class="px-3 py-1 text-xs font-medium rounded-md border transition text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-600"
                aria-label="Open prompt info">
            Info
        </button>
    </div>

    
    <div x-show="showVersions || showResults" x-cloak @click="showVersions = false; showResults = false"
         class="lg:hidden fixed inset-0 bg-black/30 z-20"></div>

    
    <div class="flex-1 flex overflow-hidden">
        
        <div class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex flex-col shrink-0 overflow-y-auto"
             :class="showVersions
                 ? 'fixed inset-y-14 left-0 z-30 w-72 shadow-xl'
                 : 'hidden lg:flex w-64'">
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.version-sidebar', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'vs-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

            
            <div class="mt-auto border-t border-gray-200 dark:border-gray-700 p-2">
                <button @click="showMeta = true"
                        class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Prompt Info
                </button>
            </div>
        </div>

        
        <div class="flex-1 flex flex-col min-w-0 overflow-visible relative z-10">
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.editor', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'ed-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        </div>

        
        <div class="border-l border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex flex-col shrink-0 overflow-y-auto"
             :class="showResults
                 ? 'fixed inset-y-14 right-0 z-30 w-80 shadow-xl'
                 : 'hidden lg:flex w-80'">
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.results-panel', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'rp-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-2', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

            <div class="border-t border-gray-200 dark:border-gray-700">
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.manual-result-form', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'mr-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
            </div>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.import-results', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'ir-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-4', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.run-with-llm', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'rl-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-5', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.run-with-template', ['prompt' => $prompt,'current-version' => $currentVersion]);

$__keyOuter = $__key ?? null;

$__key = 'rt-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-6', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        </div>
    </div>

    
    <div x-show="showMeta" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showMeta = false">
        
        <div x-show="showMeta" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/40" @click="showMeta = false"></div>
        
        <div x-show="showMeta" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-md max-h-[80vh] overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-700 p-5">
            
            <button @click="showMeta = false"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('workspace.prompt-metadata', ['prompt' => $prompt]);

$__keyOuter = $__key ?? null;

$__key = 'pm-'.$prompt->id;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1123936736-7', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        </div>
    </div>
</div>
<?php /**PATH C:\#DATA\Onedrive\Apps\URGEnt\resources\views/livewire/workspace/workspace-page.blade.php ENDPATH**/ ?>