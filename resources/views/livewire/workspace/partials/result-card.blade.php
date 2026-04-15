{{-- Result card inner content (used by both standalone and pipeline-grouped results) --}}
{{-- Header --}}
<div class="flex items-center justify-between mb-1.5">
    <div class="flex items-center gap-1.5">
        @if($results->count() > 1)
        <input type="checkbox"
               value="{{ $result->id }}"
               x-model="compareIds"
               :disabled="compareIds.length >= 4 && !compareIds.includes('{{ $result->id }}')"
               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 w-3 h-3 disabled:opacity-30 cursor-pointer disabled:cursor-not-allowed">
        @endif
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ $result->provider_name ?: 'Manual' }}
        </span>
        @if($result->role_label)
            <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">{{ $result->role_label }}</span>
        @endif
        @if($result->model_name)
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $result->model_name }}</span>
        @endif
    </div>
    <div class="flex items-center gap-1">
        <button wire:click="toggleStar({{ $result->id }})"
                class="p-1 text-base {{ $result->starred ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600 hover:text-amber-400' }} transition"
                aria-label="{{ $result->starred ? 'Unstar' : 'Star' }} this result">
            &#9733;
        </button>
    </div>
</div>

{{-- Version badge --}}
@if($showAllVersions)
    <span class="text-xs text-indigo-600 dark:text-indigo-400 mb-1 inline-block">v{{ $result->promptVersion->version_number }}</span>
@endif

{{-- Response preview --}}
<div x-data="{ expanded: false }">
    <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap" :class="expanded ? '' : 'line-clamp-4'">{{ $result->response_text }}</div>
    @if(strlen($result->response_text ?? '') > 200)
        <button @click="expanded = !expanded" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 mt-1"
                x-text="expanded ? 'Show less' : 'Show more'"></button>
    @endif
</div>

{{-- Meta info --}}
@if($result->duration_ms || $result->input_tokens || $result->output_tokens)
<div class="flex flex-wrap gap-2 text-xs text-gray-400 dark:text-gray-500 mt-1.5">
    @if($result->duration_ms)
    <span>{{ number_format($result->duration_ms / 1000, 2) }}s</span>
    @endif
    @if($result->input_tokens || $result->output_tokens)
    <span>{{ $result->input_tokens ?? '?' }} in / {{ $result->output_tokens ?? '?' }} out</span>
    @endif
</div>
@endif

{{-- Rating --}}
<div class="flex items-center gap-0.5 mt-2">
    @for($i = 1; $i <= 5; $i++)
    <button wire:click="updateRating({{ $result->id }}, {{ $i }})"
            class="p-1 text-base {{ ($result->rating ?? 0) >= $i ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600 hover:text-amber-400' }} transition"
            aria-label="Rate {{ $i }} star{{ $i > 1 ? 's' : '' }}">
        &#9733;
    </button>
    @endfor
    @if(!$result->pipeline_id)
    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $result->created_at->diffForHumans() }}</span>
    @endif
</div>

{{-- Notes --}}
@if($result->notes)
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 italic">{{ $result->notes }}</p>
@endif

{{-- Actions --}}
<div class="flex items-center gap-2 mt-2" x-data="{ showCollPicker: false }">
    <button @click="readingResult = {{ Js::from([
                'id' => $result->id,
                'provider' => $result->provider_name ?: 'Manual',
                'model' => $result->model_name,
                'text' => $result->response_text,
                'role_label' => $result->role_label,
                'duration_ms' => $result->duration_ms,
                'input_tokens' => $result->input_tokens,
                'output_tokens' => $result->output_tokens,
                'created_at' => $result->created_at->diffForHumans(),
            ]) }}"
            class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Read</button>
    <button x-data @click="navigator.clipboard.writeText(@js($result->response_text))"
            class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">Copy</button>
    <button wire:click="exportResult({{ $result->id }})"
            class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">Export</button>
    <div class="relative">
        <button @click="showCollPicker = !showCollPicker"
                class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded hover:bg-indigo-100 dark:hover:bg-indigo-800/30 transition">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Collect
        </button>
        <div x-show="showCollPicker" x-cloak @click.outside="showCollPicker = false"
             class="absolute bottom-full right-0 mb-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-20 py-1 max-h-40 overflow-y-auto">
            @forelse($collections as $coll)
            <button wire:click="addResultToCollection({{ $result->id }}, {{ $coll->id }})"
                    @click="showCollPicker = false"
                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 truncate">
                {{ $coll->title }}
            </button>
            @empty
            <p class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 italic">No collections yet</p>
            @endforelse
        </div>
    </div>
    <button wire:click="deleteResult({{ $result->id }})" wire:confirm="Delete this result?"
            class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-300">Delete</button>
</div>
