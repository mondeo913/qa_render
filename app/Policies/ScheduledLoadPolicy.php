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
        return $user->hasPermission('scheduled_load.close')
            && $this->access->canAccessLoad($user, $load);
    }
}
