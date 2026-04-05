<div class="p-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
        <button wire:click="$toggle('showCreateForm')" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
            + New Prompt
        </button>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Prompts</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $stats['prompts'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fragments</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $stats['fragments'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Results</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $stats['results'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Starred</p>
            <p class="text-2xl font-bold text-amber-500 mt-1">{{ $stats['starred'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Providers</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $stats['providers'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">API Keys</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $stats['api_keys'] }}</p>
        </div>
    </div>

    {{-- Inline Create Form --}}
    @if($showCreateForm)
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <form wire:submit="createPrompt" class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                <input wire:model="newPromptName" type="text" autofocus
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                       placeholder="My new prompt...">
                @error('newPromptName') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                <select wire:model="newPromptType" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="prompt">Prompt</option>
                    <option value="fragment">Fragment</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                Create
            </button>
            <button type="button" wire:click="$toggle('showCreateForm')" class="px-4 py-2 text-gray-500 dark:text-gray-400 text-sm hover:text-gray-700 dark:hover:text-gray-300">
                Cancel
            </button>
        </form>
    </div>
    @endif

    {{-- Recent Prompts --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Recent Prompts</h2>
            @if($recentPrompts->isNotEmpty())
                <a href="{{ route('browse') }}" wire:navigate class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">View all</a>
            @endif
        </div>
        @if($recentPrompts->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-3">No prompts yet.</p>
                <button wire:click="$set('showCreateForm', true)" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                    Create your first prompt
                </button>
            </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($recentPrompts as $prompt)
            <a href="{{ $prompt->workspaceUrl() }}" wire:navigate
               class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-sm transition">
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $prompt->name }}</h3>
                    @if($prompt->category)
                        <span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $prompt->category->color_hex }}15; color: {{ $prompt->category->color_hex }}">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $prompt->category->color_hex }}"></span>
                            {{ $prompt->category->name }}
                        </span>
                    @endif
                    @if($prompt->isFragment())
                        <span class="text-xs px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded">fragment</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $prompt->latestVersion ? 'v' . $prompt->latestVersion->version_number : 'No versions' }}
                    &middot; {{ $prompt->updated_at->diffForHumans() }}
                </p>
                @if($prompt->description)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">{{ $prompt->description }}</p>
                @endif
            </a>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Starred Results --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Starred Results</h2>
            @if($starredResults->isNotEmpty())
                <a href="{{ route('browse', ['tab' => 'starred']) }}" wire:navigate class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">View all</a>
            @endif
        </div>
        @if($starredResults->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Star your best results to find them here.</p>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($starredResults as $result)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $result->provider_name ?? 'Manual' }}</span>
                    @if($result->rating)
                        <span class="text-xs text-amber-600 dark:text-amber-400">{{ str_repeat('★', $result->rating) }}</span>
                    @endif
                </div>
                <a href="{{ $result->prompt->workspaceUrl() }}" wire:navigate class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    {{ $result->prompt->name }} v{{ $result->promptVersion->version_number }}
                </a>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-3">{{ Str::limit($result->response_text, 150) }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Recent Results --}}
    @if($recentResults->isNotEmpty())
    <section>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Recent Results</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($recentResults as $result)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $result->provider_name ?? 'Manual' }}</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $result->created_at->diffForHumans() }}</span>
                </div>
                <a href="{{ $result->prompt->workspaceUrl() }}" wire:navigate class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    {{ $result->prompt->name }} v{{ $result->promptVersion->version_number }}
                </a>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-3">{{ Str::limit($result->response_text, 150) }}</p>
            </div>
            @endforeach
        </div>
    </section>
    @endif
</div>
