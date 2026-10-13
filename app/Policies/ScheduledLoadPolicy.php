<?php

namespace App\Policies;

use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\AccessScopeService;

class ScheduledLoadPolicy
{
    public function __construct(private readonly AccessScopeService $access) {}

    public function view(User $user, ScheduledLoad $load): bool
    {
        return $this->access->canAccessLoad($user, $load);
    }

    public function close(User $user, ScheduledLoad $load): bool
    {
        return in_array($user->role?->code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)
            && $user->hasPermission('scheduled_load.close')
            && $this->access->canAccessLoad($user, $load);
    }
    public function reopen(User $user, ScheduledLoad $load): bool
    {
        return in_array($user->role?->code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)
            && $user->hasPermission('scheduled_load.reopen')
            && $this->access->canAccessLoad($user, $load);
    }
    public function verify(User $user, ScheduledLoad $load): bool
    {
        return $this->institutionalAction($user, $load, 'scheduled_load.verify');
    }

    public function signaturePackage(User $user, ScheduledLoad $load): bool
    {
        return $this->institutionalAction($user, $load, 'scheduled_load.signature_package');
    }

    public function uploadSigned(User $user, ScheduledLoad $load): bool
    {
        return $this->institutionalAction($user, $load, 'scheduled_load.upload_signed');
    }

    private function institutionalAction(User $user, ScheduledLoad $load, string $permission): bool
    {
        return in_array($user->role?->code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)
            && $user->hasPermission($permission) && $this->access->canAccessLoad($user, $load);
    }
}
