<div class="h-[calc(100vh-3.5rem)] flex flex-col overflow-hidden" x-data="{ showVersions: false, showResults: false, showMeta: false }">
    {{-- Mobile panel toggles --}}
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

    {{-- Mobile overlay --}}
    <div x-show="showVersions || showResults" x-cloak @click="showVersions = false; showResults = false"
         class="lg:hidden fixed inset-0 bg-black/30 z-20"></div>

    {{-- Three-panel layout --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Version Sidebar --}}
        <div class="border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex flex-col shrink-0 overflow-y-auto"
             :class="showVersions
                 ? 'fixed inset-y-14 left-0 z-30 w-72 shadow-xl'
                 : 'hidden lg:flex w-64'">
            <livewire:workspace.version-sidebar :prompt="$prompt" :current-version="$currentVersion" :key="'vs-'.$prompt->id" />

            {{-- Info button at bottom of sidebar (desktop) --}}
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

        {{-- Editor Panel --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-visible relative z-10">
            <livewire:workspace.editor :prompt="$prompt" :current-version="$currentVersion" :key="'ed-'.$prompt->id" />
        </div>

        {{-- Results Panel --}}
        <div class="border-l border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex flex-col shrink-0 overflow-y-auto"
             :class="showResults
                 ? 'fixed inset-y-14 right-0 z-30 w-80 shadow-xl'
                 : 'hidden lg:flex w-80'">
            <livewire:workspace.results-panel :prompt="$prompt" :current-version="$currentVersion" :key="'rp-'.$prompt->id" />

            <div class="border-t border-gray-200 dark:border-gray-700">
                <livewire:workspace.manual-result-form :prompt="$prompt" :current-version="$currentVersion" :key="'mr-'.$prompt->id" />
            </div>

            <livewire:workspace.import-results :prompt="$prompt" :current-version="$currentVersion" :key="'ir-'.$prompt->id" />

            <livewire:workspace.run-with-llm :prompt="$prompt" :current-version="$currentVersion" :key="'rl-'.$prompt->id" />

            <livewire:workspace.run-with-pipeline :prompt="$prompt" :current-version="$currentVersion" :key="'rt-'.$prompt->id" />
        </div>
    </div>

    {{-- Prompt Info Modal --}}
    <div x-show="showMeta" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showMeta = false">
        {{-- Backdrop --}}
        <div x-show="showMeta" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/40" @click="showMeta = false"></div>
        {{-- Panel --}}
        <div x-show="showMeta" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-md max-h-[80vh] overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-700 p-5">
            {{-- Close button --}}
            <button @click="showMeta = false"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            <livewire:workspace.prompt-metadata :prompt="$prompt" :key="'pm-'.$prompt->id" />
        </div>
    </div>
</div>
