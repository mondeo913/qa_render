<?php
namespace Tests\Feature;
use App\Models\Role;
use App\Models\User;
use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
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
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Dashboard de Transmisión');
        $this->assertFalse($role->permissions()->where('code','scheduled_load.close')->exists());
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
                ->assertSee($label.' · cumplimiento institucional de evidencias');
        }
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

        foreach (['ADMINISTRADOR', 'DIRECTOR_GENERAL'] as $code) {
            $role = Role::where('code', $code)->firstOrFail();
            $user = User::factory()->create(['role_id' => $role->id, 'status' => 'ACTIVE']);

            $this->actingAs($user)
                ->get(route('dashboard', ['agency_id' => $agency->id]))
                ->assertOk()
                ->assertSee('Dependencia nueva')
                ->assertSee('value="'.$agency->id.'" selected', false)
                ->assertSee('Spots programados');
        }
    }
}
