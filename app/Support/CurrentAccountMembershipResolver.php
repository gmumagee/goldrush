<?php

namespace App\Support;

use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class CurrentAccountMembershipResolver
{
    public function forUser(?Authenticatable $user): ?AccountUser
    {
        if (! $user instanceof User) {
            return null;
        }

        $request = request();

        if (! $request instanceof Request) {
            return null;
        }

        $accountId = (int) (Tenancy::currentAccountId($request) ?? 0);

        if ($accountId <= 0) {
            return null;
        }

        $cacheKey = sprintf('_current_account_membership.%d.%d', $user->id, $accountId);

        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $membership = AccountUser::query()
            ->where('account_id', $accountId)
            ->where('user_id', $user->id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->first();

        $request->attributes->set($cacheKey, $membership);

        return $membership;
    }

    public function requireForUser(?Authenticatable $user): AccountUser
    {
        $membership = $this->forUser($user);

        abort_if(! $membership, 403);

        return $membership;
    }
}
