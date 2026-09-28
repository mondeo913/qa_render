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
use Database\Seeders\AgencyTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_can_open_intelligence_and_only_sees_scoped_loads(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $tx = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $pc = $agency->units()->where('code', 'DIR_B')->firstOrFail();

        $operator = User::factory()->create([
            'role_id' => Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $tx->id,
        ]);
        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
        ]);

        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
        ]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();

        $txLoad = $this->createLoad($agency, $import, $row, $template, 'Carga Inteligencia Transmisión');
        $pcLoad = $this->createLoad($agency, $import, $row, $template, 'Carga Inteligencia Programación');

        $txRequirement = $template->requirements()->where('responsible_unit_id', $tx->id)->firstOrFail();
        $pcRequirement = $template->requirements()->where('responsible_unit_id', $pc->id)->firstOrFail();

        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $txLoad->id,
            'template_requirement_id' => $txRequirement->id,
            'organizational_unit_id' => $tx->id,
            'responsible_user_id' => $operator->id,
            'status' => 'PENDIENTE',
            'due_at' => now()->addDays(2),
        ]);
        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $pcLoad->id,
            'template_requirement_id' => $pcRequirement->id,
            'organizational_unit_id' => $pc->id,
            'status' => 'ENVIADO',
            'due_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($operator)->get(route('intelligence'));

        $response->assertOk();
        $response->assertSee('Radar y alertas');
        $response->assertSee('Carga Inteligencia Transmisión');
        $response->assertDontSee('Carga Inteligencia Programación');
    }

    private function createLoad(
        ContractingAgency $agency,
        CalendarImport $import,
        CalendarImportRow $row,
        EvidenceTemplate $template,
        string $title
    ): ScheduledLoad {
        return ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => $title,
            'period_label' => 'Septiembre 2026',
            'original_open_at' => now()->subDay(),
            'original_close_at' => now()->addDay(),
            'effective_open_at' => now()->subDay(),
            'effective_close_at' => now()->addDay(),
            'status' => 'ABIERTA',
            'traffic_light' => 'BLUE',
            'priority' => 'NORMAL',
            'completion_percentage' => 0,
        ]);
    }
}
