<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const PERMISSION = 'scheduled_load.board';

    private const ROLES = [
        'ADMINISTRADOR',
        'DIRECTOR_GENERAL',
        'DIRECTOR',
        'DIRECTOR_TRANSMISION',
        'DIRECTOR_PROGRAMACION_CONTINUIDAD',
        'ENLACE_INSTITUCIONAL',
        'OPERADOR',
        'OPERADOR_TRANSMISION',
        'OPERADOR_PROGRAMACION_CONTINUIDAD',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => self::PERMISSION],
            [
                'name' => 'Consultar tablero Kanban de cargas',
                'module' => 'loads',
                'description' => 'Permite consultar cargas programadas agrupadas por dependencia y etapa operativa.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')->where('code', self::PERMISSION)->value('id');
        $roleIds = DB::table('roles')->whereIn('code', self::ROLES)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('code', self::PERMISSION)->value('id');

        if ($permissionId && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('code', self::PERMISSION)->delete();
    }
};
