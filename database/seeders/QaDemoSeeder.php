<?php

namespace Database\Seeders;

use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\Evidence;
use App\Models\EvidenceTemplate;
use App\Models\OrganizationalUnit;
use App\Models\RepositoryFolder;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class QaDemoSeeder extends Seeder
{
    public function run(): void
    {

        $this->call(AgencyTemplateSeeder::class);

        $agencies = ContractingAgency::query()
            ->whereIn('code', ['IMSS', 'IPAB'])
            ->with(['units', 'templates.requirements'])
            ->orderBy('id')
            ->get();

        $users = app(\App\Services\QaUserStandardService::class)->sync();
        foreach ($agencies as $agencyIndex => $agency) {
            $monitoring = $agency->units->firstWhere('code', 'DIR_A');
            $production = $agency->units->firstWhere('code', 'DIR_B');
            $emails = [
                'admin' => 'admin@siget.local',
                'director_general' => 'director.general@siget.local',
                'director_monitoring' => 'director@siget.local',
                'director_production' => 'director.produccion@siget.local',
                'enlace' => 'enlace@siget.local',
                'operator_monitoring' => 'operador.monitoreo@siget.local',
                'operator_production' => 'operador.produccion@siget.local',
                'fiscalizador' => 'fiscalizador@siget.local',
            ];

            $template = $agency->templates->firstWhere('code', 'PAUTA_MENSUAL');
            if (!$template) {
                throw new \RuntimeException("No existe la plantilla PAUTA_MENSUAL para la dependencia QA {$agency->code}.");
            }
            $template->load('requirements');

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
                'PROGRAMADA', 'ABIERTA', 'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA',
                'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'OBSERVADA', 'LISTA_PARA_FIRMA',
                'VALIDADA', 'VALIDADO_Y_CERRADO', 'VENCIDA', 'REPROGRAMADA',
            ];

            $deliverableStatus = [
                'PROGRAMADA' => 'PENDIENTE', 'ABIERTA' => 'EN_CAPTURA', 'EN_CAPTURA' => 'EN_CAPTURA',
                'PARCIALMENTE_ENTREGADA' => 'ENVIADO', 'ENTREGADA' => 'ENVIADO',
                'EN_REVISION_INSTITUCIONAL' => 'ENVIADO', 'OBSERVADA' => 'OBSERVADO',
                'LISTA_PARA_FIRMA' => 'VALIDADO', 'VALIDADA' => 'VALIDADO',
                'VALIDADO_Y_CERRADO' => 'CERRADO', 'VENCIDA' => 'OBSERVADO', 'REPROGRAMADA' => 'EN_CAPTURA',
            ];

            $traffic = [
                'PROGRAMADA' => 'GRAY', 'ABIERTA' => 'BLUE', 'EN_CAPTURA' => 'YELLOW',
                'PARCIALMENTE_ENTREGADA' => 'YELLOW', 'ENTREGADA' => 'PURPLE',
                'EN_REVISION_INSTITUCIONAL' => 'PURPLE', 'OBSERVADA' => 'ORANGE',
                'LISTA_PARA_FIRMA' => 'GREEN', 'VALIDADA' => 'GREEN',
                'VALIDADO_Y_CERRADO' => 'DARK_GREEN', 'VENCIDA' => 'RED', 'REPROGRAMADA' => 'ORANGE',
            ];

            for ($index = 0; $index < 24; $index++) {
                $status = $statuses[$index % count($statuses)];
                $open = CarbonImmutable::now()->subMonths(8)->startOfMonth()->addDays($index * 3)->setTime(8, 0);
                $close = $open->setTime(23, 59);
                $row = CalendarImportRow::query()->updateOrCreate(
                    [
                        'calendar_import_id' => $import->id,
                        'sheet_name' => 'QA ABCD',
                        'row_number' => $index + 9,
                    ],
                    [
                        'contracting_agency_code' => $agency->code,
                        'organizational_unit_code' => 'MULTI',
                        'template_code' => $template->code,
                        'original_open_at' => $open,
                        'original_close_at' => $close,
                        'source_column' => 'Q'.$index,
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
                    'PENDIENTE' => 0, 'EN_CAPTURA' => 20, 'ENVIADO' => 50, 'OBSERVADO' => 45,
                    'VALIDADO' => 90, 'CERRADO' => 100, default => 0,
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
                        'effective_open_at' => $status === 'REPROGRAMADA' ? $open->addDays(20) : $open,
                        'effective_close_at' => $status === 'REPROGRAMADA' ? $close->addDays(20) : $close,
                        'status' => $status,
                        'traffic_light' => $traffic[$status],
                        'is_blocked' => in_array($status, ['VALIDADO_Y_CERRADO', 'REPROGRAMADA'], true),
                        'block_reason' => $status === 'VALIDADO_Y_CERRADO' ? 'Carga cerrada' : ($status === 'REPROGRAMADA' ? 'Carga reprogramada' : null),
                        'retroactive' => $status === 'REPROGRAMADA',
                        'priority' => $index % 7 === 0 ? 'ALTA' : 'NORMAL',
                        'completion_percentage' => $completion,
                        'delivered_at' => in_array($status, ['ENTREGADA','EN_REVISION_INSTITUCIONAL','LISTA_PARA_FIRMA','VALIDADA','VALIDADO_Y_CERRADO'], true) ? $close->subHours(2) : null,
                        'validated_at' => in_array($status, ['VALIDADA','VALIDADO_Y_CERRADO'], true) ? $close->subHour() : null,
                        'closed_at' => $status === 'VALIDADO_Y_CERRADO' ? $close : null,
                        'metadata' => ['qa' => true, 'service_count' => ($index % 5) + 1, 'services' => []],
                    ]
                );

                $root = RepositoryFolder::query()->firstOrCreate(
                    ['path_key' => "qa/{$agency->code}/{$load->id}"],
                    ['contracting_agency_id' => $agency->id, 'scheduled_load_id' => $load->id, 'folder_type' => 'SCHEDULED_LOAD', 'name' => $load->title, 'created_by' => $importer->id]
                );

                foreach ($template->requirements as $requirement) {
                    $unit = OrganizationalUnit::query()->findOrFail($requirement->responsible_unit_id);
                    $operatorEmail = $unit->code === 'DIR_A' ? $emails['operator_monitoring'] : $emails['operator_production'];
                    $operator = $users[$operatorEmail];

                    $folder = RepositoryFolder::query()->firstOrCreate(
                        ['path_key' => "qa/{$agency->code}/{$load->id}/{$unit->code}"],
                        ['parent_id' => $root->id, 'contracting_agency_id' => $agency->id, 'organizational_unit_id' => $unit->id, 'scheduled_load_id' => $load->id, 'folder_type' => 'OPERATIONAL_UNIT', 'name' => $unit->name, 'created_by' => $importer->id]
                    );

                    $deliverable = ScheduledLoadDeliverable::query()->updateOrCreate(
                        ['scheduled_load_id' => $load->id, 'template_requirement_id' => $requirement->id, 'organizational_unit_id' => $unit->id],
                        [
                            'responsible_user_id' => $operator->id,
                            'status' => $deliverableStatus[$status],
                            'due_at' => $load->effective_close_at,
                            'submitted_at' => in_array($deliverableStatus[$status], ['ENVIADO','VALIDADO','CERRADO','OBSERVADO'], true) ? $load->effective_close_at->subHours(3) : null,
                            'validated_at' => in_array($deliverableStatus[$status], ['VALIDADO','CERRADO'], true) ? $load->effective_close_at->subHour() : null,
                            'observations' => $deliverableStatus[$status] === 'OBSERVADO' ? 'Corregir nomenclatura y volver a enviar.' : null,
                        ]
                    );

                    if ($deliverableStatus[$status] !== 'PENDIENTE') {
                        $evidenceStatus = match ($deliverableStatus[$status]) {
                            'EN_CAPTURA' => 'EN_CAPTURA', 'ENVIADO' => 'ENVIADO', 'OBSERVADO' => 'OBSERVADO',
                            'VALIDADO', 'CERRADO' => 'VALIDADO', default => 'EN_CAPTURA',
                        };

                        Evidence::query()->updateOrCreate(
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
                            ]
                        );
                    }
                }
            }
        }
    }
}
