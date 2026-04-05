<section x-data="{ confirming: false }">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Delete Account') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Once deleted, all your data will be permanently removed.') }}
        </p>
    </header>

    <div class="mt-4">
        <button @click="confirming = true" x-show="!confirming"
                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
            Delete Account
        </button>

        <form method="post" action="{{ route('profile.destroy') }}" x-show="confirming" x-cloak class="space-y-4">
            @csrf
            @method('delete')

            <p class="text-sm text-red-600 dark:text-red-400">
                This action cannot be undone. Enter your password to confirm.
            </p>

            <div>
                <input name="password" type="password" placeholder="Your password"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-red-500 focus:ring-red-500">
                @error('password', 'userDeletion') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2">
                <button type="button" @click="confirming = false"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    Permanently Delete
                </button>
            </div>
        </form>
    </div>
</section>
