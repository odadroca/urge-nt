@if($entry->item->item_type === 'collection' && $entry->resolved)
    {{-- Nested collection (chapter) --}}
    <div class="relative pl-14">
        <div class="absolute left-2.5 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold bg-violet-600 text-white">
            {{ $index + 1 }}
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-violet-200 dark:border-violet-800 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-violet-100 dark:border-violet-800 flex items-center gap-2 bg-violet-50 dark:bg-violet-900/20">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                    Collection
                </span>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $entry->resolved->title }}
                </span>
                @if($entry->resolved->items_count ?? false)
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $entry->resolved->items_count ?? $entry->resolved->items->count() }} items
                    </span>
                @endif
            </div>

            @if($entry->resolved->description)
                <div class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $entry->resolved->description }}
                </div>
            @endif

            @if($entry->children && $entry->children->isNotEmpty())
                <div class="px-4 py-4">
                    <div class="relative">
                        <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-violet-100 dark:bg-violet-900/30"></div>
                        <div class="space-y-6">
                            @foreach($entry->children as $childIndex => $childEntry)
                                @include('public.partials.story-item', ['entry' => $childEntry, 'index' => $childIndex])
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(!$entry->children)
                <div class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500 italic">
                    Nested content not expanded (depth limit reached)
                </div>
            @endif

            @if($entry->item->notes)
                <div class="px-4 py-2.5 bg-amber-50 dark:bg-amber-900/10 border-t border-amber-100 dark:border-amber-900/20">
                    <p class="text-sm text-amber-800 dark:text-amber-300 leading-relaxed">
                        <span class="font-medium">Note:</span> {{ $entry->item->notes }}
                    </p>
                </div>
            @endif
        </div>
    </div>
@else
    {{-- Prompt version or result item --}}
    <div class="relative pl-14">
        <div class="absolute left-2.5 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold
            {{ $entry->item->item_type === 'prompt_version'
                ? 'bg-indigo-600 text-white'
                : 'bg-emerald-600 text-white' }}">
            {{ $index + 1 }}
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                @if($entry->item->item_type === 'prompt_version')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300">
                        Prompt
                    </span>
                    @if($entry->resolved)
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $entry->resolved->prompt->name ?? 'Untitled' }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            v{{ $entry->resolved->version_number }}
                        </span>
                    @endif
                @elseif($entry->item->item_type === 'result')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                        Result
                    </span>
                    @if($entry->resolved)
                        @if($entry->resolved->provider_name)
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $entry->resolved->provider_name }}
                            </span>
                        @endif
                        @if($entry->resolved->model_name)
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $entry->resolved->model_name }}
                            </span>
                        @endif
                    @endif
                @endif
            </div>

            <div class="px-4 py-4">
                @if($entry->item->item_type === 'prompt_version' && $entry->rendered)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <pre class="whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900/50 rounded-md p-3 font-sans leading-relaxed">{!! nl2br(e($entry->rendered)) !!}</pre>
                    </div>
                @elseif($entry->item->item_type === 'result' && $entry->resolved)
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <div class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap">{{ $entry->resolved->response_text }}</div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                        @if($entry->resolved->rating)
                            <span class="flex items-center gap-0.5">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-3.5 h-3.5 {{ $i <= $entry->resolved->rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            </span>
                        @endif
                        @if($entry->resolved->duration_ms)
                            <span>{{ number_format($entry->resolved->duration_ms / 1000, 1) }}s</span>
                        @endif
                        @if($entry->resolved->input_tokens || $entry->resolved->output_tokens)
                            <span>{{ number_format(($entry->resolved->input_tokens ?? 0) + ($entry->resolved->output_tokens ?? 0)) }} tokens</span>
                        @endif
                        @if($entry->resolved->prompt)
                            <span>{{ $entry->resolved->prompt->name }}</span>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">Content unavailable</p>
                @endif
            </div>

            @if($entry->item->notes)
                <div class="px-4 py-2.5 bg-amber-50 dark:bg-amber-900/10 border-t border-amber-100 dark:border-amber-900/20">
                    <p class="text-sm text-amber-800 dark:text-amber-300 leading-relaxed">
                        <span class="font-medium">Note:</span> {{ $entry->item->notes }}
                    </p>
                </div>
            @endif
        </div>
    </div>
@endif
