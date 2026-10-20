<?php

namespace Tests\Feature;

use App\Models\{Permission, Role, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_directors_validate_their_direction_without_institutional_closure(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach ([
            'DIRECTOR',
            'DIRECTOR_TRANSMISION',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD',
        ] as $roleCode) {
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
            $codes = $role->permissions()->pluck('code');

            $this->assertTrue($codes->contains('direction.dashboard'));
            $this->assertTrue($codes->contains('repository.view'));
            $this->assertTrue($codes->contains('evidence.review'));
            $this->assertFalse($codes->contains('scheduled_load.close'));
        }
    }

    public function test_specific_operators_can_upload_but_cannot_review_or_close(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach ([
            'OPERADOR_TRANSMISION',
            'OPERADOR_PROGRAMACION_CONTINUIDAD',
        ] as $roleCode) {
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
            $codes = $role->permissions()->pluck('code');

            $this->assertTrue($codes->contains('evidence.upload'));
            $this->assertFalse($codes->contains('evidence.review'));
            $this->assertFalse($codes->contains('scheduled_load.close'));
        }
    }

    public function test_institutional_link_keeps_review_validate_and_close_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::query()->where('code', 'ENLACE_INSTITUCIONAL')->firstOrFail();
        $codes = $role->permissions()->pluck('code');

        $this->assertTrue($codes->contains('evidence.review'));
        $this->assertTrue($codes->contains('scheduled_load.verify'));
        $this->assertTrue($codes->contains('scheduled_load.close'));
        $this->assertFalse($codes->contains('templates.manage'));
    }

    public function test_institutional_link_cannot_access_templates_but_admin_can(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $institutional = Role::query()->where('code', 'ENLACE_INSTITUCIONAL')->firstOrFail();
        $admin = Role::query()->where('code', 'ADMINISTRADOR')->firstOrFail();
        $this->assertTrue($admin->permissions()->where('code', 'templates.manage')->exists());

        $enlaceUser = User::factory()->create(['role_id' => $institutional->id, 'status' => 'ACTIVE']);
        $adminUser = User::factory()->create(['role_id' => $admin->id, 'status' => 'ACTIVE']);

        $this->actingAs($enlaceUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.templates').'"', false);
        $this->actingAs($enlaceUser)->get(route('admin.templates'))->assertForbidden();
        $this->actingAs($enlaceUser)->post(route('admin.templates.requirements.store'))->assertForbidden();

        $this->actingAs($adminUser)->get(route('admin.templates'))->assertOk();

        // Una asignación manual accidental del permiso tampoco debe reabrir el módulo.
        $permission = Permission::query()->where('code', 'templates.manage')->firstOrFail();
        $institutional->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs($enlaceUser)->get(route('admin.templates'))->assertForbidden();
        $this->actingAs($enlaceUser)->post(route('admin.templates.requirements.store'))->assertForbidden();

        // La migración corrige permisos heredados sin modificar los del Administrador.
        $migration = require base_path('database/migrations/2026_10_19_000001_revoke_templates_from_institutional_link.php');
        $migration->up();
        $this->assertFalse($institutional->permissions()->where('code', 'templates.manage')->exists());
        $this->assertTrue($admin->permissions()->where('code', 'templates.manage')->exists());
        $this->assertTrue(Permission::query()->where('code', 'templates.manage')->exists());
    }

}
