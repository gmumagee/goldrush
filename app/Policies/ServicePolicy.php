<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canViewServiceRecords() ?? false;
    }

    public function view(User $user, Service $service): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canViewServiceRecords()
            && $this->belongsToCurrentAccount($membership, $service);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManage() ?? false;
    }

    public function update(User $user, Service $service): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canUpdateServiceRecords()
            && $this->belongsToCurrentAccount($membership, $service);
    }

    public function delete(User $user, Service $service): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $this->belongsToCurrentAccount($membership, $service)
            && (
                (int) $service->created_by_user_id === (int) $user->id
                || $membership->canManageAccountUsers()
            );
    }
}
