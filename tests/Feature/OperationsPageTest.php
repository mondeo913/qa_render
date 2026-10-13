<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Operations\DatabaseHealthService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_can_open_operations_on_the_configured_database(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach (['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'] as $code) {
            $user = User::factory()->create(['role_id' => Role::where('code', $code)->firstOrFail()->id, 'status' => 'ACTIVE']);
            $this->actingAs($user)->get(route('operations.index'))->assertOk()->assertSee('Centro de Operaciones SIGET');
        }
    }

    public function test_health_reports_real_metrics_for_the_database_driver(): void
    {
        $health = app(DatabaseHealthService::class)->inspect();
        $this->assertSame('UP', $health['status']);
        $this->assertSame(DB::connection()->getDriverName(), $health['driver']);
        $this->assertNotEmpty($health['version']);
        if ($health['driver'] === 'sqlite') {
            $this->assertNull($health['connections_active']);
        } else {
            $this->assertGreaterThanOrEqual(1, $health['connections_active']);
        }
    }
}
