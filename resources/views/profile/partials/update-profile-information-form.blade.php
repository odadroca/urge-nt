<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ __("Update your name and email address.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-4 space-y-4">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('name') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('email') <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-600 transition">
                Save
            </button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-green-600 dark:text-green-400">Saved.</p>
            @endif
        </div>
    </form>
</section>
