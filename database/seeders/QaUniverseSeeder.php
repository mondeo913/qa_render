<?php

namespace Database\Seeders;

use App\Models\CalendarImport;
use App\Models\ContractingAgency;
use App\Models\Role;
use App\Models\ScheduledLoad;
use App\Models\User;
use App\Models\UserScope;
use App\Services\CalendarImportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class QaUniverseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AgencyTemplateSeeder::class,
        ]);

        DB::transaction(function (): void {
            $agencies = ContractingAgency::query()
                ->whereIn('code', ['IMSS', 'IPAB'])
                ->with(['units', 'templates.requirements'])
                ->orderBy('id')
                ->get();

            if ($agencies->count() !== 2) {
                throw new \RuntimeException('El universo QA requiere las dependencias IMSS e IPAB.');
            }

            $this->cleanQaOperationalData();
            $users = $this->createQaUsers($agencies);
            $this->seedPautasThroughRealFlow($agencies, $users);
        });
    }

    private function cleanQaOperationalData(): void
    {
        // Primero identificamos las importaciones QA y sus cargas asociadas.
        // Así se limpian también universos QA antiguos que no tengan metadata.qa.
        $qaImportIds = CalendarImport::query()
            ->where('original_filename', 'like', 'Pauta_QA_%')
            ->pluck('id');

        if ($qaImportIds->isNotEmpty()) {
            ScheduledLoad::query()
                ->whereIn('calendar_import_id', $qaImportIds)
                ->delete();
        }

        ScheduledLoad::query()
            ->whereRaw("COALESCE(metadata->>'qa','false') = 'true'")
            ->delete();

        if ($qaImportIds->isNotEmpty()) {
            CalendarImport::query()->whereIn('id', $qaImportIds)->delete();
        }

        UserScope::query()
            ->whereHas('user', fn ($query) =>
                $query->whereRaw("COALESCE(metadata->>'qa','false') = 'true'")
            )
            ->delete();

        $canonicalEmails = [
            'admin@siget.local',
            'director.general@siget.local',
            'director@siget.local',
            'director.produccion@siget.local',
            'enlace@siget.local',
            'operador.monitoreo@siget.local',
            'operacion.programacion@siget.local',
            'fiscalizador@siget.local',
            'director.monitoreo.IPAB@siget.local',
            'director.produccion.IPAB@siget.local',
            'enlace.IPAB@siget.local',
            'operador.monitoreo.IPAB@siget.local',
            'operacion.programacion.IPAB@siget.local',
            'fiscalizador.IPAB@siget.local',
        ];

        $duplicateUsers = User::query()
            ->whereRaw("COALESCE(metadata->>'qa','false') = 'true'")
            ->whereNotIn('email', $canonicalEmails)
            ->get();

        $canonicalByRole = User::query()
            ->whereIn('email', $canonicalEmails)
            ->with('role')
            ->get()
            ->keyBy(fn (User $user) => $user->role?->code ?? 'ADMINISTRADOR');

        foreach ($duplicateUsers as $user) {
            $replacement = $canonicalByRole->get($user->role?->code) ?? $canonicalByRole->get('ADMINISTRADOR');
            if (!$replacement) {
                continue;
            }

            $references = DB::select(<<<'SQL'
                SELECT tc.table_name, kcu.column_name
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
                  AND tc.table_name NOT IN ('users','user_scopes')
            SQL);

            foreach ($references as $reference) {
                DB::table($reference->table_name)
                    ->where($reference->column_name, $user->id)
                    ->update([$reference->column_name => $replacement->id]);
            }

            $user->delete();
        }
    }

    private function createQaUsers($agencies): array
    {
        $password = env('SIGET_QA_PASSWORD', 'SigetQA_2026_Cambiar!');
        $users = [];

        $globalDefinitions = [
            ['ADMINISTRADOR', 'admin@siget.local', 'Administrador QA', null],
            ['DIRECTOR_GENERAL', 'director.general@siget.local', 'Director General QA', null],
        ];

        foreach ($globalDefinitions as [$roleCode, $email, $name, $unit]) {
            $users[$email] = $this->upsertQaUser($roleCode, $email, $name, $unit?->id, $password);
        }

        foreach ($agencies as $agency) {
            $dirTransmission = $agency->units->firstWhere('code', 'DIR_A');
            $dirProgramming = $agency->units->firstWhere('code', 'DIR_B');

            $definitions = [
                ['DIRECTOR_TRANSMISION', $agency->code === 'IMSS' ? 'director@siget.local' : 'director.monitoreo.IPAB@siget.local', "Director de Transmisión QA {$agency->code}", $dirTransmission],
                ['DIRECTOR_PROGRAMACION_CONTINUIDAD', $agency->code === 'IMSS' ? 'director.produccion@siget.local' : 'director.produccion.IPAB@siget.local', "Director de Programación y Continuidad QA {$agency->code}", $dirProgramming],
                ['ENLACE_INSTITUCIONAL', $agency->code === 'IMSS' ? 'enlace@siget.local' : 'enlace.IPAB@siget.local', "Enlace Institucional QA {$agency->code}", $dirTransmission],
                ['OPERADOR_TRANSMISION', $agency->code === 'IMSS' ? 'operador.monitoreo@siget.local' : 'operador.monitoreo.IPAB@siget.local', "Operador de Transmisión QA {$agency->code}", $dirTransmission],
                ['OPERADOR_PROGRAMACION_CONTINUIDAD', $agency->code === 'IMSS' ? 'operacion.programacion@siget.local' : 'operacion.programacion.IPAB@siget.local', "Operador de Programación y Continuidad QA {$agency->code}", $dirProgramming],
                ['FISCALIZADOR', $agency->code === 'IMSS' ? 'fiscalizador@siget.local' : 'fiscalizador.IPAB@siget.local', "Fiscalizador QA {$agency->code}", $dirTransmission],
            ];

            foreach ($definitions as [$roleCode, $email, $name, $unit]) {
                $users[$email] = $this->upsertQaUser($roleCode, $email, $name, $unit?->id, $password);
            }
        }

        return $users;
    }

    private function upsertQaUser(string $roleCode, string $email, string $name, ?int $unitId, string $password): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->id,
                'contracting_agency_id' => null,
                'organizational_unit_id' => $unitId,
                'name' => $name,
                'password' => $password,
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
                'metadata' => ['qa' => true, 'source' => 'QaUniverseSeeder'],
            ]
        );

        UserScope::query()->where('user_id', $user->id)->delete();

        return $user;
    }

    private function seedPautasThroughRealFlow($agencies, array $users): void
    {
        $importService = app(CalendarImportService::class);
        $admin = $users['admin@siget.local'];

        foreach ($agencies as $agency) {
            $workbook = $this->buildPautaWorkbook($agency->code, 2026);
            $tempPath = tempnam(sys_get_temp_dir(), 'siget-qa-pauta-').'.xlsx';

            try {
                (new Xlsx($workbook))->save($tempPath);

                $upload = new UploadedFile(
                    $tempPath,
                    "Pauta_QA_{$agency->code}_2026.xlsx",
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    UPLOAD_ERR_OK,
                    true
                );

                $import = $importService->uploadAndValidate($upload, $agency->id, $admin->id, 2026);
                $importService->confirm($import, $admin->id);
            } finally {
                @unlink($tempPath);
            }
        }
    }

    private function buildPautaWorkbook(string $agencyCode, int $year): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pauta QA');

        $months = [
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
        ];

        // Cinco fechas por dependencia; dos dependencias = 10 cargas totales.
        $targetDates = [
            [1, 15],
            [2, 19],
            [3, 16],
            [5, 14],
            [8, 18],
        ];

        $column = 15;
        $targetColumns = [];

        foreach ($months as $monthNumber => $monthName) {
            $daysInMonth = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;

            foreach (range(1, $daysInMonth) as $day) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->setCellValue($letter.'1', $monthName);
                $sheet->setCellValue($letter.'2', $day);

                if (in_array([$monthNumber, $day], $targetDates, true)) {
                    $targetColumns[] = $column;
                }

                $column++;
            }
        }

        $rows = [
            3 => [
                'Campaña institucional '.$agencyCode,
                'v1',
                'Nacional',
                'Canal Catorce',
                'Nacional',
                'SIGET',
                'Canal Catorce',
                'Barra institucional',
                'Nacional',
                '',
                '',
                'Contenido general',
                '08:00-23:59',
                'SPOT / 30 seg',
            ],
            4 => [
                'Campaña institucional '.$agencyCode,
                'v1',
                'Nacional',
                'Canal Catorce',
                'Nacional',
                'SIGET',
                'Canal Catorce',
                'Continuidad',
                'Nacional',
                '',
                '',
                'Promoción institucional',
                '08:00-23:59',
                'SPOT / 30 seg',
            ],
        ];

        foreach ($rows as $rowNumber => $values) {
            foreach ($values as $index => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $value);
            }
        }

        foreach ($targetColumns as $position => $targetColumn) {
            $letter = Coordinate::stringFromColumnIndex($targetColumn);
            $sheet->setCellValue($letter.'3', 'X');

            // Tres fechas tienen una segunda marca para probar la agrupación del importador.
            if (in_array($position, [1, 3, 4], true)) {
                $sheet->setCellValue($letter.'4', 'X');
            }
        }

        return $spreadsheet;
    }
}
