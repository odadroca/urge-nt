<div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Settings</h1>

    {{-- Tabs (role-based visibility) --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex space-x-4">
            @if(in_array('api-keys', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'api-keys')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'api-keys' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                API Keys
            </button>
            @endif
            @if(in_array('llm-providers', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'llm-providers')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'llm-providers' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                LLM Providers
            </button>
            @endif
            @if(in_array('categories', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'categories')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'categories' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                Categories
            </button>
            @endif
            @if(in_array('pipelines', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'pipelines')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'pipelines' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                Pipelines
            </button>
            @endif
            @if(in_array('evaluation', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'evaluation')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'evaluation' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                Evaluation
            </button>
            @endif
            @if(in_array('users', $this->visibleTabs))
            <button wire:click="$set('activeTab', 'users')"
                    class="px-3 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'users' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }}">
                Users
            </button>
            @endif
        </nav>
    </div>

    {{-- Tab Content (with role guards) --}}
    @if($activeTab === 'api-keys' && in_array('api-keys', $this->visibleTabs))
        <livewire:settings.api-keys />
    @elseif($activeTab === 'llm-providers' && in_array('llm-providers', $this->visibleTabs))
        <livewire:settings.llm-providers />
    @elseif($activeTab === 'categories' && in_array('categories', $this->visibleTabs))
        <livewire:settings.categories />
    @elseif($activeTab === 'pipelines' && in_array('pipelines', $this->visibleTabs))
        <livewire:settings.pipelines />
    @elseif($activeTab === 'evaluation' && in_array('evaluation', $this->visibleTabs))
        <livewire:settings.evaluation />
    @elseif($activeTab === 'users' && in_array('users', $this->visibleTabs))
        <livewire:settings.user-management />
    @endif
</div>
