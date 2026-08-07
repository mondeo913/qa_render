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
use App\Services\AccessScopeService;
use Database\Seeders\AgencyTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_access_the_other_direction_deliverable(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $monitoring = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $production = $agency->units()->where('code', 'DIR_B')->firstOrFail();
        $role = Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail();

        $monitoringUser = User::factory()->create([
            'role_id' => $role->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $monitoring->id,
        ]);

        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
        ]);

        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
        ]);
        $row = CalendarImportRow::factory()->create([
            'calendar_import_id' => $import->id,
            'source_column' => 'O',
        ]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();

        $load = ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => 'Carga aislada',
            'original_open_at' => now()->subHour(),
            'original_close_at' => now()->addHour(),
            'effective_open_at' => now()->subHour(),
            'effective_close_at' => now()->addHour(),
            'status' => 'ABIERTA',
            'traffic_light' => 'BLUE',
        ]);

        $monitorReq = $template->requirements()->where('responsible_unit_id', $monitoring->id)->firstOrFail();
        $productionReq = $template->requirements()->where('responsible_unit_id', $production->id)->firstOrFail();

        $monitorDeliverable = ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $load->id,
            'template_requirement_id' => $monitorReq->id,
            'organizational_unit_id' => $monitoring->id,
            'responsible_user_id' => $monitoringUser->id,
            'status' => 'PENDIENTE',
        ]);

        $productionDeliverable = ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $load->id,
            'template_requirement_id' => $productionReq->id,
            'organizational_unit_id' => $production->id,
            'status' => 'PENDIENTE',
        ]);

        $access = app(AccessScopeService::class);

        $this->assertTrue($access->canAccessDeliverable($monitoringUser, $monitorDeliverable));
        $this->assertFalse($access->canAccessDeliverable($monitoringUser, $productionDeliverable));

        $response = $this->actingAs($monitoringUser)->get(route('loads.show', $load));
        $response->assertOk();
        $response->assertSee($monitorReq->name);
        $response->assertDontSee($productionReq->name);
    }
}
