<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Update Password') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Use a strong, unique password to keep your account secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('current_password', 'updatePassword') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
            <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('password', 'updatePassword') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('password_confirmation', 'updatePassword') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                Update Password
            </button>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-green-600 dark:text-green-400">Password updated.</p>
            @endif
        </div>
    </form>
</section>
