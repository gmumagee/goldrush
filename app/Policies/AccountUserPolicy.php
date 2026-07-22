<?php

namespace App\Policies;

use App\Models\AccountUser;
use App\Models\User;

class AccountUserPolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManageAccountUsers() ?? false;
    }

    public function view(User $user, AccountUser $accountUser): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && $this->belongsToCurrentAccount($membership, $accountUser);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManageAccountUsers() ?? false;
    }

    public function update(User $user, AccountUser $accountUser): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && $this->belongsToCurrentAccount($membership, $accountUser);
    }

    public function delete(User $user, AccountUser $accountUser): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && $this->belongsToCurrentAccount($membership, $accountUser);
    }

    public function resetPassword(User $user, AccountUser $accountUser): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && $this->belongsToCurrentAccount($membership, $accountUser)
            && (int) $accountUser->user_id !== (int) $user->id;
    }
}
