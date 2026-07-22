<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class OperationalEntityPolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canAccessOperationalRecords() ?? false;
    }

    public function view(User $user, mixed $model): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && (
                $membership->canAccessOperationalRecords()
                || ($membership->isTechnician() && $model instanceof Location)
            )
            && $this->belongsToCurrentAccount($membership, $model);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManage() ?? false;
    }

    public function update(User $user, mixed $model): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManage()
            && $this->belongsToCurrentAccount($membership, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canDelete()
            && $this->belongsToCurrentAccount($membership, $model);
    }
}
