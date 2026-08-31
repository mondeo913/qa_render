<?php

namespace Database\Seeders;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QaUserScopeCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SIGET_QA_PASSWORD', 'SigetQA_2026_Cambiar!');

        $agency = ContractingAgency::query()
            ->with('units')
            ->orderBy('id')
            ->first();

        $dirTransmission = $agency?->units?->firstWhere('code', 'DIR_A');
        $dirProgramming = $agency?->units?->firstWhere('code', 'DIR_B');

        $definitions = [
            ['ADMINISTRADOR', 'admin@siget.local', 'Administrador QA', null],
            ['DIRECTOR_GENERAL', 'director.general@siget.local', 'Director General QA', null],
            ['DIRECTOR_TRANSMISION', 'director@siget.local', 'Director de Transmisión QA', $dirTransmission?->id],
            ['DIRECTOR_PROGRAMACION_CONTINUIDAD', 'director.produccion@siget.local', 'Director de Programación y Continuidad QA', $dirProgramming?->id],
            ['ENLACE_INSTITUCIONAL', 'enlace@siget.local', 'Enlace Institucional QA', $dirTransmission?->id],
            ['OPERADOR_TRANSMISION', 'operador.monitoreo@siget.local', 'Operador de Transmisión QA', $dirTransmission?->id],
            ['OPERADOR_PROGRAMACION_CONTINUIDAD', 'operacion.programacion@siget.local', 'Operador de Programación y Continuidad QA', $dirProgramming?->id],
            ['FISCALIZADOR', 'fiscalizador@siget.local', 'Fiscalizador QA', $dirTransmission?->id],
        ];

        DB::transaction(function () use ($definitions, $password) {
            $canonical = [];

            foreach ($definitions as [$roleCode, $email, $name, $unitId]) {
                $role = Role::query()->where('code', $roleCode)->firstOrFail();

                $canonicalUser = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'role_id' => $role->id,
                        // En QA ningún usuario tiene dependencia asignada.
                        'contracting_agency_id' => null,
                        // La Dirección puede mantenerse como referencia funcional del usuario.
                        'organizational_unit_id' => $unitId,
                        'name' => $name,
                        'password' => $password,
                        'status' => 'ACTIVE',
                        'email_verified_at' => now(),
                        'metadata' => ['qa' => true],
                    ]
                );

                $canonical[$roleCode] = $canonicalUser;
            }

            // El QA no utiliza alcances UserScope. Cualquier alcance histórico
            // por dependencia o Dirección queda eliminado.
            UserScope::query()->delete();

            $canonicalIds = collect($canonical)
                ->map(fn (User $user) => $user->id)
                ->values()
                ->all();

            $references = DB::select(<<<'SQL'
                SELECT
                    tc.table_name,
                    kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.constraint_schema = kcu.constraint_schema
                JOIN information_schema.constraint_column_usage ccu
                  ON tc.constraint_name = ccu.constraint_name
                 AND tc.constraint_schema = ccu.constraint_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND ccu.table_name = 'users'
                  AND ccu.column_name = 'id'
                  AND tc.table_name NOT IN ('users', 'user_scopes')
            SQL);

            $roleFallback = [
                'ADMINISTRADOR' => 'ADMINISTRADOR',
                'DIRECTOR_GENERAL' => 'DIRECTOR_GENERAL',
                'DIRECTOR' => 'DIRECTOR_TRANSMISION',
                'DIRECTOR_TRANSMISION' => 'DIRECTOR_TRANSMISION',
                'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'DIRECTOR_PROGRAMACION_CONTINUIDAD',
                'ENLACE_INSTITUCIONAL' => 'ENLACE_INSTITUCIONAL',
                'OPERADOR' => 'OPERADOR_TRANSMISION',
                'OPERADOR_TRANSMISION' => 'OPERADOR_TRANSMISION',
                'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'OPERADOR_PROGRAMACION_CONTINUIDAD',
                'FISCALIZADOR' => 'FISCALIZADOR',
            ];

            User::query()
                ->whereNotIn('id', $canonicalIds)
                ->with('role')
                ->orderBy('id')
                ->each(function (User $user) use ($canonical, $roleFallback, $references) {
                    $roleCode = $roleFallback[$user->role?->code ?? ''] ?? 'ADMINISTRADOR';
                    $replacement = $canonical[$roleCode] ?? $canonical['ADMINISTRADOR'];

                    foreach ($references as $reference) {
                        $table = str_replace('"', '', (string) $reference->table_name);
                        $column = str_replace('"', '', (string) $reference->column_name);

                        if ($table === 'users' || $table === 'user_scopes') {
                            continue;
                        }

                        DB::table($table)
                            ->where($column, $user->id)
                            ->update([$column => $replacement->id]);
                    }

                    $user->delete();
                });

            // Garantía final: ningún usuario QA queda con dependencia.
            User::query()
                ->whereIn('id', $canonicalIds)
                ->update(['contracting_agency_id' => null]);
        });
    }
}
