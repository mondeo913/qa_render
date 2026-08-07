<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\BackupExecution;
use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\Evidence;
use App\Models\EvidenceReview;
use App\Models\EvidenceTemplate;
use App\Models\NotificationRecord;
use App\Models\OperationalIncident;
use App\Models\OperationalMetric;
use App\Models\OrganizationalUnit;
use App\Models\RepositoryFolder;
use App\Models\ReviewAssignment;
use App\Models\Role;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\SystemSetting;
use App\Models\TemplateRequirement;
use App\Models\User;
use App\Models\UserScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SIGET_QA_PASSWORD', 'SigetQA_2026_Cambiar!');
        $adminEmail = env('SIGET_ADMIN_EMAIL', 'admin@siget.local');

        $agencies = ContractingAgency::query()
            ->with(['units', 'templates.requirements'])
            ->get();

        $users = [];

        foreach ($agencies as $agencyIndex => $agency) {
            $monitoring = $agency->units->firstWhere('code', 'DIR_A');
            $production = $agency->units->firstWhere('code', 'DIR_B');

            $emails = [
                'admin' => $agencyIndex === 0 ? $adminEmail : "admin.{$agency->code}@siget.local",
                'director_general' => $agencyIndex === 0 ? 'director.general@siget.local' : "director.general.{$agency->code}@siget.local",
                'director_monitoring' => $agencyIndex === 0 ? 'director@siget.local' : "director.monitoreo.{$agency->code}@siget.local",
                'director_production' => $agencyIndex === 0 ? 'director.produccion@siget.local' : "director.produccion.{$agency->code}@siget.local",
                'enlace' => $agencyIndex === 0 ? 'enlace@siget.local' : "enlace.{$agency->code}@siget.local",
                'operator_monitoring' => $agencyIndex === 0 ? 'operador.monitoreo@siget.local' : "operador.monitoreo.{$agency->code}@siget.local",
                'operator_production' => $agencyIndex === 0 ? 'operador.produccion@siget.local' : "operador.produccion.{$agency->code}@siget.local",
                'fiscalizador' => $agencyIndex === 0 ? 'fiscalizador@siget.local' : "fiscalizador.{$agency->code}@siget.local",
            ];

            $definitions = [
                ['ADMINISTRADOR', $emails['admin'], "Administrador {$agency->code}", $monitoring],
                ['DIRECTOR_GENERAL', $emails['director_general'], "Director General {$agency->code}", $monitoring],
                ['DIRECTOR_TRANSMISION', $emails['director_monitoring'], "Director de Transmisión {$agency->code}", $monitoring],
                ['DIRECTOR_PROGRAMACION_CONTINUIDAD', $emails['director_production'], "Director de Programación y Continuidad {$agency->code}", $production],
                ['ENLACE_INSTITUCIONAL', $emails['enlace'], "Enlace {$agency->code}", $monitoring],
                ['OPERADOR_TRANSMISION', $emails['operator_monitoring'], "Operativo de Transmisión {$agency->code}", $monitoring],
                ['OPERADOR_PROGRAMACION_CONTINUIDAD', $emails['operator_production'], "Operativo de Programación y Continuidad {$agency->code}", $production],
                ['FISCALIZADOR', $emails['fiscalizador'], "Fiscalizador {$agency->code}", $monitoring],
            ];

            foreach ($definitions as [$roleCode, $email, $name, $unit]) {
                $role = Role::query()->where('code', $roleCode)->firstOrFail();

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
                        'metadata' => ['qa' => true],
                    ]
                );

                $users[$email] = $user;

                UserScope::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'contracting_agency_id' => $agency->id,
                        'organizational_unit_id' => in_array($roleCode, [
                            'DIRECTOR',
                            'DIRECTOR_TRANSMISION',
                            'DIRECTOR_PROGRAMACION_CONTINUIDAD',
                            'OPERADOR',
                            'OPERADOR_TRANSMISION',
                            'OPERADOR_PROGRAMACION_CONTINUIDAD',
                        ], true)
                            ? $unit?->id
                            : null,
                    ],
                    [
                        'can_read' => true,
                        'can_write' => in_array(
                            $roleCode,
                            [
                                'ADMINISTRADOR',
                                'ENLACE_INSTITUCIONAL',
                                'OPERADOR',
                                'OPERADOR_TRANSMISION',
                                'OPERADOR_PROGRAMACION_CONTINUIDAD',
                            ],
                            true
                        ),
                    ]
                );
            }

            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->with('requirements')
                ->firstOrFail();

            $importer = $users[$emails['admin']];

            $import = CalendarImport::query()->updateOrCreate(
                ['sha256' => hash('sha256', "SIGET-ABCD-QA-{$agency->code}")],
                [
                    'contracting_agency_id' => $agency->id,
                    'uploaded_by' => $importer->id,
                    'original_filename' => "Pauta_QA_{$agency->code}.xlsx",
                    'storage_path' => "qa/Pauta_QA_{$agency->code}.xlsx",
                    'workbook_version' => 'ABCD-QA-1',
                    'status' => 'CONFIRMED',
                    'total_rows' => 48,
                    'valid_rows' => 48,
                    'error_rows' => 0,
                    'warnings' => [],
                    'errors' => [],
                    'confirmed_at' => now(),
                ]
            );

            $statuses = [
                'PROGRAMADA',
                'ABIERTA',
                'EN_CAPTURA',
                'PARCIALMENTE_ENTREGADA',
                'ENTREGADA',
                'EN_REVISION_INSTITUCIONAL',
                'OBSERVADA',
                'LISTA_PARA_FIRMA',
                'VALIDADA',
                'VALIDADO_Y_CERRADO',
                'VENCIDA',
                'REPROGRAMADA',
            ];

            $traffic = [
                'PROGRAMADA' => 'GRAY',
                'ABIERTA' => 'BLUE',
                'EN_CAPTURA' => 'YELLOW',
                'PARCIALMENTE_ENTREGADA' => 'YELLOW',
                'ENTREGADA' => 'PURPLE',
                'EN_REVISION_INSTITUCIONAL' => 'PURPLE',
                'OBSERVADA' => 'ORANGE',
                'LISTA_PARA_FIRMA' => 'GREEN',
                'VALIDADA' => 'GREEN',
                'VALIDADO_Y_CERRADO' => 'DARK_GREEN',
                'VENCIDA' => 'RED',
                'REPROGRAMADA' => 'ORANGE',
            ];

            $deliverableStatus = [
                'PROGRAMADA' => 'PENDIENTE',
                'ABIERTA' => 'PENDIENTE',
                'EN_CAPTURA' => 'EN_CAPTURA',
                'PARCIALMENTE_ENTREGADA' => 'ENVIADO',
                'ENTREGADA' => 'ENVIADO',
                'EN_REVISION_INSTITUCIONAL' => 'VALIDADO',
                'OBSERVADA' => 'OBSERVADO',
                'LISTA_PARA_FIRMA' => 'VALIDADO',
                'VALIDADA' => 'VALIDADO',
                'VALIDADO_Y_CERRADO' => 'CERRADO',
                'VENCIDA' => 'PENDIENTE',
                'REPROGRAMADA' => 'PENDIENTE',
            ];

            $baseDate = CarbonImmutable::now()
                ->subMonths(8)
                ->startOfMonth()
                ->addDays(2);

            for ($index = 0; $index < 48; $index++) {
                $status = $statuses[$index % count($statuses)];
                $open = $baseDate->addDays($index * 5)->setTime(8, 0);
                $close = $open->setTime(23, 59);
                $row = CalendarImportRow::query()->updateOrCreate(
                    [
                        'calendar_import_id' => $import->id,
                        'sheet_name' => 'QA ABCD',
                        'row_number' => $index + 9,
                        'source_column' => 'Q'.$index,
                    ],
                    [
                        'contracting_agency_code' => $agency->code,
                        'organizational_unit_code' => 'MULTI',
                        'template_code' => $template->code,
                        'original_open_at' => $open,
                        'original_close_at' => $close,
                        'delivery_name' => "Pauta QA {$agency->code} #".($index + 1),
                        'payload' => [
                            'format' => 'HORIZONTAL_X',
                            'service' => [
                                'campana' => 'Campaña de demostración',
                                'canal' => 'Canal Catorce',
                                'plaza' => ['Nacional', 'Puebla', 'Veracruz', 'Yucatán'][$index % 4],
                                'formato_duracion' => 'SPOT TELEVISIVO / 30 seg',
                            ],
                        ],
                        'is_valid' => true,
                        'validation_messages' => [],
                    ]
                );

                $completion = match ($deliverableStatus[$status]) {
                    'PENDIENTE' => 0,
                    'EN_CAPTURA' => 20,
                    'ENVIADO' => 50,
                    'OBSERVADO' => 45,
                    'VALIDADO' => 90,
                    'CERRADO' => 100,
                    default => 0,
                };

                $load = ScheduledLoad::query()->updateOrCreate(
                    ['calendar_import_row_id' => $row->id],
                    [
                        'calendar_import_id' => $import->id,
                        'contracting_agency_id' => $agency->id,
                        'template_id' => $template->id,
                        'title' => "Pauta TV {$agency->code} · ".$open->format('d/m/Y'),
                        'period_label' => ucfirst($open->translatedFormat('F Y')),
                        'original_open_at' => $open,
                        'original_close_at' => $close,
                        'effective_open_at' => $status === 'REPROGRAMADA'
                            ? $open->addDays(20)
                            : $open,
                        'effective_close_at' => $status === 'REPROGRAMADA'
                            ? $close->addDays(20)
                            : $close,
                        'status' => $status,
                        'traffic_light' => $traffic[$status],
                        'is_blocked' => in_array($status, ['VALIDADO_Y_CERRADO', 'REPROGRAMADA'], true),
                        'block_reason' => $status === 'VALIDADO_Y_CERRADO'
                            ? 'Carga cerrada'
                            : ($status === 'REPROGRAMADA' ? 'Carga reprogramada' : null),
                        'retroactive' => $status === 'REPROGRAMADA',
                        'priority' => $index % 7 === 0 ? 'ALTA' : 'NORMAL',
                        'completion_percentage' => $completion,
                        'delivered_at' => in_array($status, [
                            'ENTREGADA',
                            'EN_REVISION_INSTITUCIONAL',
                            'LISTA_PARA_FIRMA',
                            'VALIDADA',
                            'VALIDADO_Y_CERRADO',
                        ], true) ? $close->subHours(2) : null,
                        'validated_at' => in_array($status, [
                            'VALIDADA',
                            'VALIDADO_Y_CERRADO',
                        ], true) ? $close->subHour() : null,
                        'closed_at' => $status === 'VALIDADO_Y_CERRADO'
                            ? $close
                            : null,
                        'metadata' => [
                            'qa' => true,
                            'service_count' => ($index % 5) + 1,
                            'services' => [],
                        ],
                    ]
                );

                $root = RepositoryFolder::query()->firstOrCreate(
                    ['path_key' => "qa/{$agency->code}/{$load->id}"],
                    [
                        'contracting_agency_id' => $agency->id,
                        'scheduled_load_id' => $load->id,
                        'folder_type' => 'SCHEDULED_LOAD',
                        'name' => $load->title,
                        'created_by' => $importer->id,
                    ]
                );

                foreach ($template->requirements as $requirement) {
                    $unit = OrganizationalUnit::query()->findOrFail(
                        $requirement->responsible_unit_id
                    );
                    $operatorEmail = $unit->code === 'DIR_A'
                        ? $emails['operator_monitoring']
                        : $emails['operator_production'];
                    $operator = $users[$operatorEmail];

                    $folder = RepositoryFolder::query()->firstOrCreate(
                        ['path_key' => "qa/{$agency->code}/{$load->id}/{$unit->code}"],
                        [
                            'parent_id' => $root->id,
                            'contracting_agency_id' => $agency->id,
                            'organizational_unit_id' => $unit->id,
                            'scheduled_load_id' => $load->id,
                            'folder_type' => 'OPERATIONAL_UNIT',
                            'name' => $unit->name,
                            'created_by' => $importer->id,
                        ]
                    );

                    $deliverable = ScheduledLoadDeliverable::query()->updateOrCreate(
                        [
                            'scheduled_load_id' => $load->id,
                            'template_requirement_id' => $requirement->id,
                            'organizational_unit_id' => $unit->id,
                        ],
                        [
                            'responsible_user_id' => $operator->id,
                            'status' => $deliverableStatus[$status],
                            'due_at' => $load->effective_close_at,
                            'submitted_at' => in_array($deliverableStatus[$status], [
                                'ENVIADO', 'VALIDADO', 'CERRADO', 'OBSERVADO',
                            ], true) ? $load->effective_close_at->subHours(3) : null,
                            'validated_at' => in_array($deliverableStatus[$status], [
                                'VALIDADO', 'CERRADO',
                            ], true) ? $load->effective_close_at->subHour() : null,
                            'observations' => $deliverableStatus[$status] === 'OBSERVADO'
                                ? 'Corregir nomenclatura y volver a enviar.'
                                : null,
                        ]
                    );

                    if (!in_array($deliverableStatus[$status], ['PENDIENTE'], true)) {
                        $evidenceStatus = match ($deliverableStatus[$status]) {
                            'EN_CAPTURA' => 'EN_CAPTURA',
                            'ENVIADO' => 'ENVIADO',
                            'OBSERVADO' => 'OBSERVADO',
                            'VALIDADO', 'CERRADO' => 'VALIDADO',
                            default => 'EN_CAPTURA',
                        };

                        $evidence = Evidence::query()->updateOrCreate(
                            ['deliverable_id' => $deliverable->id],
                            [
                                'scheduled_load_id' => $load->id,
                                'folder_id' => $folder->id,
                                'submitted_by' => $operator->id,
                                'title' => $requirement->name,
                                'description' => 'Evidencia QA para poblar gráficas y flujos.',
                                'status' => $evidenceStatus,
                                'current_version' => $evidenceStatus === 'OBSERVADO' ? 2 : 1,
                                'revision_number' => $evidenceStatus === 'OBSERVADO' ? 2 : 1,
                                'submitted_at' => $deliverable->submitted_at,
                                'validated_at' => $deliverable->validated_at,
                                'validated_by' => $deliverable->validated_at
                                    ? $users[$emails['enlace']]->id
                                    : null,
                            ]
                        );

                        if ($evidenceStatus === 'OBSERVADO') {
                            EvidenceReview::query()->updateOrCreate(
                                [
                                    'evidence_id' => $evidence->id,
                                    'reviewer_id' => $users[$emails['enlace']]->id,
                                    'decision' => 'OBSERVADO',
                                ],
                                [
                                    'comments' => 'Ajustar el nombre y la versión del archivo.',
                                    'review_type' => 'INSTITUTIONAL',
                                ]
                            );
                        }

                        if ($evidenceStatus === 'VALIDADO') {
                            EvidenceReview::query()->updateOrCreate(
                                [
                                    'evidence_id' => $evidence->id,
                                    'reviewer_id' => $users[$emails['enlace']]->id,
                                    'decision' => 'APROBADO',
                                ],
                                [
                                    'comments' => 'Evidencia QA validada.',
                                    'review_type' => 'INSTITUTIONAL',
                                ]
                            );
                        }
                    }
                }

                if ($index % 4 === 0) {
                    ReviewAssignment::query()->updateOrCreate(
                        [
                            'scheduled_load_id' => $load->id,
                            'fiscalizador_id' => $users[$emails['fiscalizador']]->id,
                        ],
                        [
                            'assigned_by' => $users[$emails['enlace']]->id,
                            'active' => true,
                            'notes' => 'Asignación QA automática.',
                        ]
                    );
                }

                NotificationRecord::query()->create([
                    'user_id' => $users[$emails['enlace']]->id,
                    'scheduled_load_id' => $load->id,
                    'channel' => 'DATABASE',
                    'status' => 'SENT',
                    'subject' => "Actualización de carga #{$load->id}",
                    'message' => "La carga se encuentra en estado {$status}.",
                    'action_url' => "/cargas/{$load->id}",
                    'sent_at' => now(),
                ]);

                AuditLog::query()->create([
                    'user_id' => $importer->id,
                    'event' => 'qa.load.seeded',
                    'entity_type' => ScheduledLoad::class,
                    'entity_id' => (string) $load->id,
                    'new_values' => ['status' => $status],
                    'request_id' => Str::uuid(),
                ]);
            }
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'qa.environment'],
            [
                'value' => ['value' => 'GitHub Codespaces'],
                'description' => 'Ambiente integrado SIGET ABCD para QA.',
                'updated_by' => $users[$adminEmail]->id,
            ]
        );

        foreach ([
            ['system.health', 1, 'boolean'],
            ['queue.pending', 2, 'jobs'],
            ['storage.usage', 18.5, 'percent'],
            ['database.connections', 4, 'connections'],
        ] as [$metric, $value, $unit]) {
            OperationalMetric::query()->create([
                'metric_key' => $metric,
                'metric_value' => $value,
                'unit' => $unit,
                'dimensions' => ['environment' => 'codespaces'],
                'collected_at' => now(),
            ]);
        }

        AlertRule::query()->updateOrCreate(
            ['code' => 'QUEUE_PENDING_HIGH'],
            [
                'name' => 'Cola con trabajos pendientes',
                'metric_key' => 'queue.pending',
                'operator' => '>',
                'threshold' => 25,
                'severity' => 'WARNING',
                'evaluation_window_minutes' => 5,
                'cooldown_minutes' => 30,
                'channels' => ['database', 'mail'],
                'recipients' => [$adminEmail],
                'enabled' => true,
            ]
        );

        OperationalIncident::query()->create([
            'code' => 'QA-INC-'.now()->format('YmdHis'),
            'title' => 'Incidente QA de demostración',
            'severity' => 'LOW',
            'status' => 'OPEN',
            'source' => 'QA_SEEDER',
            'description' => 'Incidente demostrativo para validar el Centro de Operaciones.',
            'assigned_to' => $users[$adminEmail]->id,
            'opened_at' => now()->subHour(),
            'metadata' => ['qa' => true],
        ]);

        BackupExecution::query()->create([
            'backup_type' => 'FULL',
            'status' => 'VERIFIED',
            'started_at' => now()->subHours(3),
            'finished_at' => now()->subHours(2)->addMinutes(45),
            'database_file' => 'qa/backups/database.dump',
            'storage_file' => 'qa/backups/storage.zip',
            'database_sha256' => hash('sha256', 'qa-database'),
            'storage_sha256' => hash('sha256', 'qa-storage'),
            'size_bytes' => 10485760,
            'verified_at' => now()->subHours(2),
            'metadata' => ['qa' => true],
        ]);
    }
}
