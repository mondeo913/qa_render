<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Console\Command;

class SigetQaUsers extends Command
{
    protected $signature = 'siget:qa-users {--password= : Password QA to assign to all managed QA users}';
    protected $description = 'Crea o sincroniza los usuarios de prueba QA de SIGET sin tocar otros usuarios.';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: env('SIGET_QA_PASSWORD', 'SigetQA_2026_Cambiar!'));

        $definitions = [
            ['ADMINISTRADOR', 'admin@siget.local', 'Administrador QA', null],
            ['DIRECTOR_GENERAL', 'director.general@siget.local', 'Director General QA', null],
            ['DIRECTOR_TRANSMISION', 'director@siget.local', 'Director de Transmisión QA', 'DIR_A'],
            ['DIRECTOR_PROGRAMACION_CONTINUIDAD', 'director.produccion@siget.local', 'Director de Programación y Continuidad QA', 'DIR_B'],
            ['ENLACE_INSTITUCIONAL', 'enlace@siget.local', 'Enlace Institucional QA', 'DIR_A'],
            ['OPERADOR_TRANSMISION', 'operador.monitoreo@siget.local', 'Operador de Transmisión QA', 'DIR_A'],
            ['OPERADOR_PROGRAMACION_CONTINUIDAD', 'operador.produccion@siget.local', 'Operador de Programación y Continuidad QA', 'DIR_B'],
            ['FISCALIZADOR', 'fiscalizador@siget.local', 'Fiscalizador QA', 'DIR_A'],
        ];

        $agency = \App\Models\ContractingAgency::query()->first();
        if (! $agency) {
            $this->error('No existe una dependencia/agencia QA. Ejecute primero: php artisan db:seed --class=AgencyTemplateSeeder --force');
            return self::FAILURE;
        }

        $units = $agency->units()->get()->keyBy('code');

        foreach ($definitions as [$roleCode, $email, $name, $unitCode]) {
            $role = Role::query()->where('code', $roleCode)->first();
            if (! $role) {
                $this->error("No existe el rol requerido: {$roleCode}");
                return self::FAILURE;
            }

            $unit = $unitCode ? $units->get($unitCode) : null;

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'role_id' => $role->id,
                    'contracting_agency_id' => $agency->id,
                    'organizational_unit_id' => $unit?->id,
                    'name' => $name,
                    'password' => $password,
                    'status' => 'ACTIVE',
                    'email_verified_at' => now(),
                    'metadata' => ['qa' => true, 'managed_by' => 'siget:qa-users'],
                ]
            );

            UserScope::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'contracting_agency_id' => $agency->id,
                    'organizational_unit_id' => $unit?->id,
                ],
                [
                    'can_read' => true,
                    'can_write' => in_array($roleCode, [
                        'ADMINISTRADOR',
                        'ENLACE_INSTITUCIONAL',
                        'OPERADOR_TRANSMISION',
                        'OPERADOR_PROGRAMACION_CONTINUIDAD',
                    ], true),
                ]
            );

            $this->line("OK {$email} [{$roleCode}]");
        }

        $this->newLine();
        $this->info('Usuarios QA sincronizados correctamente.');
        $this->line('Password QA: ' . $password);

        return self::SUCCESS;
    }
}
