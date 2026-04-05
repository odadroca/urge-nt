<?php

namespace App\Http\Controllers\Api;

use App\Models\Prompt;
use App\Models\User;

trait ResolvesPrompts
{
    protected function resolvePrompt(string $username, string $promptSlug, ?\Illuminate\Http\Request $request = null): Prompt
    {
        $owner = User::where('slug', $username)->firstOrFail();
        $prompt = Prompt::where('created_by', $owner->id)
            ->where('slug', $promptSlug)
            ->firstOrFail();

        // Check visibility if request has an authenticated user
        if ($request && $request->user()) {
            $canSee = Prompt::visibleTo($request->user())
                ->where('id', $prompt->id)
                ->exists();
            if (!$canSee) {
                abort(404);
            }
        }

        return $prompt;
    }

    protected function authorizeOwnership(Prompt $prompt, \Illuminate\Http\Request $request): void
    {
        $user = $request->user();
        if ($prompt->created_by !== $user->id && !$user->isAdmin()) {
            abort(403, 'Only the prompt owner can perform this action.');
        }
    }
}
