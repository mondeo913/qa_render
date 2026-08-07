<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_directors_have_supervision_without_evidence_review(): void
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
            $this->assertFalse($codes->contains('evidence.review'));
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
    }
}
