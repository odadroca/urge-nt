<?php

namespace App\Policies;

use App\Models\Result;
use App\Models\User;
use App\Services\AuthorizationService;

class ResultPolicy
{
    public function view(User $user, Result $result): bool
    {
        return AuthorizationService::userCanSeeResult($user, $result);
    }

    public function update(User $user, Result $result): bool
    {
        return AuthorizationService::userCanMutateResult($user, $result);
    }

    public function delete(User $user, Result $result): bool
    {
        return AuthorizationService::userCanMutateResult($user, $result);
    }

    public function evaluate(User $user, Result $result): bool
    {
        return AuthorizationService::userCanSeeResult($user, $result);
    }
}
