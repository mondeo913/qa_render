<?php
namespace Tests\Feature;
use App\Models\Role;
use App\Models\User;
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
}
