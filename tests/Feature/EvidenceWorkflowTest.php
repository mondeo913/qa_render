<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoadStatusHistory;
use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use App\Models\Role;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use App\Services\EvidenceService;
use App\Services\EvidenceWorkflowService;
use Database\Seeders\AgencyTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EvidenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_submit_and_approve_updates_the_workflow(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $unit = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $operatorRole = Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail();
        $enlaceRole = Role::query()->where('code', 'ENLACE_INSTITUCIONAL')->firstOrFail();

        $operator = User::factory()->create([
            'role_id' => $operatorRole->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $unit->id,
        ]);
        $enlace = User::factory()->create([
            'role_id' => $enlaceRole->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $unit->id,
        ]);

        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $enlace->id,
        ]);
        $row = CalendarImportRow::factory()->create([
            'calendar_import_id' => $import->id,
            'source_column' => 'P',
        ]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();
        $requirement = $template->requirements()
            ->where('responsible_unit_id', $unit->id)
            ->firstOrFail();

        $load = ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => 'Carga de flujo',
            'original_open_at' => now()->subHour(),
            'original_close_at' => now()->addHours(4),
            'effective_open_at' => now()->subHour(),
            'effective_close_at' => now()->addHours(4),
            'status' => 'ABIERTA',
            'traffic_light' => 'BLUE',
        ]);

        $deliverable = ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $load->id,
            'template_requirement_id' => $requirement->id,
            'organizational_unit_id' => $unit->id,
            'responsible_user_id' => $operator->id,
            'status' => 'PENDIENTE',
            'due_at' => now()->addHours(4),
        ]);

        $file = UploadedFile::fake()->create(
            'bitacora.xlsx',
            20,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $evidence = app(EvidenceService::class)->upload(
            $deliverable->load(['scheduledLoad', 'templateRequirement']),
            $file,
            $operator,
            'Bitácora de prueba'
        );

        app(EvidenceWorkflowService::class)->submit($evidence, $operator);
        $director = User::factory()->create(['role_id' => Role::query()->where('code', 'DIRECTOR_TRANSMISION')->firstOrFail()->id, 'organizational_unit_id' => $unit->id]);
        app(EvidenceWorkflowService::class)->review($evidence->fresh(), $director, 'APROBADO', 'Validación de Dirección');
        app(EvidenceWorkflowService::class)->review(
            $evidence->fresh(['deliverable', 'scheduledLoad']),
            $enlace,
            'APROBADO',
            'Correcta'
        );

        $this->assertSame('VALIDADO', $evidence->fresh()->status->value);
        $this->assertSame('VALIDADO', $deliverable->fresh()->status->value);
        $this->assertDatabaseHas('evidence_reviews', [
            'evidence_id' => $evidence->id,
            'decision' => 'APROBADO',
        ]);
    }

    public function test_retroactive_submission_and_validation_keep_schedule_date_while_audit_keeps_real_date(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 11:00:00', config('app.timezone')));
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $unit = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $operator = User::factory()->create([
            'role_id' => Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $unit->id,
        ]);
        $enlace = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ENLACE_INSTITUCIONAL')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $unit->id,
        ]);
        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $enlace->id,
        ]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();
        $requirement = $template->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();

        $load = ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => 'Pauta retroactiva de prueba',
            'period_label' => 'Julio 2026',
            'original_open_at' => '2026-07-01 08:00:00',
            'original_close_at' => '2026-07-01 23:59:59',
            'effective_open_at' => '2026-07-01 08:00:00',
            'effective_close_at' => '2026-07-01 23:59:59',
            'retroactive' => true,
            'metadata' => [
                'retroactive_registration' => true,
                'retroactive_policy_year' => 2026,
                'source_schedule_date' => '2026-07-01',
            ],
            'status' => 'PROGRAMADA',
            'traffic_light' => 'GRAY',
            'is_blocked' => false,
        ]);
        $deliverable = ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $load->id,
            'template_requirement_id' => $requirement->id,
            'organizational_unit_id' => $unit->id,
            'responsible_user_id' => $operator->id,
            'status' => 'PENDIENTE',
            'due_at' => $load->effective_close_at,
        ]);

        $file = UploadedFile::fake()->create(
            'bitacora.xlsx',
            20,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $evidence = app(EvidenceService::class)->upload(
            $deliverable->load(['scheduledLoad', 'templateRequirement']),
            $file,
            $operator,
            'Bitácora retroactiva'
        );

        app(EvidenceWorkflowService::class)->submit($evidence, $operator);
        $director = User::factory()->create([
            'role_id' => Role::query()->where('code', 'DIRECTOR_TRANSMISION')->firstOrFail()->id,
            'organizational_unit_id' => $unit->id,
        ]);
        app(EvidenceWorkflowService::class)->review(
            $evidence->fresh(),
            $director,
            'APROBADO',
            'Validación de Dirección'
        );
        app(EvidenceWorkflowService::class)->review(
            $evidence->fresh(['deliverable', 'scheduledLoad']),
            $enlace,
            'APROBADO',
            'Validación institucional'
        );

        $evidence = $evidence->fresh();
        $deliverable = $deliverable->fresh();
        $expectedOperationalDate = '2026-07-01 23:59:59';

        $this->assertSame($expectedOperationalDate, $evidence->submitted_at->format('Y-m-d H:i:s'));
        $this->assertSame($expectedOperationalDate, $deliverable->submitted_at->format('Y-m-d H:i:s'));
        $this->assertSame($expectedOperationalDate, $evidence->validated_at->format('Y-m-d H:i:s'));
        $this->assertSame($expectedOperationalDate, $deliverable->validated_at->format('Y-m-d H:i:s'));

        $auditRows = AuditLog::query()->whereIn('event', [
            'evidence.file.uploaded', 'evidence.submitted', 'evidence.reviewed',
        ])->get();
        $this->assertGreaterThanOrEqual(4, $auditRows->count());
        $this->assertTrue($auditRows->every(
            fn (AuditLog $event) => $event->created_at->toDateString() === '2026-10-19'
        ));

        $historyRows = LoadStatusHistory::query()->where('scheduled_load_id', $load->id)->get();
        $this->assertGreaterThanOrEqual(3, $historyRows->count());
        $this->assertTrue($historyRows->every(
            fn (LoadStatusHistory $event) => $event->created_at->toDateString() === '2026-10-19'
        ));
        $this->assertTrue($historyRows->every(
            fn (LoadStatusHistory $event) => data_get($event->metadata, 'operational_date') === '2026-07-01'
        ));
    }

}
