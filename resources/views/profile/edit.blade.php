<x-app-layout>
    <div class="p-6 max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Profile</h1>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5">
                @include('profile.partials.update-password-form')
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-5">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
