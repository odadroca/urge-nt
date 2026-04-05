@props(['title' => 'Shared Collection'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }} - URGE</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script>
            (function() {
                document.documentElement.classList.toggle('dark',
                    localStorage.theme === 'dark' ||
                    (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)
                );
            })();
        </script>

        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div class="min-h-screen flex flex-col">
            <header class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 h-12 flex items-center justify-between shrink-0">
                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400 tracking-tight">URGE</span>
                <button
                    onclick="(function(b){var d=localStorage.theme!=='dark';localStorage.theme=d?'dark':'light';document.documentElement.classList.toggle('dark',d)})()"
                    class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    title="Toggle dark mode">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="border-t border-gray-200 dark:border-gray-700 py-6 text-center text-xs text-gray-400 dark:text-gray-500">
                Shared via <span class="font-semibold text-indigo-600 dark:text-indigo-400">URGE</span>
            </footer>
        </div>
    </body>
</html>
