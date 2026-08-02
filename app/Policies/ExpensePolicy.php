<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManage() ?? false;
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManage()
            && $this->belongsToCurrentAccount($membership, $expense);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->membership($user)?->canManage() ?? false;
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManage()
            && $this->belongsToCurrentAccount($membership, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canManage()
            && $this->belongsToCurrentAccount($membership, $expense);
    }
}
