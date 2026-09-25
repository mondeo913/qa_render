<?php

namespace Tests\Feature;

use App\Models\ContractingAgency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_edit_form_and_update_an_agency(): void
    {
        [$user, $permission] = $this->adminWithAgencyPermission();
        $agency = ContractingAgency::query()->create([
            'code' => 'OLD',
            'name' => 'Dependencia anterior',
            'legal_name' => 'Razón anterior',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.agencies'))
            ->assertOk()
            ->assertSee('Editar')
            ->assertSee('Guardar cambios')
            ->assertSee(route('admin.agencies.update', $agency), false);

        $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->patch(route('admin.agencies.update', $agency), [
                '_token' => 'test-token',
                'code' => 'NEW',
                'name' => 'Dependencia actualizada',
                'legal_name' => 'Razón actualizada',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contracting_agencies', [
            'id' => $agency->id,
            'code' => 'NEW',
            'name' => 'Dependencia actualizada',
            'legal_name' => 'Razón actualizada',
        ]);
    }

    public function test_agency_code_must_remain_unique_when_editing(): void
    {
        [$user] = $this->adminWithAgencyPermission();
        $agency = ContractingAgency::query()->create(['code' => 'ONE', 'name' => 'Una', 'active' => true]);
        ContractingAgency::query()->create(['code' => 'TWO', 'name' => 'Dos', 'active' => true]);

        $this->withSession(['_token' => 'test-token'])
            ->actingAs($user)
            ->patch(route('admin.agencies.update', $agency), [
                '_token' => 'test-token',
                'code' => 'TWO',
                'name' => 'Cambio inválido',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('contracting_agencies', [
            'id' => $agency->id,
            'code' => 'ONE',
            'name' => 'Una',
        ]);
    }

    private function adminWithAgencyPermission(): array
    {
        $role = Role::query()->create([
            'code' => 'ADMINISTRADOR',
            'name' => 'Administrador',
            'active' => true,
        ]);
        $permission = Permission::query()->create([
            'code' => 'agencies.manage',
            'name' => 'Administrar dependencias',
            'module' => 'administration',
        ]);
        $role->permissions()->attach($permission);

        return [User::query()->create([
            'role_id' => $role->id,
            'name' => 'Administrador QA',
            'email' => 'admin-agencies@example.test',
            'password' => 'secret',
            'status' => 'ACTIVE',
        ]), $permission];
    }
}
