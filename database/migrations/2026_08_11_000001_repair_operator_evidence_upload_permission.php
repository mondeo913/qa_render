<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('code', 'evidence.upload')
            ->value('id');

        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'evidence.upload',
                'name' => 'Cargar evidencias',
                'module' => 'evidence',
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('code', [
                'OPERADOR',
                'OPERADOR_TRANSMISION',
                'OPERADOR_PROGRAMACION_CONTINUIDAD',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('code', 'evidence.upload')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('code', [
                'OPERADOR',
                'OPERADOR_TRANSMISION',
                'OPERADOR_PROGRAMACION_CONTINUIDAD',
            ])
            ->pluck('id');

        if ($permissionId && $roleIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
