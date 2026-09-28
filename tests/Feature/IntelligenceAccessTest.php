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
use App\Services\IntelligenceAnalyticsService;
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

    public function test_director_transmision_ve_unicamente_su_direccion_en_todos_los_kpis(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $tx = $agency->units()->where('code', 'DIR_A')->firstOrFail();
        $pc = $agency->units()->where('code', 'DIR_B')->firstOrFail();

        $director = User::factory()->create([
            'role_id' => Role::query()->where('code', 'DIRECTOR_TRANSMISION')->firstOrFail()->id,
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
            'original_filename' => 'Pauta Dirección Septiembre 2026.xlsx',
        ]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();

        $txLoad = $this->createLoad($agency, $import, $row, $template, 'Carga exclusiva Transmisión');
        $txLoad->update(['status' => 'VENCIDA']);

        $pcLoad = $this->createLoad($agency, $import, $row, $template, 'Carga exclusiva Programación');
        $pcLoad->update(['status' => 'VALIDADO_Y_CERRADO']);

        $txRequirement = $template->requirements()->where('responsible_unit_id', $tx->id)->firstOrFail();
        $pcRequirement = $template->requirements()->where('responsible_unit_id', $pc->id)->firstOrFail();

        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $txLoad->id,
            'template_requirement_id' => $txRequirement->id,
            'organizational_unit_id' => $tx->id,
            'responsible_user_id' => $director->id,
            'status' => 'PENDIENTE',
            'due_at' => now()->subDay(),
        ]);

        ScheduledLoadDeliverable::query()->create([
            'scheduled_load_id' => $pcLoad->id,
            'template_requirement_id' => $pcRequirement->id,
            'organizational_unit_id' => $pc->id,
            'status' => 'CERRADO',
            'due_at' => now()->subDay(),
        ]);

        $payload = app(IntelligenceAnalyticsService::class)->forUser($director);
        $this->assertCount(1, $payload['direction_performance']);
        $this->assertSame('DIRECCIÓN DE TRANSMISIÓN', $payload['direction_performance'][0]['name']);
        $this->assertSame(1, $payload['kpis']['total']);
        $this->assertSame(1, $payload['kpis']['overdue']);

        $response = $this->actingAs($director)->get(route('intelligence'));

        $response->assertOk();
        $response->assertSee('DIRECCIÓN DE TRANSMISIÓN');
        $response->assertSee('Carga exclusiva Transmisión');
        $response->assertDontSee('DIRECCIÓN DE PROGRAMACIÓN Y CONTINUIDAD');
        $response->assertDontSee('Carga exclusiva Programación');
        $response->assertDontSee('Riesgo por Dependencia');
    }

    public function test_directors_consolidan_dependencias_sin_cruzar_direcciones(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agencies = ContractingAgency::query()
            ->whereIn('code', ['IMSS', 'IPAB'])
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $agencies);

        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
        ]);

        $txAgencyLoads = [];
        $pcAgencyLoads = [];
        $txDirectors = [];
        $pcDirectors = [];

        foreach ($agencies as $agency) {
            $tx = $agency->units()->where('code', 'DIR_A')->firstOrFail();
            $pc = $agency->units()->where('code', 'DIR_B')->firstOrFail();
            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->firstOrFail();

            $txDirector = User::factory()->create([
                'role_id' => Role::query()->where('code', 'DIRECTOR_TRANSMISION')->firstOrFail()->id,
                'organizational_unit_id' => $tx->id,
                'name' => 'Director Transmisión '.$agency->code,
            ]);
            $pcDirector = User::factory()->create([
                'role_id' => Role::query()->where('code', 'DIRECTOR_PROGRAMACION_CONTINUIDAD')->firstOrFail()->id,
                'organizational_unit_id' => $pc->id,
                'name' => 'Director Programación '.$agency->code,
            ]);

            $txOperator = User::factory()->create([
                'role_id' => Role::query()->where('code', 'OPERADOR_TRANSMISION')->firstOrFail()->id,
                'organizational_unit_id' => $tx->id,
                'name' => 'Operador Transmisión '.$agency->code,
            ]);
            $pcOperator = User::factory()->create([
                'role_id' => Role::query()->where('code', 'OPERADOR_PROGRAMACION_CONTINUIDAD')->firstOrFail()->id,
                'organizational_unit_id' => $pc->id,
                'name' => 'Enlace Operativo Programación '.$agency->code,
            ]);

            $txImport = CalendarImport::factory()->create([
                'contracting_agency_id' => $agency->id,
                'uploaded_by' => $admin->id,
                'original_filename' => 'Pauta TX '.$agency->code.'.xlsx',
            ]);
            $pcImport = CalendarImport::factory()->create([
                'contracting_agency_id' => $agency->id,
                'uploaded_by' => $admin->id,
                'original_filename' => 'Pauta PC '.$agency->code.'.xlsx',
            ]);
            $txRow = CalendarImportRow::factory()->create(['calendar_import_id' => $txImport->id]);
            $pcRow = CalendarImportRow::factory()->create(['calendar_import_id' => $pcImport->id]);

            $txLoad = $this->createLoad($agency, $txImport, $txRow, $template, 'Carga TX '.$agency->code);
            $pcLoad = $this->createLoad($agency, $pcImport, $pcRow, $template, 'Carga PC '.$agency->code);

            $txRequirement = $template->requirements()->where('responsible_unit_id', $tx->id)->firstOrFail();
            $pcRequirement = $template->requirements()->where('responsible_unit_id', $pc->id)->firstOrFail();

            ScheduledLoadDeliverable::query()->create([
                'scheduled_load_id' => $txLoad->id,
                'template_requirement_id' => $txRequirement->id,
                'organizational_unit_id' => $tx->id,
                'responsible_user_id' => $txOperator->id,
                'status' => 'ENVIADO',
                'due_at' => now()->addDay(),
            ]);
            ScheduledLoadDeliverable::query()->create([
                'scheduled_load_id' => $pcLoad->id,
                'template_requirement_id' => $pcRequirement->id,
                'organizational_unit_id' => $pc->id,
                'responsible_user_id' => $pcOperator->id,
                'status' => 'ENVIADO',
                'due_at' => now()->addDay(),
            ]);

            $txAgencyLoads[] = [$agency->name, $txLoad->title];
            $pcAgencyLoads[] = [$agency->name, $pcLoad->title];
            $txDirectors[] = $txDirector;
            $pcDirectors[] = $pcDirector;
        }

        $txDirector = $txDirectors[0];
        $pcDirector = $pcDirectors[0];

        $txAnalytics = app(IntelligenceAnalyticsService::class)->forUser($txDirector);
        $pcAnalytics = app(IntelligenceAnalyticsService::class)->forUser($pcDirector);

        $this->assertSame(2, $txAnalytics['kpis']['total']);
        $this->assertSame(2, $pcAnalytics['kpis']['total']);
        $this->assertCount(1, $txAnalytics['direction_performance']);
        $this->assertCount(1, $pcAnalytics['direction_performance']);
        $this->assertSame('DIRECCIÓN DE TRANSMISIÓN', $txAnalytics['direction_performance'][0]['name']);
        $this->assertSame('DIRECCIÓN DE PROGRAMACIÓN Y CONTINUIDAD', $pcAnalytics['direction_performance'][0]['name']);

        $txDashboard = $this->actingAs($txDirector)->get(route('dashboard'));
        $txDashboard->assertOk();
        foreach ($txAgencyLoads as [$agencyName, $title]) {
            $txDashboard->assertSee($agencyName);
            $txDashboard->assertSee($title);
        }
        foreach ($pcAgencyLoads as [$agencyName, $title]) {
            $txDashboard->assertDontSee($title);
        }

        $pcDashboard = $this->actingAs($pcDirector)->get(route('dashboard'));
        $pcDashboard->assertOk();
        foreach ($pcAgencyLoads as [$agencyName, $title]) {
            $pcDashboard->assertSee($agencyName);
            $pcDashboard->assertSee($title);
        }
        foreach ($txAgencyLoads as [$agencyName, $title]) {
            $pcDashboard->assertDontSee($title);
        }

        $pcResponsibleNames = collect($pcAnalytics['evidence_by_responsible'] ?? [])
            ->pluck('responsible')
            ->all();
        $this->assertContains('Enlace Operativo Programación '.$agencies[0]->code, $pcResponsibleNames);
        $this->assertContains('Enlace Operativo Programación '.$agencies[1]->code, $pcResponsibleNames);
    }

    public function test_ejecutivos_ven_solo_estados_principales_y_clasificacion_por_pauta(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);

        $agency = ContractingAgency::query()->where('code', 'IMSS')->firstOrFail();
        $admin = User::factory()->create([
            'role_id' => Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail()->id,
            'contracting_agency_id' => $agency->id,
        ]);
        $import = CalendarImport::factory()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $admin->id,
            'original_filename' => 'Pauta Ejecutiva Septiembre 2026.xlsx',
        ]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
        $template = EvidenceTemplate::query()
            ->where('contracting_agency_id', $agency->id)
            ->where('code', 'PAUTA_MENSUAL')
            ->firstOrFail();

        $load = $this->createLoad($agency, $import, $row, $template, 'Carga Ejecutiva');
        $load->update(['status' => 'VALIDADA']);

        $response = $this->actingAs($admin)->get(route('intelligence'));

        $response->assertOk();
        $response->assertSee('Clasificación ejecutiva por Pauta y Dependencia');
        $response->assertSee('Pauta Ejecutiva Septiembre 2026.xlsx');
        $response->assertSee('Programadas');
        $response->assertSee('Reprogramadas');
        $response->assertSee('Validadas');
        $response->assertSee('Cerradas');
        $response->assertSee('Vencidas');
        $response->assertDontSee('Reprogramada abierta');
        $response->assertDontSee('Pendiente firma');
        $response->assertDontSee('Suspendida');

        $filtered = $this->actingAs($admin)->get(route('intelligence', ['status' => 'VALIDADA']));
        $filtered->assertOk()->assertSee('Pauta Ejecutiva Septiembre 2026.xlsx');
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
