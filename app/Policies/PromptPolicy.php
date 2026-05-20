<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;
use App\Services\AuthorizationService;

class PromptPolicy
{
    public function view(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userCanSeePrompt($user, $prompt);
    }

    public function update(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userOwnsPrompt($user, $prompt);
    }

    public function delete(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userOwnsPrompt($user, $prompt);
    }

    public function share(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userOwnsPrompt($user, $prompt);
    }

    public function pin(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userOwnsPrompt($user, $prompt);
    }

    public function archive(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userOwnsPrompt($user, $prompt);
    }

    /**
     * Writing a Result onto a prompt is allowed for anyone who can see
     * the prompt (owner, team members, admin). Read-write parity with
     * the existing UX.
     */
    public function writeResult(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userCanSeePrompt($user, $prompt);
    }

    public function run(User $user, Prompt $prompt): bool
    {
        return AuthorizationService::userCanSeePrompt($user, $prompt);
    }
}
