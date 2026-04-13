<x-layouts.public>
    <div class="min-h-screen flex items-center justify-center bg-gray-900 p-4">
        <div class="w-full max-w-md bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h1 class="text-xl font-bold text-indigo-400 text-center mb-2">URGE</h1>
            <h2 class="text-lg font-semibold text-gray-100 text-center mb-6">Authorize Application</h2>

            <div class="bg-gray-900 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-300 mb-2">
                    <span class="text-gray-500">Application:</span>
                    <span class="font-mono text-xs break-all">{{ $client_id }}</span>
                </p>
                <p class="text-sm text-gray-300">
                    <span class="text-gray-500">Requesting access to:</span>
                </p>
                <ul class="mt-2 space-y-1">
                    @foreach ($scopes as $s)
                        <li class="text-xs text-indigo-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $s }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <form method="POST" action="{{ url('/oauth/authorize') }}">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client_id }}">
                <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="hidden" name="state" value="{{ $state }}">
                <input type="hidden" name="code_challenge" value="{{ $code_challenge }}">
                <input type="hidden" name="code_challenge_method" value="{{ $code_challenge_method }}">
                <input type="hidden" name="resource" value="{{ $resource }}">

                <div class="flex gap-3">
                    <button type="submit" name="decision" value="deny"
                        class="flex-1 bg-gray-700 hover:bg-gray-600 text-gray-300 py-2 rounded-lg font-medium">
                        Deny
                    </button>
                    <button type="submit" name="decision" value="approve"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium">
                        Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.public>
