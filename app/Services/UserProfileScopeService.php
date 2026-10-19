<?php

namespace App\Services;

use App\Models\{ContractingAgency, User};

final class UserProfileScopeService
{
    /** Called only after an administrator explicitly selects the general profile. */
    public function apply(User $user, bool $general, bool $refresh = false): void
    {
        $metadata = $user->metadata ?? [];
        $before = $metadata['standard_profile'] ?? null;
        if (!$refresh && (($general && $before === $user->role->code) || (!$general && $before === null))) return;
        unset($metadata['standard_profile']);
        if ($general) $metadata['standard_profile'] = $user->role->code;
        $user->update(['metadata' => $metadata]);
        $user->scopes()->delete();
        $user->tokens()->delete();
        app(AuditService::class)->record('user.scope_profile_changed', $user, ['profile' => $before], ['general_profile' => $general, 'role' => $user->role->code]);
        if (!$general) return;
        foreach (ContractingAgency::where('active', true)->get() as $agency) {
            app(AgencyWorkflowService::class)->provision($agency);
        }
    }
}
