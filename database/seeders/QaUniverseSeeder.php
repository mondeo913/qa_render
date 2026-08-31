<?php

namespace Database\Seeders;

use App\Models\CalendarImport;
use App\Models\ContractingAgency;
use App\Models\Evidence;
use App\Models\EvidenceReview;
use App\Models\OrganizationalUnit;
use App\Models\RepositoryFolder;
use App\Models\Role;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use App\Models\UserScope;
use App\Services\CalendarImportService;
use App\Services\HorizontalPautaParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $qaLoads = ScheduledLoad::query()
            ->whereRaw("COALESCE(metadata->>'qa','false') = 'true'")
            ->pluck('id');

        if ($qaLoads->isNotEmpty()) {
            ScheduledLoad::query()->whereIn('id', $qaLoads)->delete();
        }

        CalendarImport::query()
            ->where('original_filename', 'like', 'Pauta_QA_%')
            ->delete();

        UserScope::query()->whereHas('user', function ($query): void {
            $query->whereRaw("COALESCE(metadata->>'qa','false') = 'true'");
        })->delete();

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

        if ($duplicateUsers->isEmpty()) {
            return;
        }

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
                  AND tc.table_name NOT IN ('users', 'user_scopes')
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

            $suffix = $agency->code === 'IMSS' ? '' : '.IPAB';
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
        $evidenceService = app(\App\Services\EvidenceService::class);

        foreach ($agencies as $agency) {
            $admin = $users['admin@siget.local'];
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

                $loads = ScheduledLoad::query()
                    ->where('calendar_import_id', $import->id)
                    ->orderBy('effective_open_at')
                    ->get();

                $this->applyCredibleStatuses($loads, $agency, $users, $evidenceService);
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
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        ];

        $column = 15;
        $days = [3, 10, 17, 24, 28];
        foreach ($months as $monthNumber => $monthName) {
            $daysInMonth = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;
            foreach (range(1, $daysInMonth) as $day) {
                $sheet->setCellValueByColumnAndRow($column, 1, $monthName);
                $sheet->setCellValueByColumnAndRow($column, 2, $day);
                $column++;
            }
        }

        $rows = [
            3 => ['Campaña institucional '.$agencyCode, 'v1', 'Nacional', 'Canal Catorce', 'Nacional', 'SIGET', 'Canal Catorce', 'Barra institucional', 'Nacional', '', '', 'Contenido general', '08:00-23:59', 'SPOT / 30 seg'],
            4 => ['Campaña institucional '.$agencyCode, 'v1', 'Nacional', 'Canal Catorce', 'Nacional', 'SIGET', 'Canal Catorce', 'Continuidad', 'Nacional', '', '', 'Promoción institucional', '08:00-23:59', 'SPOT / 30 seg'],
        ];

        foreach ($rows as $rowNumber => $values) {
            foreach ($values as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, $rowNumber, $value);
            }
        }

        $cursor = 15;
        foreach (array_keys($months) as $monthNumber) {
            $daysInMonth = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;
            foreach (range(1, $daysInMonth) as $day) {
                if (in_array($day, $days, true)) {
                    $sheet->setCellValueByColumnAndRow($cursor, 3, 'X');
                    if ($day === 10 || $day === 24 || $day === 28) {
                        $sheet->setCellValueByColumnAndRow($cursor, 4, 'X');
                    }
                }
                $cursor++;
            }
        }

        // Una marca adicional para el 31 de agosto permite un registro del día actual;
        // las suspensiones institucionales la harán reprogramable sin alterar el flujo.
        $augustStart = 15;
        foreach (array_slice(array_keys($months), 0, 7) as $m) {
            $augustStart += CarbonImmutable::create($year, $m, 1)->daysInMonth;
        }
        $sheet->setCellValueByColumnAndRow($augustStart + 30, 3, 'X');

        return $spreadsheet;
    }

    private function applyCredibleStatuses($loads, ContractingAgency $agency, array $users, $evidenceService): void
    {
        $statusPlan = [
            'VALIDADO_Y_CERRADO', 'VALIDADA', 'VENCIDA', 'VALIDADO_Y_CERRADO', 'VALIDADA',
            'ENTREGADA', 'OBSERVADA', 'EN_REVISION_INSTITUCIONAL', 'VALIDADO_Y_CERRADO', 'VENCIDA',
            'VALIDADA', 'ENTREGADA', 'OBSERVADA', 'EN_REVISION_INSTITUCIONAL', 'VALIDADO_Y_CERRADO',
            'VENCIDA', 'VALIDADA', 'ENTREGADA', 'OBSERVADA', 'PROGRAMADA',
            'ABIERTA', 'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA', 'REPROGRAMADA', 'PROGRAMADA',
            'EN_CAPTURA', 'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'OBSERVADA', 'VALIDADA',
            'VALIDADO_Y_CERRADO', 'VENCIDA', 'PROGRAMADA', 'ABIERTA', 'EN_CAPTURA',
            'ENTREGADA', 'OBSERVADA', 'VALIDADA', 'REPROGRAMADA', 'PROGRAMADA',
            'EN_CAPTURA', 'ENTREGADA'
        ];

        foreach ($loads as $index => $load) {
            $status = $statusPlan[$index % count($statusPlan)];
            $isReprogrammed = $status === 'REPROGRAMADA';
            $isClosed = $status === 'VALIDADO_Y_CERRADO';
            $isValidated = in_array($status, ['VALIDADA', 'VALIDADO_Y_CERRADO'], true);
            $isDelivered = in_array($status, ['ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'OBSERVADA', 'VALIDADA', 'VALIDADO_Y_CERRADO'], true);
            $completion = match (true) {
                $isClosed => 100,
                $isValidated => 90,
                $isDelivered => 60,
                $status === 'OBSERVADA' => 45,
                $status === 'EN_CAPTURA' => 20,
                $status === 'PARCIALMENTE_ENTREGADA' => 50,
                default => 0,
            };

            $load->update([
                'status' => $status,
                'traffic_light' => match ($status) {
                    'VALIDADO_Y_CERRADO', 'VALIDADA' => 'GREEN',
                    'VENCIDA' => 'RED',
                    'OBSERVADA', 'REPROGRAMADA' => 'ORANGE',
                    'PROGRAMADA' => 'GRAY',
                    default => 'YELLOW',
                },
                'retroactive' => $isReprogrammed,
                'is_blocked' => $isClosed || $isReprogrammed,
                'block_reason' => $isClosed ? 'Carga cerrada' : ($isReprogrammed ? 'Carga reprogramada' : null),
                'completion_percentage' => $completion,
                'delivered_at' => $isDelivered ? $load->effective_close_at->subHours(3) : null,
                'validated_at' => $isValidated ? $load->effective_close_at->subHour() : null,
                'closed_at' => $isClosed ? $load->effective_close_at : null,
                'metadata' => array_merge($load->metadata ?? [], [
                    'qa' => true,
                    'universe' => '2026-credibly-rebuilt',
                    'agency_code' => $agency->code,
                ]),
            ]);

            foreach ($load->deliverables()->with('organizationalUnit')->get() as $deliverable) {
                $deliverableStatus = match ($status) {
                    'PROGRAMADA' => 'PENDIENTE',
                    'ABIERTA', 'EN_CAPTURA', 'REPROGRAMADA' => 'EN_CAPTURA',
                    'PARCIALMENTE_ENTREGADA', 'ENTREGADA', 'EN_REVISION_INSTITUCIONAL' => 'ENVIADO',
                    'OBSERVADA' => 'OBSERVADO',
                    'VALIDADA', 'VALIDADO_Y_CERRADO' => 'VALIDADO',
                    'VENCIDA' => 'OBSERVADO',
                    default => 'PENDIENTE',
                };

                $deliverable->update([
                    'status' => $deliverableStatus,
                    'submitted_at' => in_array($deliverableStatus, ['ENVIADO', 'OBSERVADO', 'VALIDADO'], true) ? $load->effective_close_at->subHours(4) : null,
                    'validated_at' => in_array($deliverableStatus, ['VALIDADO'], true) ? $load->effective_close_at->subHour() : null,
                    'observations' => $deliverableStatus === 'OBSERVADO' ? 'Revisar nomenclatura y completar evidencia.' : null,
                ]);

                if ($deliverableStatus === 'PENDIENTE') {
                    continue;
                }

                $operator = $deliverable->organizational_unit_id === $agency->units->firstWhere('code', 'DIR_A')?->id
                    ? $users[$agency->code === 'IMSS' ? 'operador.monitoreo@siget.local' : 'operador.monitoreo.IPAB@siget.local']
                    : $users[$agency->code === 'IMSS' ? 'operacion.programacion@siget.local' : 'operacion.programacion.IPAB@siget.local'];

                $evidenceStatus = match ($deliverableStatus) {
                    'OBSERVADO' => 'OBSERVADO',
                    'VALIDADO' => 'VALIDADO',
                    'ENVIADO' => 'ENVIADO',
                    default => 'EN_CAPTURA',
                };

                $evidence = Evidence::query()->updateOrCreate(
                    ['deliverable_id' => $deliverable->id],
                    [
                        'scheduled_load_id' => $load->id,
                        'folder_id' => RepositoryFolder::query()
                            ->where('scheduled_load_id', $load->id)
                            ->where('organizational_unit_id', $deliverable->organizational_unit_id)
                            ->value('id'),
                        'submitted_by' => $operator->id,
                        'title' => $deliverable->templateRequirement?->name ?? 'Evidencia QA',
                        'description' => 'Registro de evidencia QA generado sobre una carga importada por el flujo real de pauta.',
                        'status' => $evidenceStatus,
                        'current_version' => $evidenceStatus === 'OBSERVADO' ? 2 : 1,
                        'revision_number' => $evidenceStatus === 'OBSERVADO' ? 2 : 1,
                        'submitted_at' => $deliverable->submitted_at,
                        'validated_at' => $deliverable->validated_at,
                    ]
                );

                if ($evidenceStatus === 'VALIDADO') {
                    $reviewer = $users['enlace@siget.local'];
                    EvidenceReview::query()->firstOrCreate([
                        'evidence_id' => $evidence->id,
                        'reviewer_id' => $reviewer->id,
                        'decision' => 'APPROVED',
                    ], [
                        'comments' => 'Evidencia QA validada para prueba funcional.',
                        'review_type' => 'INSTITUTIONAL',
                    ]);
                }
            }
        }
    }
}
