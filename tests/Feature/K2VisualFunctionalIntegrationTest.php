<?php
namespace Tests\Feature;
use App\Models\Role;
use App\Models\User;
use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class K2VisualFunctionalIntegrationTest extends TestCase {
    use RefreshDatabase;
    public function test_login_uses_correct_branding_and_recovery_route(): void {
        $response=$this->get(route('login'));
        $response->assertOk()->assertSee('Sistema de Gestión de Evidencias de Transmisión')->assertSee('¿Olvidó su contraseña?')->assertDontSee('Sistema Institucional de Gestión de Entregas');
        $this->assertTrue(route('password.request')!=='');
    }
    public function test_institutional_link_has_review_inbox_and_kanban_permissions(): void {
        $this->seed(RolePermissionSeeder::class);
        $role=Role::where('code','ENLACE_INSTITUCIONAL')->firstOrFail();
        $codes=$role->permissions()->pluck('code');
        $this->assertTrue($codes->contains('scheduled_load.review'));
        $this->assertTrue($codes->contains('scheduled_load.board'));
        $this->assertTrue($codes->contains('scheduled_load.close'));
    }
    public function test_director_dashboard_renders_without_review_permissions(): void {
        $this->seed(RolePermissionSeeder::class);
        $role=Role::where('code','DIRECTOR_TRANSMISION')->firstOrFail();
        $user=User::factory()->create(['role_id'=>$role->id,'status'=>'ACTIVE']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Dashboard operativo')->assertSee('Dirección de Transmisión');
        $this->assertTrue($role->permissions()->where('code','scheduled_load.close')->exists());
    }

    public function test_operational_dashboard_uses_standardized_geometry_and_keeps_filter_flow(): void {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::where('code', 'DIRECTOR_TRANSMISION')->firstOrFail();
        $agency = ContractingAgency::factory()->create(['name' => 'Dependencia estándar']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'ACTIVE',
            'contracting_agency_id' => $agency->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'agency_id' => $agency->id,
        ]));

        $response->assertOk()
            ->assertSee('Dashboard operativo')
            ->assertSee('Avance por unidad interna')
            ->assertSee('Calendario de fechas programadas')
            ->assertSee('Estado del expediente')
            ->assertSee('Tendencia de entregas')
            ->assertSee('Carga por responsable')
            ->assertSee('Bandeja de evidencias')
            ->assertSee('Exportar')
            ->assertSee('Más filtros')
            ->assertSee('name="agency_id"', false)
            ->assertSee('name="pauta_id"', false)
            ->assertSee('name="organizational_unit_id"', false)
            ->assertSee('name="responsible_id"', false)
            ->assertSee('value="'.$agency->id.'" selected', false);
    }

    public function test_executive_dashboard_is_available_to_admin_and_general_director(): void {
        $this->seed(RolePermissionSeeder::class);

        foreach (['ADMINISTRADOR' => 'Administrador', 'DIRECTOR_GENERAL' => 'Director General'] as $code => $label) {
            $role = Role::where('code', $code)->firstOrFail();
            $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Dashboard ejecutivo')
                ->assertSee($label.' · cumplimiento institucional de evidencias')
                ->assertSee('Comparativo por dirección')
                ->assertSee('tres barras por dirección')
                ->assertSee('"label":"Esperadas"', false)
                ->assertSee('"label":"Recibidas"', false)
                ->assertSee('"label":"Validadas"', false)
                ->assertSee('exec-comparison-card', false);
        }

        $template = file_get_contents(resource_path('views/dashboard/executive.blade.php'));
        $this->assertStringContainsString('agency-ipab-official.png', $template);
        $this->assertStringContainsString('agency-imss-official.png', $template);
    }

    public function test_global_dashboard_filters_include_new_agencies_without_loads(): void {
        $this->seed(RolePermissionSeeder::class);
        $agency = ContractingAgency::factory()->create(['code' => 'NEW_FILTER', 'name' => 'Dependencia nueva']);
        OrganizationalUnit::query()->create([
            'contracting_agency_id' => $agency->id,
            'code' => 'DIR_A',
            'name' => 'Dirección de Transmisión',
            'unit_type' => 'DIRECTION',
            'active' => true,
        ]);
        OrganizationalUnit::query()->create([
            'contracting_agency_id' => $agency->id,
            'code' => 'AREA_DEPENDENCIA',
            'name' => 'Nombre que no debe aparecer como dirección',
            'unit_type' => 'AREA',
            'active' => true,
        ]);

        foreach (['ADMINISTRADOR', 'DIRECTOR_GENERAL'] as $code) {
            $role = Role::where('code', $code)->firstOrFail();
            $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);

            $this->actingAs($user)
                ->get(route('dashboard', ['agency_id' => $agency->id]))
                ->assertOk()
                ->assertSee('Dependencia nueva')
                ->assertSee('value="'.$agency->id.'" selected', false)
                ->assertSee('Dirección de Transmisión')
                ->assertDontSee('Nombre que no debe aparecer como dirección')
                ->assertSee('Spots programados');
        }
    }

    public function test_campaign_filter_only_lists_pautas_from_selected_agency(): void {
        $this->seed(RolePermissionSeeder::class);
        $firstLoad = ScheduledLoad::factory()->create(['title' => 'Pauta Bienestar']);
        $secondLoad = ScheduledLoad::factory()->create(['title' => 'Pauta Salud']);
        $role = Role::where('code', 'DIRECTOR_GENERAL')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);

        $this->actingAs($user)
            ->get(route('dashboard', ['agency_id' => $firstLoad->contracting_agency_id]))
            ->assertOk()
            ->assertSee('Pauta Bienestar')
            ->assertDontSee('Pauta Salud');
    }

    public function test_campaign_filter_lists_multiple_pautas_from_same_agency(): void {
        $this->seed(RolePermissionSeeder::class);
        $firstLoad = ScheduledLoad::factory()->create(['title' => 'Pauta Enero']);
        $secondLoad = ScheduledLoad::factory()->create(['title' => 'Pauta Febrero']);
        $secondLoad->update(['contracting_agency_id' => $firstLoad->contracting_agency_id]);
        $role = Role::where('code', 'DIRECTOR_GENERAL')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);

        $this->actingAs($user)
            ->get(route('dashboard', ['agency_id' => $firstLoad->contracting_agency_id]))
            ->assertOk()
            ->assertSee('Pauta Enero')
            ->assertSee('Pauta Febrero');

        $this->actingAs($user)
            ->get(route('dashboard', [
                'agency_id' => $firstLoad->contracting_agency_id,
                'campaign' => 'Pauta Febrero',
            ]))
            ->assertOk()
            ->assertSee('name="campaign"', false)
            ->assertSee('value="Pauta Febrero" selected', false);
    }
}
