<?php

namespace Database\Seeders;

use App\Enums\DeliverableStatus;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use App\Models\CalendarImport;
use App\Models\ScheduledLoad;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('email', 'admin@siget.local')->first();
            $director = User::query()->where('email', 'director.general@siget.local')->first();
            $enlace = User::query()->where('email', 'enlace@siget.local')->first();

            if (!$admin || !$director || !$enlace) {
                throw new \RuntimeException('No existen los usuarios QA requeridos para el escenario del dashboard.');
            }

            $importIds = CalendarImport::query()
                ->whereIn('original_filename', [
                    'Pauta_QA_IMSS_2026.xlsx',
                    'Pauta_QA_IPAB_2026.xlsx',
                ])
                ->pluck('id');

            $loads = ScheduledLoad::query()
                ->whereIn('calendar_import_id', $importIds)
                ->orderBy('contracting_agency_id')
                ->orderBy('effective_open_at')
                ->orderBy('id')
                ->get();

            if ($loads->count() !== 10) {
                throw new \RuntimeException('El escenario del dashboard requiere exactamente 10 cargas QA.');
            }

            $scenario = [
                [
                    'status' => ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
                    'completion' => 100,
                    'traffic' => TrafficLight::DARK_GREEN->value,
                    'deliverable' => DeliverableStatus::CERRADO->value,
                    'note' => 'Cierre completo y validado.',
                ],
                [
                    'status' => ScheduledLoadStatus::VALIDADA->value,
                    'completion' => 100,
                    'traffic' => TrafficLight::GREEN->value,
                    'deliverable' => DeliverableStatus::VALIDADO->value,
                    'note' => 'Evidencia validada; pendiente de cierre administrativo.',
                ],
                [
                    'status' => ScheduledLoadStatus::ENTREGADA->value,
                    'completion' => 100,
                    'traffic' => TrafficLight::BLUE->value,
                    'deliverable' => DeliverableStatus::ENVIADO->value,
                    'note' => 'Realizada y entregada; pendiente de validación.',
                ],
                [
                    'status' => ScheduledLoadStatus::REPROGRAMADA->value,
                    'completion' => 60,
                    'traffic' => TrafficLight::YELLOW->value,
                    'deliverable' => DeliverableStatus::PENDIENTE->value,
                    'note' => 'Reprogramación pendiente de ejecución.',
                ],
                [
                    'status' => ScheduledLoadStatus::VENCIDA->value,
                    'completion' => 0,
                    'traffic' => TrafficLight::RED->value,
                    'deliverable' => DeliverableStatus::PENDIENTE->value,
                    'note' => 'Faltante: fecha de pauta vencida sin entrega.',
                ],
            ];

            foreach ($loads as $index => $load) {
                $s = $scenario[$index % 5];
                $now = now();
                $isClosed = $s['status'] === ScheduledLoadStatus::VALIDADO_Y_CERRADO->value;
                $isValidated = in_array($s['status'], [
                    ScheduledLoadStatus::VALIDADA->value,
                    ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
                ], true);
                $isRealized = in_array($s['status'], [
                    ScheduledLoadStatus::ENTREGADA->value,
                    ScheduledLoadStatus::VALIDADA->value,
                    ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
                ], true);

                $load->update([
                    'status' => $s['status'],
                    'traffic_light' => $s['traffic'],
                    'completion_percentage' => $s['completion'],
                    'delivered_at' => $isRealized ? $now->copy()->subDays(1) : null,
                    'validated_at' => $isValidated ? $now->copy()->subHours(6) : null,
                    'validated_by' => $isValidated ? $director->id : null,
                    'closed_at' => $isClosed ? $now->copy()->subHours(2) : null,
                    'closed_by' => $isClosed ? $admin->id : null,
                    'priority' => $s['status'] === ScheduledLoadStatus::VENCIDA->value ? 90 : ($s['status'] === ScheduledLoadStatus::REPROGRAMADA->value ? 70 : 40),
                    'metadata' => array_merge((array) $load->metadata, [
                        'qa' => true,
                        'dashboard_demo' => true,
                        'dashboard_scenario' => $s['note'],
                    ]),
                    'row_version' => ((int) $load->row_version) + 1,
                ]);

                $deliverableStatus = $s['deliverable'];
                foreach ($load->deliverables()->get() as $deliverable) {
                    $deliverable->update([
                        'status' => $deliverableStatus,
                        'submitted_at' => in_array($deliverableStatus, [DeliverableStatus::ENVIADO->value, DeliverableStatus::VALIDADO->value, DeliverableStatus::CERRADO->value], true) ? $now->copy()->subDays(1) : null,
                        'validated_at' => in_array($deliverableStatus, [DeliverableStatus::VALIDADO->value, DeliverableStatus::CERRADO->value], true) ? $now->copy()->subHours(6) : null,
                        'validated_by' => in_array($deliverableStatus, [DeliverableStatus::VALIDADO->value, DeliverableStatus::CERRADO->value], true) ? $director->id : null,
                        'observations' => $s['note'],
                    ]);
                }
            }
        });
    }
}
