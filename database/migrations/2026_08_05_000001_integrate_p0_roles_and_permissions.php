<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const NEW_ROLES = [
        'DIRECTOR_TRANSMISION' => 'Director de Transmisión',
        'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'Director de Programación y Continuidad',
        'OPERADOR_TRANSMISION' => 'Operativo de Transmisión',
        'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'Operativo de Programación y Continuidad',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach (self::NEW_ROLES as $code => $name) {
            DB::table('roles')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $directorCodes = [
            'direction.dashboard','direction.repository','indicators.view','reports.view',
            'repository.view','repository.download','calendar.view','alerts.view',
        ];
        $operatorCodes = [
            'dashboard.view','calendar.view','evidence.upload','repository.view',
            'repository.download','reports.view','alerts.view',
        ];

        $this->syncPermissions('DIRECTOR_TRANSMISION', $directorCodes);
        $this->syncPermissions('DIRECTOR_PROGRAMACION_CONTINUIDAD', $directorCodes);
        $this->syncPermissions('OPERADOR_TRANSMISION', $operatorCodes);
        $this->syncPermissions('OPERADOR_PROGRAMACION_CONTINUIDAD', $operatorCodes);

        // El director heredado conserva supervisión, pero pierde revisión operativa.
        $reviewPermissionId = DB::table('permissions')->where('code', 'evidence.review')->value('id');
        $directorRoleIds = DB::table('roles')
            ->whereIn('code', ['DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'])
            ->pluck('id');

        if ($reviewPermissionId && $directorRoleIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('permission_id', $reviewPermissionId)
                ->whereIn('role_id', $directorRoleIds)
                ->delete();
        }

        if (Schema::hasTable('users') && Schema::hasTable('organizational_units')) {
            $this->migrateUsersByUnit('DIRECTOR', 'DIR_A', 'DIRECTOR_TRANSMISION');
            $this->migrateUsersByUnit('DIRECTOR', 'DIR_B', 'DIRECTOR_PROGRAMACION_CONTINUIDAD');
            $this->migrateUsersByUnit('OPERADOR', 'DIR_A', 'OPERADOR_TRANSMISION');
            $this->migrateUsersByUnit('OPERADOR', 'DIR_B', 'OPERADOR_PROGRAMACION_CONTINUIDAD');
        }

        if (Schema::hasTable('template_requirements') && Schema::hasTable('organizational_units')) {
            $this->migrateRequirementRole('DIR_A', 'OPERADOR_TRANSMISION');
            $this->migrateRequirementRole('DIR_B', 'OPERADOR_PROGRAMACION_CONTINUIDAD');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (Schema::hasTable('users')) {
            $this->moveUsersBack('DIRECTOR_TRANSMISION', 'DIRECTOR');
            $this->moveUsersBack('DIRECTOR_PROGRAMACION_CONTINUIDAD', 'DIRECTOR');
            $this->moveUsersBack('OPERADOR_TRANSMISION', 'OPERADOR');
            $this->moveUsersBack('OPERADOR_PROGRAMACION_CONTINUIDAD', 'OPERADOR');
        }

        if (Schema::hasTable('template_requirements')) {
            DB::table('template_requirements')
                ->whereIn('responsible_role_code', [
                    'OPERADOR_TRANSMISION',
                    'OPERADOR_PROGRAMACION_CONTINUIDAD',
                ])
                ->update(['responsible_role_code' => 'OPERADOR']);
        }

        $roleIds = DB::table('roles')->whereIn('code', array_keys(self::NEW_ROLES))->pluck('id');
        if (Schema::hasTable('permission_role') && $roleIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
        }
        DB::table('roles')->whereIn('code', array_keys(self::NEW_ROLES))->delete();
    }

    private function syncPermissions(string $roleCode, array $permissionCodes): void
    {
        $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
        if (!$roleId || !Schema::hasTable('permission_role')) {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        $permissionIds = DB::table('permissions')->whereIn('code', $permissionCodes)->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function migrateUsersByUnit(string $fromRole, string $unitCode, string $toRole): void
    {
        $fromRoleId = DB::table('roles')->where('code', $fromRole)->value('id');
        $toRoleId = DB::table('roles')->where('code', $toRole)->value('id');
        $unitIds = DB::table('organizational_units')->where('code', $unitCode)->pluck('id');

        if ($fromRoleId && $toRoleId && $unitIds->isNotEmpty()) {
            DB::table('users')
                ->where('role_id', $fromRoleId)
                ->whereIn('organizational_unit_id', $unitIds)
                ->update(['role_id' => $toRoleId, 'updated_at' => now()]);
        }
    }

    private function moveUsersBack(string $fromRole, string $toRole): void
    {
        $fromRoleId = DB::table('roles')->where('code', $fromRole)->value('id');
        $toRoleId = DB::table('roles')->where('code', $toRole)->value('id');

        if ($fromRoleId && $toRoleId) {
            DB::table('users')->where('role_id', $fromRoleId)
                ->update(['role_id' => $toRoleId, 'updated_at' => now()]);
        }
    }

    private function migrateRequirementRole(string $unitCode, string $roleCode): void
    {
        $unitIds = DB::table('organizational_units')->where('code', $unitCode)->pluck('id');

        if ($unitIds->isNotEmpty()) {
            DB::table('template_requirements')
                ->whereIn('responsible_unit_id', $unitIds)
                ->update(['responsible_role_code' => $roleCode, 'updated_at' => now()]);
        }
    }
};
