<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    public function create(User $user, ?string $serviceType = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        if ($membership === null) {
            return false;
        }

        if ($serviceType === null || trim($serviceType) === '') {
            return $membership->canCreateMaintenanceServices();
        }

        if ($membership->canCreateLocationServices()) {
            return true;
        }

        return strcasecmp(trim($serviceType), Service::TYPE_MAINTENANCE) === 0
            && $membership->canCreateMaintenanceServices();
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

    public function finalize(User $user, Service $service): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $membership->canFinalizeServices()
            && $this->belongsToCurrentAccount($membership, $service);
    }

    public function delete(User $user, Service $service): Response
    {
        if ($service->isMaintenanceService() && $service->isServiceClosed()) {
            return Response::deny('Closed maintenance services cannot be deleted.');
        }

        if ($this->isSuperAdmin($user)) {
            return Response::allow();
        }

        $membership = $this->membership($user);

        return $membership !== null
            && $this->belongsToCurrentAccount($membership, $service)
            && (
                (int) $service->created_by_user_id === (int) $user->id
                || $membership->canManageAccountUsers()
            )
                ? Response::allow()
                : Response::deny();
    }
}
