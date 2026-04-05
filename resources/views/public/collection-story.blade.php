<x-layouts.public :title="$collection->title">
    <div class="max-w-3xl mx-auto px-4 py-8 sm:py-12">

        {{-- Hero --}}
        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-50 leading-tight">
                {{ $collection->title }}
            </h1>
            @if($collection->description)
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400 max-w-xl mx-auto leading-relaxed">
                    {{ $collection->description }}
                </p>
            @endif
            <div class="mt-4 flex items-center justify-center gap-3 text-sm text-gray-400 dark:text-gray-500">
                <span>{{ $renderedItems->count() }} {{ Str::plural('chapter', $renderedItems->count()) }}</span>
                <span>&middot;</span>
                <span>{{ $collection->creator->name ?? 'Unknown' }}</span>
            </div>
        </div>

        {{-- Timeline --}}
        @if($renderedItems->isNotEmpty())
            <div class="relative">
                {{-- Vertical line --}}
                <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

                <div class="space-y-8">
                    @foreach($renderedItems as $index => $entry)
                        @include('public.partials.story-item', ['entry' => $entry, 'index' => $index])
                    @endforeach
                </div>

                {{-- End marker --}}
                <div class="relative pl-14 pt-4">
                    <div class="absolute left-2.5 w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                        <div class="w-2 h-2 rounded-full bg-white dark:bg-gray-800"></div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 pt-0.5">End</p>
                </div>
            </div>
        @else
            <div class="text-center py-12 text-gray-400 dark:text-gray-500">
                <p>This collection is empty.</p>
            </div>
        @endif
    </div>
</x-layouts.public>
