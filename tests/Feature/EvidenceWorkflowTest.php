<?php

namespace Tests\Feature;

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
}
