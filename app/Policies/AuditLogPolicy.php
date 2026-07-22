<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy extends BaseAccountPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->membership($user)?->isAdminTier() ?? false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->isAdminTier()
            && $auditLog->account_id !== null
            && (int) $auditLog->account_id === (int) $membership->account_id;
    }
}
