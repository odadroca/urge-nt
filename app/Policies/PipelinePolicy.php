<?php

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;
use App\Services\AuthorizationService;

class PipelinePolicy
{
    public function view(User $user, Pipeline $pipeline): bool
    {
        return AuthorizationService::userOwnsPipeline($user, $pipeline);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return AuthorizationService::userOwnsPipeline($user, $pipeline);
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return AuthorizationService::userOwnsPipeline($user, $pipeline);
    }

    public function run(User $user, Pipeline $pipeline): bool
    {
        return AuthorizationService::userOwnsPipeline($user, $pipeline);
    }

    public function manageChannels(User $user, Pipeline $pipeline): bool
    {
        return AuthorizationService::userOwnsPipeline($user, $pipeline);
    }
}
