<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'URGE' }} - URGE</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

        <script>
            (function() {
                function applyTheme() {
                    document.documentElement.classList.toggle('dark', localStorage.theme === 'dark');
                }
                applyTheme();
                document.addEventListener('livewire:navigated', applyTheme);
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        {{-- Toast Notifications --}}
        <div x-data="toasts()" @notify.window="add($event.detail)"
             class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
            <template x-for="toast in items" :key="toast.id">
                <div x-show="toast.visible"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-x-8"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-x-0"
                     x-transition:leave-end="opacity-0 translate-x-8"
                     class="pointer-events-auto px-4 py-2.5 rounded-lg shadow-lg text-sm font-medium max-w-sm"
                     :class="toast.type === 'error'
                         ? 'bg-red-600 text-white'
                         : 'bg-green-600 text-white'">
                    <span x-text="toast.message"></span>
                </div>
            </template>
        </div>

        <script>
            function toasts() {
                return {
                    items: [],
                    add(detail) {
                        const id = Date.now();
                        this.items.push({ id, message: detail.message || detail[0]?.message || '', type: detail.type || detail[0]?.type || 'success', visible: true });
                        setTimeout(() => {
                            const t = this.items.find(i => i.id === id);
                            if (t) t.visible = false;
                            setTimeout(() => { this.items = this.items.filter(i => i.id !== id); }, 200);
                        }, 3000);
                    }
                };
            }
        </script>

        @php
            $lastPrompt = session('last_prompt_id') ? \App\Models\Prompt::with('creator')->find(session('last_prompt_id')) : null;
        @endphp

        <div class="min-h-screen flex flex-col">
            <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 h-14 flex items-center justify-between shrink-0" x-data="{ mobileMenu: false }">
                <div class="flex items-center gap-6">
                    <a href="{{ route('browse') }}" wire:navigate class="flex items-center gap-1.5 text-lg font-bold text-indigo-600 dark:text-indigo-400 tracking-tight">
                        <x-application-logo class="w-5 h-5 fill-current" />
                        URGE
                    </a>

                    {{-- Mobile hamburger --}}
                    <button @click="mobileMenu = !mobileMenu" class="sm:hidden p-1.5 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Toggle navigation menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path x-show="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            <path x-show="mobileMenu" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <div class="hidden sm:flex items-center gap-1">
                        <a href="{{ route('browse') }}" wire:navigate
                           class="px-3 py-1.5 rounded-md text-sm font-medium {{ request()->routeIs('browse') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            Browse
                        </a>
                        @auth
                        <a href="{{ route('teams') }}" wire:navigate
                           class="px-3 py-1.5 rounded-md text-sm font-medium {{ request()->routeIs('teams', 'team.detail') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            Teams
                        </a>
                        <a href="{{ route('settings') }}" wire:navigate
                           class="px-3 py-1.5 rounded-md text-sm font-medium {{ request()->routeIs('settings') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            Settings
                        </a>
                        @endauth
                        @if($lastPrompt)
                        <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                        <a href="{{ $lastPrompt->workspaceUrl() }}" wire:navigate
                           class="px-2 py-1.5 text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-[12rem]"
                           title="{{ $lastPrompt->name }}">
                            Continue: {{ Str::limit($lastPrompt->name, 20) }}
                        </a>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        x-data="{ dark: localStorage.theme === 'dark' }"
                        x-on:click="dark = !dark; localStorage.theme = dark ? 'dark' : 'light'; document.documentElement.classList.toggle('dark', dark)"
                        class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        aria-label="Toggle dark mode">
                        <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>
                    <a href="{{ route('profile.edit') }}" class="hidden sm:inline text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">{{ Auth::user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Logout</button>
                    </form>
                </div>

                {{-- Mobile menu dropdown --}}
                <div x-show="mobileMenu" x-cloak @click.outside="mobileMenu = false"
                     class="sm:hidden absolute top-14 left-0 right-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-lg z-40 px-4 py-3 space-y-1">
                    <a href="{{ route('browse') }}" wire:navigate @click="mobileMenu = false"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('browse') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400' }}">
                        Browse
                    </a>
                    @auth
                    <a href="{{ route('teams') }}" wire:navigate @click="mobileMenu = false"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('teams', 'team.detail') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400' }}">
                        Teams
                    </a>
                    <a href="{{ route('settings') }}" wire:navigate @click="mobileMenu = false"
                       class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('settings') ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400' }}">
                        Settings
                    </a>
                    @endauth
                    @if($lastPrompt)
                    <a href="{{ $lastPrompt->workspaceUrl() }}" wire:navigate @click="mobileMenu = false"
                       class="block px-3 py-2 rounded-md text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 truncate"
                       title="{{ $lastPrompt->name }}">
                        Continue: {{ Str::limit($lastPrompt->name, 20) }}
                    </a>
                    @endif
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700 mt-2">
                        <a href="{{ route('profile.edit') }}" @click="mobileMenu = false"
                           class="block px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                            {{ Auth::user()->name }} — Profile
                        </a>
                    </div>
                </div>
            </nav>

            <main class="flex-1 overflow-hidden">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
