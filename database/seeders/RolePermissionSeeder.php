<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'ADMINISTRADOR' => 'Administrador',
            'DIRECTOR_GENERAL' => 'Director General',
            'DIRECTOR' => 'Director (heredado)',
            'DIRECTOR_TRANSMISION' => 'Director de Transmisión',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'Director de Programación y Continuidad',
            'ENLACE_INSTITUCIONAL' => 'Enlace Institucional',
            'OPERADOR' => 'Operador (heredado)',
            'OPERADOR_TRANSMISION' => 'Operativo de Transmisión',
            'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'Operativo de Programación y Continuidad',
            'FISCALIZADOR' => 'Fiscalizador',
        ];

        foreach ($roles as $code => $name) {
            Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'active' => true]
            );
        }

        $permissions = [
            ['dashboard.view','Ver dashboard','dashboard'],
            ['users.manage','Administrar usuarios','administration'],
            ['roles.manage','Administrar roles','administration'],
            ['agencies.manage','Administrar dependencias','administration'],
            ['catalogs.manage','Administrar catálogos','administration'],
            ['settings.manage','Administrar configuración','administration'],
            ['logs.view','Consultar logs','audit'],
            ['intelligence.view','Ver Centro de Inteligencia','intelligence'],
            ['indicators.view','Ver indicadores','indicators'],
            ['direction.dashboard','Ver dashboard de dirección','direction'],
            ['direction.repository','Ver repositorio de su dirección','repository'],
            ['calendar.view','Ver calendario','calendar'],
            ['calendar.import','Adjuntar Excel final de pautas','calendar'],
            ['calendar.confirm','Confirmar importación de pautas','calendar'],
            ['calendar.reschedule','Reprogramar cargas','calendar'],
            ['templates.manage','Administrar plantillas','templates'],
            ['evidence.upload','Cargar evidencias','evidence'],
            ['evidence.review','Revisar evidencias','evidence'],
            ['repository.view','Consultar repositorio','repository'],
            ['repository.download','Descargar evidencias','repository'],
            ['scheduled_load.board','Consultar tablero Kanban de cargas','loads'],
            ['scheduled_load.review','Iniciar revisión institucional','closure'],
            ['scheduled_load.verify','Marcar verificaciones de cierre','closure'],
            ['scheduled_load.signature_package','Descargar expediente para firma','closure'],
            ['scheduled_load.upload_signed','Adjuntar documento firmado','closure'],
            ['scheduled_load.close','Validar y cerrar carga','closure'],
            ['scheduled_load.reopen','Reabrir carga cerrada','closure'],
            ['reports.view','Consultar reportes','reports'],
            ['reports.export','Exportar PDF y Excel','reports'],
            ['alerts.view','Consultar alertas','notifications'],
            ['operations.view','Ver Centro de Operaciones','operations'],
            ['operations.manage','Administrar operación','operations'],
            ['incidents.manage','Administrar incidentes','operations'],
            ['backups.view','Consultar respaldos','operations'],
        ];

        foreach ($permissions as [$code, $name, $module]) {
            Permission::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'module' => $module]
            );
        }

        $directorPermissions = [
            'direction.dashboard','direction.repository','indicators.view','reports.view',
            'repository.view','repository.download','calendar.view','alerts.view','scheduled_load.board',
        ];
        $operatorPermissions = [
            'dashboard.view','calendar.view','evidence.upload','repository.view',
            'repository.download','reports.view','alerts.view','scheduled_load.board',
        ];

        $map = [
            'DIRECTOR_GENERAL' => ['intelligence.view','indicators.view','reports.view','repository.view','scheduled_load.board'],
            'DIRECTOR' => $directorPermissions,
            'DIRECTOR_TRANSMISION' => $directorPermissions,
            'DIRECTOR_PROGRAMACION_CONTINUIDAD' => $directorPermissions,
            'ENLACE_INSTITUCIONAL' => [
                'dashboard.view','calendar.view','calendar.import','calendar.confirm','calendar.reschedule','scheduled_load.board',
                'templates.manage','evidence.review','repository.view','repository.download',
                'scheduled_load.review','scheduled_load.verify','scheduled_load.signature_package',
                'scheduled_load.upload_signed','scheduled_load.close','reports.view','reports.export',
                'alerts.view','indicators.view','operations.view','backups.view',
            ],
            'OPERADOR' => $operatorPermissions,
            'OPERADOR_TRANSMISION' => $operatorPermissions,
            'OPERADOR_PROGRAMACION_CONTINUIDAD' => $operatorPermissions,
            'FISCALIZADOR' => ['repository.view','repository.download','reports.view','reports.export','evidence.review'],
        ];

        $allPermissionIds = Permission::query()->pluck('id')->all();
        Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()
            ->permissions()->sync($allPermissionIds);

        foreach ($map as $roleCode => $codes) {
            $ids = Permission::query()->whereIn('code', $codes)->pluck('id')->all();
            Role::query()->where('code', $roleCode)->firstOrFail()
                ->permissions()->sync($ids);
        }
    }
}
