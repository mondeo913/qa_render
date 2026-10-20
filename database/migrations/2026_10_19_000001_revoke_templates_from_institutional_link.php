<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Eliminar únicamente la concesión heredada de Plantillas al rol Enlace.
        // La plantilla y el permiso siguen existiendo para el Administrador.
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')
            || !Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('code', 'ENLACE_INSTITUCIONAL')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'templates.manage')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }

    public function down(): void
    {
        // No restituir privilegios sobre plantillas de manera automática.
    }
};
