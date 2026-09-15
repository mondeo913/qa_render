<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissionDefinitions = [
            ['code' => 'evidence.review', 'name' => 'Revisar evidencias', 'module' => 'evidence'],
            ['code' => 'scheduled_load.review', 'name' => 'Iniciar revisión institucional', 'module' => 'closure'],
            ['code' => 'scheduled_load.verify', 'name' => 'Marcar verificaciones de cierre', 'module' => 'closure'],
            ['code' => 'scheduled_load.signature_package', 'name' => 'Descargar expediente para firma', 'module' => 'closure'],
            ['code' => 'scheduled_load.upload_signed', 'name' => 'Adjuntar documento firmado', 'module' => 'closure'],
            ['code' => 'scheduled_load.close', 'name' => 'Validar y cerrar carga', 'module' => 'closure'],
            ['code' => 'scheduled_load.reopen', 'name' => 'Reabrir carga cerrada', 'module' => 'closure'],
        ];

        foreach ($permissionDefinitions as $definition) {
            Permission::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'module' => $definition['module'],
                ]
            );
        }

        $roles = [
            'DIRECTOR_GENERAL',
            'DIRECTOR',
            'DIRECTOR_TRANSMISION',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD',
            'ENLACE_INSTITUCIONAL',
        ];

        $permissionIds = Permission::query()
            ->whereIn('code', array_column($permissionDefinitions, 'code'))
            ->pluck('id')
            ->all();

        foreach ($roles as $roleCode) {
            $role = Role::query()->where('code', $roleCode)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('code', [
                'evidence.review',
                'scheduled_load.review',
                'scheduled_load.verify',
                'scheduled_load.signature_package',
                'scheduled_load.upload_signed',
                'scheduled_load.close',
                'scheduled_load.reopen',
            ])
            ->pluck('id')
            ->all();

        foreach (['DIRECTOR_GENERAL', 'DIRECTOR', 'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD', 'ENLACE_INSTITUCIONAL'] as $roleCode) {
            $role = Role::query()->where('code', $roleCode)->first();
            if ($role && $permissionIds !== []) {
                $role->permissions()->detach($permissionIds);
            }
        }
    }
};
