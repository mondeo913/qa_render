<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Evidence;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class AccessScopeService
{
    public function scopeLoads(Builder $query, User $user): Builder
    {
        $role = $user->role?->code;

        if (in_array($role, [RoleCode::ADMINISTRADOR->value, RoleCode::DIRECTOR_GENERAL->value], true)) {
            return $query;
        }

        if ($role === RoleCode::ENLACE_INSTITUCIONAL->value) {
            $agencyIds = $this->readableAgencyIds($user);
            return $agencyIds !== []
                ? $query->whereIn('scheduled_loads.contracting_agency_id', $agencyIds)
                : $query->whereRaw('1 = 0');
        }

        if (RoleCode::isDirectionDirector($role)) {
            $unitIds = $this->readableUnitIds($user);
            $agencyIds = $this->readableAgencyIds($user);

            return $agencyIds === [] || $unitIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('scheduled_loads.contracting_agency_id', $agencyIds)
                    ->whereHas('deliverables', fn (Builder $deliverables) =>
                        $deliverables->whereIn('organizational_unit_id', $unitIds)
                    );
        }

        if (RoleCode::isOperator($role)) {
            $unitIds = $this->readableUnitIds($user);
            $agencyIds = $this->readableAgencyIds($user);

            return $agencyIds === [] || $unitIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('scheduled_loads.contracting_agency_id', $agencyIds)
                    ->whereHas('deliverables', function (Builder $deliverables) use ($user, $unitIds) {
                        $deliverables->whereIn('organizational_unit_id', $unitIds)
                            ->where(function (Builder $responsibility) use ($user) {
                                $responsibility->whereNull('responsible_user_id')
                                    ->orWhere('responsible_user_id', $user->id);
                            });
                    });
        }

        if ($role === RoleCode::FISCALIZADOR->value) {
            return $query->whereHas('reviewAssignments', fn (Builder $assignments) =>
                $assignments->where('fiscalizador_id', $user->id)
                    ->where('active', true)
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function accessibleUnitIds(User $user): array
    {
        if (!RoleCode::isDirectionDirector($user->role?->code) && !RoleCode::isOperator($user->role?->code)) {
            return [];
        }

        return $this->readableUnitIds($user);
    }

    public function scopeDeliverables(Builder|Relation $query, User $user): Builder|Relation
    {
        $unitIds = $this->accessibleUnitIds($user);
        if ($unitIds !== []) {
            $query->whereIn('scheduled_load_deliverables.organizational_unit_id', $unitIds);
        }

        if (RoleCode::isOperator($user->role?->code)) {
            $query->where(function (Builder $responsibility) use ($user) {
                $responsibility->whereNull('scheduled_load_deliverables.responsible_user_id')
                    ->orWhere('scheduled_load_deliverables.responsible_user_id', $user->id);
            });
        }

        return $query;
    }

    public function canAccessLoad(User $user, ScheduledLoad $load): bool
    {
        return $this->scopeLoads(
            ScheduledLoad::query()->whereKey($load->id),
            $user
        )->exists();
    }

    public function canAccessDeliverable(User $user, ScheduledLoadDeliverable $deliverable): bool
    {
        if (!$this->canAccessLoad($user, $deliverable->scheduledLoad)) {
            return false;
        }

        if (!RoleCode::isOperator($user->role?->code)) {
            return true;
        }

        return in_array(
            (int) $deliverable->organizational_unit_id,
            $this->readableUnitIds($user),
            true
        ) && ($deliverable->responsible_user_id === null
            || $deliverable->responsible_user_id === $user->id);
    }

    public function canAccessEvidence(User $user, Evidence $evidence): bool
    {
        return $this->canAccessDeliverable($user, $evidence->deliverable);
    }

    public function canReviewEvidence(User $user, Evidence $evidence): bool
    {
        if (!$user->hasPermission('evidence.review')) {
            return false;
        }

        if (!$this->canAccessLoad($user, $evidence->scheduledLoad)) {
            return false;
        }

        return in_array(
            $user->role?->code,
            [
                RoleCode::ADMINISTRADOR->value,
                RoleCode::DIRECTOR_GENERAL->value,
                RoleCode::DIRECTOR->value,
                RoleCode::DIRECTOR_TRANSMISION->value,
                RoleCode::DIRECTOR_PROGRAMACION_CONTINUIDAD->value,
                RoleCode::ENLACE_INSTITUCIONAL->value,
                RoleCode::FISCALIZADOR->value,
            ],
            true
        );
    }

    /** @return list<int> */
    private function readableAgencyIds(User $user): array
    {
        $role = $user->role?->code;

        // Un Director de Dirección consolida todas las dependencias que
        // tengan cargada la misma Dirección (DIR_A o DIR_B), sin acceder
        // nunca a la otra Dirección.
        if (RoleCode::isDirectionDirector($role) && $user->organizational_unit_id) {
            $directionCode = OrganizationalUnit::query()
                ->where('organizational_units.id', (int) $user->organizational_unit_id)
                ->value('code');

            if ($directionCode) {
                return OrganizationalUnit::query()
                    ->where('active', true)
                    ->where('unit_type', 'DIRECTION')
                    ->where('code', $directionCode)
                    ->whereNotNull('contracting_agency_id')
                    ->distinct()
                    ->pluck('contracting_agency_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            return [];
        }

        $agencyIds = $user->scopes()
            ->where('can_read', true)
            ->whereNotNull('contracting_agency_id')
            ->pluck('contracting_agency_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($user->contracting_agency_id) {
            $agencyIds[] = (int) $user->contracting_agency_id;
        }

        // En QA los usuarios no guardan alcance de dependencia. Una Dirección
        // sí identifica de forma estructural a qué dependencia pertenece.
        if ($user->organizational_unit_id) {
            $unitAgencyId = OrganizationalUnit::query()
                ->whereKey($user->organizational_unit_id)
                ->value('contracting_agency_id');

            if ($unitAgencyId) {
                $agencyIds[] = (int) $unitAgencyId;
            }
        }

        return array_values(array_unique(array_filter($agencyIds)));
    }

    /** @return list<int> */
    private function readableUnitIds(User $user): array
    {
        $role = $user->role?->code;

        if (RoleCode::isDirectionDirector($role) && $user->organizational_unit_id) {
            $root = OrganizationalUnit::query()
                ->where('organizational_units.id', (int) $user->organizational_unit_id)
                ->first();

            if (!$root) {
                return [];
            }

            // Una Dirección es una unidad estructural replicada por dependencia.
            // El Director consolida todas las raíces con el mismo código y sus
            // descendientes, pero nunca cruza hacia DIR_A <-> DIR_B.
            $rootUnits = OrganizationalUnit::query()
                ->where('active', true)
                ->where('unit_type', 'DIRECTION')
                ->where('code', $root->code)
                ->get(['id', 'contracting_agency_id']);

            $unitIds = $rootUnits->pluck('id')->map(fn ($id) => (int) $id)->all();
            $pending = $unitIds;
            $agencyIds = $rootUnits->pluck('contracting_agency_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            while ($pending !== []) {
                $children = OrganizationalUnit::query()
                    ->whereIn('parent_id', $pending)
                    ->whereIn('contracting_agency_id', $agencyIds)
                    ->where('active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $children = array_values(array_diff($children, $unitIds));
                if ($children === []) {
                    break;
                }

                $unitIds = array_merge($unitIds, $children);
                $pending = $children;
            }

            return array_values(array_unique($unitIds));
        }

        if (RoleCode::isOperator($role) && $user->organizational_unit_id) {
            $unitIds = $user->scopes()
                ->where('can_read', true)
                ->whereNotNull('organizational_unit_id')
                ->pluck('organizational_unit_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $unitIds[] = (int) $user->organizational_unit_id;

            return array_values(array_unique($unitIds));
        }

        $unitIds = $user->scopes()
            ->where('can_read', true)
            ->whereNotNull('organizational_unit_id')
            ->pluck('organizational_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($user->organizational_unit_id) {
            $unitIds[] = (int) $user->organizational_unit_id;
        }

        return array_values(array_unique($unitIds));
    }
}
