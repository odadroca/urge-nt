<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Pipeline;
use App\Models\Prompt;
use App\Models\Result;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Centralized authorization helpers shared by REST controllers and
 * MCP tool handlers. The Policy classes wrap these for use with
 * Laravel's Gate facade; MCP code paths (without a Request) call
 * the static helpers directly.
 *
 * Single source of truth for: prompt visibility, ownership, result
 * write-permission, pipeline ownership, and API-key prompt scoping.
 */
class AuthorizationService
{
    public static function userCanSeePrompt(User $user, Prompt $prompt): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return Prompt::visibleTo($user)->whereKey($prompt->id)->exists();
    }

    public static function userOwnsPrompt(User $user, Prompt $prompt): bool
    {
        return $user->isAdmin() || $prompt->created_by === $user->id;
    }

    public static function userCanSeeResult(User $user, Result $result): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (!$result->prompt_id) {
            return $result->created_by === $user->id;
        }

        return Prompt::visibleTo($user)->whereKey($result->prompt_id)->exists();
    }

    public static function userCanMutateResult(User $user, Result $result): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($result->created_by === $user->id) {
            return true;
        }

        // Prompt owner can manage results on their prompt
        $result->loadMissing('prompt');
        if ($result->prompt && $result->prompt->created_by === $user->id) {
            return true;
        }

        return false;
    }

    public static function userOwnsPipeline(User $user, Pipeline $pipeline): bool
    {
        return $user->isAdmin() || $pipeline->created_by === $user->id;
    }

    /**
     * A prompt-scoped API key may only address its allowlisted prompts.
     * An unscoped key (no rows in api_key_prompt) is unrestricted.
     */
    public static function apiKeyAllowsPrompt(?ApiKey $apiKey, Prompt $prompt): bool
    {
        if (!$apiKey) {
            return true;
        }

        if (!$apiKey->prompts()->exists()) {
            return true;
        }

        return $apiKey->prompts()->whereKey($prompt->id)->exists();
    }

    /**
     * Throws 403 if the incoming request is authenticated by a prompt-
     * scoped API key that does not allow this prompt.
     */
    public static function enforceApiKeyScope(Request $request, Prompt $prompt): void
    {
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey instanceof ApiKey && !self::apiKeyAllowsPrompt($apiKey, $prompt)) {
            abort(403, 'API key does not have access to this prompt.');
        }
    }
}
