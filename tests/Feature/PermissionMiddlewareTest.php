<?php
namespace Tests\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_import_calendar(): void
    {
        $role = Role::query()->create(['code'=>'OPERADOR_TRANSMISION','name'=>'Operativo de Transmisión']);
        $user = User::query()->create([
            'role_id'=>$role->id,'name'=>'Operador','email'=>'operador@example.test',
            'password'=>'secret','status'=>'ACTIVE',
        ]);

        $this->actingAs($user)->post('/calendario/importar')->assertForbidden();
    }
}
