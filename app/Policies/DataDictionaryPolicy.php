<?php

namespace App\Policies;

use App\Models\DataDictionary;
use App\Models\User;

class DataDictionaryPolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManageAccountUsers() ?? false;
    }

    public function view(User $user, DataDictionary $dataDictionary): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && ($dataDictionary->account_id === null || $this->belongsToCurrentAccount($membership, $dataDictionary));
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManageAccountUsers() ?? false;
    }

    public function update(User $user, DataDictionary $dataDictionary): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManageAccountUsers()
            && ($dataDictionary->account_id === null || $this->belongsToCurrentAccount($membership, $dataDictionary));
    }
}
