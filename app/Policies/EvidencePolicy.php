<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;
use App\Services\AccessScopeService;

class EvidencePolicy
{
    public function __construct(private readonly AccessScopeService $access) {}

    public function view(User $user, Evidence $evidence): bool
    {
        return $this->access->canAccessEvidence($user, $evidence);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('evidence.upload');
    }

    public function review(User $user, Evidence $evidence): bool
    {
        return $this->access->canReviewEvidence($user, $evidence);
    }
}
