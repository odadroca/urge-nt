<?php

namespace App\Http\Controllers\Api;

use App\Models\Prompt;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

trait ResolvesPrompts
{
    /**
     * Resolve a prompt by (username, slug), enforce visibility AND the
     * caller's API-key prompt-scope. Always fails closed: aborts 404 if
     * the prompt is not visible to the authenticated user, 403 if the
     * caller's API key is restricted and this prompt is not in scope.
     */
    protected function resolvePrompt(string $username, string $promptSlug, Request $request): Prompt
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $owner = User::where('slug', $username)->firstOrFail();
        $prompt = Prompt::where('created_by', $owner->id)
            ->where('slug', $promptSlug)
            ->firstOrFail();

        if (!AuthorizationService::userCanSeePrompt($user, $prompt)) {
            abort(404);
        }

        AuthorizationService::enforceApiKeyScope($request, $prompt);

        return $prompt;
    }

    protected function authorizeOwnership(Prompt $prompt, Request $request): void
    {
        $user = $request->user();
        if (!AuthorizationService::userOwnsPrompt($user, $prompt)) {
            abort(403, 'Only the prompt owner can perform this action.');
        }
    }
}
