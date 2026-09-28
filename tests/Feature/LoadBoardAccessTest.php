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
use App\Models\UserScope;
use Database\Seeders\AgencyTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadBoardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_transmission_operator_sees_only_transmission_loads_on_board(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $transmission = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $programming = $agency->units()->where('code', 'DIR_B')->firstOrFail();

        $operator = User::factory()->create([
            'role_id' => Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
            'organizational_unit_id' => $transmission->id,
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

        $txLoad = $this->createLoad($agency, $import, $row, $template, 'Carga visible de Transmisión', 'ABIERTA');
        $pcLoad = $this->createLoad($agency, $import, $row, $template, 'Carga privada de Programación', 'ENTREGADA');

        $txRequirement = $template->requirements()->where('responsible_unit_id', $transmission->id)->firstOrFail();
        $pcRequirement = $template->requirements()->where('responsible_unit_id', $programming->id)->firstOrFail();

        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $txLoad->id,
            'template_requirement_id' => $txRequirement->id,
            'organizational_unit_id' => $transmission->id,
            'responsible_user_id' => $operator->id,
            'status' => 'PENDIENTE',
        ]);
        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $pcLoad->id,
            'template_requirement_id' => $pcRequirement->id,
            'organizational_unit_id' => $programming->id,
            'status' => 'EN_REVISION',
        ]);

        $response = $this->actingAs($operator)->get(route('loads.board'));

        $response->assertOk();
        $response->assertSee('Por hacer');
        $response->assertSee('En progreso');
        $response->assertSee('En revisión');
        $response->assertSee('Validadas y cerradas');
        $response->assertSee('Carga visible de Transmisión');
        $response->assertDontSee('Carga privada de Programación');
        $response->assertSee('Dirección de Transmisión');
    }


    public function test_operator_catalog_can_include_multiple_scoped_dependencies(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $imss = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $ipab = ContractingAgency::query()->where('code', 'IPAB')->firstOrFail();
        $imssTx = $imss->units()->where('code', 'DIR_A')->firstOrFail();
        $ipabTx = $ipab->units()->where('code', 'DIR_A')->firstOrFail();

        $operator = User::factory()->create([
            'role_id' => Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail()->id,
            'contracting_agency_id' => $imss->id,
            'organizational_unit_id' => $imssTx->id,
        ]);
        UserScope::query()->create([
            'user_id' => $operator->id,
            'contracting_agency_id' => $ipab->id,
            'organizational_unit_id' => $ipabTx->id,
            'can_read' => true,
            'can_write' => true,
        ]);

        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
            'contracting_agency_id' => $imss->id,
        ]);

        foreach ([[$imss, $imssTx, 'Carga IMSS Transmisión'], [$ipab, $ipabTx, 'Carga IPAB Transmisión']] as [$agency, $unit, $title]) {
            $import = CalendarImport::factory()->create([
                'contracting_agency_id' => $agency->id,
                'uploaded_by' => $admin->id,
            ]);
            $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->firstOrFail();
            $load = $this->createLoad($agency, $import, $row, $template, $title, 'ABIERTA');
            $requirement = $template->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
            ScheduledLoadDeliverable::query()->create([
                'scheduled_load_id' => $load->id,
                'template_requirement_id' => $requirement->id,
                'organizational_unit_id' => $unit->id,
                'responsible_user_id' => $operator->id,
                'status' => 'PENDIENTE',
            ]);
        }

        $response = $this->actingAs($operator)->get(route('loads.board'));

        $response->assertOk();
        $response->assertSee('Carga IMSS Transmisión');
        $response->assertSee('Carga IPAB Transmisión');
        $response->assertSee('IMSS');
        $response->assertSee('IPAB');
    }

    public function test_direction_directors_see_only_same_direction_pautas_and_can_filter_by_excel(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $imss = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $ipab = ContractingAgency::query()->where('code', 'IPAB')->firstOrFail();
        $imssTx = $imss->units()->where('code', 'DIR_A')->firstOrFail();
        $imssPc = $imss->units()->where('code', 'DIR_B')->firstOrFail();
        $ipabTx = $ipab->units()->where('code', 'DIR_A')->firstOrFail();
        $ipabPc = $ipab->units()->where('code', 'DIR_B')->firstOrFail();

        $pcDirector = User::factory()->create([
            'role_id' => Role::query()->where('code', 'DIRECTOR_PROGRAMACION_CONTINUIDAD')->firstOrFail()->id,
            'organizational_unit_id' => $imssPc->id,
        ]);

        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
        ]);

        $rows = [];
        foreach ([
            [$imss, $imssPc, 'Pauta PC IMSS.xlsx', 'Carga PC IMSS'],
            [$ipab, $ipabPc, 'Pauta PC IPAB.xlsx', 'Carga PC IPAB'],
            [$imss, $imssTx, 'Pauta TX IMSS.xlsx', 'Carga TX IMSS'],
            [$ipab, $ipabTx, 'Pauta TX IPAB.xlsx', 'Carga TX IPAB'],
        ] as [$agency, $unit, $filename, $title]) {
            $import = CalendarImport::factory()->create([
                'contracting_agency_id' => $agency->id,
                'uploaded_by' => $admin->id,
                'original_filename' => $filename,
            ]);
            $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->firstOrFail();
            $load = $this->createLoad($agency, $import, $row, $template, $title, 'PROGRAMADA');
            $requirement = $template->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
            ScheduledLoadDeliverable::query()->create([
                'scheduled_load_id' => $load->id,
                'template_requirement_id' => $requirement->id,
                'organizational_unit_id' => $unit->id,
                'status' => 'PENDIENTE',
            ]);
            $rows[$filename] = $load;
        }

        $response = $this->actingAs($pcDirector)->get(route('loads.board'));
        $response->assertOk();
        $response->assertSee('Pauta PC IMSS.xlsx');
        $response->assertSee('Pauta PC IPAB.xlsx');
        $response->assertDontSee('Pauta TX IMSS.xlsx');
        $response->assertDontSee('Pauta TX IPAB.xlsx');
        $response->assertSee('Dirección de Programación y Continuidad');
        $response->assertDontSee('Dirección de Transmisión');

        $filtered = $this->actingAs($pcDirector)->get(route('loads.board', [
            'pauta_id' => $rows['Pauta PC IMSS.xlsx']->calendar_import_id,
        ]));
        $filtered->assertOk();
        $filtered->assertSee('Carga PC IMSS');
        $filtered->assertDontSee('Carga PC IPAB');
        $filtered->assertDontSee('Carga TX IMSS');
        $filtered->assertDontSee('Carga TX IPAB');
    }

    public function test_direction_board_period_bounds_follow_selected_pauta_and_dependency(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $pc = $agency->units()->where('code', 'DIR_B')->firstOrFail();
        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
        ]);
        $director = User::factory()->create([
            'role_id' => Role::query()->where('code', 'DIRECTOR_PROGRAMACION_CONTINUIDAD')->firstOrFail()->id,
            'organizational_unit_id' => $pc->id,
        ]);

        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();

        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
            'original_filename' => 'Pauta PC Periodo.xlsx',
        ]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);

        $load = ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => 'Carga PC Periodo',
            'period_label' => 'Agosto 2026',
            'original_open_at' => '2026-08-10 08:00:00',
            'original_close_at' => '2026-08-20 18:00:00',
            'effective_open_at' => '2026-08-10 08:00:00',
            'effective_close_at' => '2026-08-20 18:00:00',
            'status' => 'PROGRAMADA',
            'traffic_light' => 'GRAY',
        ]);

        $requirement = $template->requirements()->where('responsible_unit_id', $pc->id)->firstOrFail();
        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $load->id,
            'template_requirement_id' => $requirement->id,
            'organizational_unit_id' => $pc->id,
            'status' => 'PENDIENTE',
        ]);

        $response = $this->actingAs($director)->get(route('loads.board', [
            'pauta_id' => $import->id,
            'agency_id' => $agency->id,
        ]));

        $response->assertOk();
        $response->assertSee('Pauta PC Periodo.xlsx');
        $response->assertSee('2026-08 → 2026-08');
        $response->assertSee('El rango se limita a los meses contratados disponibles para la pauta, dependencia y Dirección seleccionadas.');
    }

    private function createLoad(
        ContractingAgency $agency,
        CalendarImport $import,
        CalendarImportRow $row,
        EvidenceTemplate $template,
        string $title,
        string $status
    ): ScheduledLoad {
        return ScheduledLoad::query()->create([
            'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id,
            'contracting_agency_id' => $agency->id,
            'template_id' => $template->id,
            'title' => $title,
            'period_label' => 'Agosto 2026',
            'original_open_at' => now()->subDay(),
            'original_close_at' => now()->addDay(),
            'effective_open_at' => now()->subDay(),
            'effective_close_at' => now()->addDay(),
            'status' => $status,
            'traffic_light' => 'GREEN',
            'priority' => 'HIGH',
            'completion_percentage' => 0,
        ]);
    }
}
