<?php

namespace App\Policies;

use App\Models\AccountUser;
use App\Models\User;
use App\Support\CurrentAccountMembershipResolver;

abstract class BaseAccountPolicy
{
    public function __construct(protected CurrentAccountMembershipResolver $membershipResolver)
    {
    }

    protected function membership(User $user): ?AccountUser
    {
        return $this->membershipResolver->forUser($user);
    }

    protected function belongsToCurrentAccount(AccountUser $membership, mixed $model): bool
    {
        return isset($model->account_id) && (int) $model->account_id === (int) $membership->account_id;
    }
}
