<?php
namespace Tests\Feature;

use App\Models\{AuditLog, User};
use App\Services\QaUserStandardService;
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserEditTest extends TestCase
{
    use RefreshDatabase;
    private array $users;
    protected function setUp(): void {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $this->users = app(QaUserStandardService::class)->sync();
        $this->actingAs($this->users['admin@siget.local']);
    }
    private function payload(User $user, string $email): array {
        return ['name' => 'Enlace institucional actualizado', 'email' => $email, 'role_id' => $user->role_id,
            'contracting_agency_id' => '', 'organizational_unit_id' => '', 'password' => '', '_editing_user_id' => $user->id];
    }
    public function test_administrator_can_edit_general_link_without_losing_access_password_or_identity(): void {
        $user = $this->users['enlace@siget.local'];
        $user->update(['password' => 'ClavePrueba_2026!']);
        $password = $user->getRawOriginal('password');
        $scopeIds = $user->scopes()->pluck('id')->all();
        $this->from(route('admin.users'))->patch(route('admin.users.update', $user), $this->payload($user, 'enlace_institucional@siget.local'))
            ->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();
        $this->assertSame('enlace_institucional@siget.local', $user->fresh()->email);
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        $this->assertSame($scopeIds, $user->scopes()->pluck('id')->all());
        $this->assertTrue(AuditLog::where('entity_id', $user->id)->where('event', 'user.updated')->exists());
        app(QaUserStandardService::class)->sync();
        app(QaUserStandardService::class)->sync();
        $this->assertSame(8, User::where('metadata->qa', true)->where('status', 'ACTIVE')->count());
        $this->assertSame('enlace_institucional@siget.local', $user->fresh()->email);
        $this->assertSame('Enlace institucional actualizado', $user->fresh()->name);
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        auth()->logout();
        $this->post(route('login.store'), ['email' => 'enlace_institucional@siget.local', 'password' => 'ClavePrueba_2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get(route('calendar.index'))->assertOk()->assertViewHas('importAgencies', fn ($agencies) => $agencies->count() === 7);
    }
    public function test_incomplete_or_duplicate_email_is_rejected_and_edit_form_stays_open(): void {
        $user = $this->users['enlace@siget.local'];
        $this->from(route('admin.users'))->patch(route('admin.users.update', $user), $this->payload($user, 'enlace_institucional@'))
            ->assertRedirect(route('admin.users'))->assertSessionHasErrors('email');
        $this->get(route('admin.users'))->assertOk()->assertSee('Escriba un correo completo')
            ->assertSee('id="user-edit-'.$user->id.'" class=" bg-light"', false)
            ->assertSee('value="enlace_institucional@"', false);
        $this->assertSame('enlace@siget.local', $user->fresh()->email);
        $this->patch(route('admin.users.update', $user), $this->payload($user, 'admin@siget.local'))->assertSessionHasErrors('email');
        $this->assertSame('enlace@siget.local', $user->fresh()->email);
    }
    public function test_link_without_any_scope_is_not_granted_access_by_editing_name(): void {
        $user = $this->users['enlace@siget.local'];
        $user->scopes()->delete();
        $this->patch(route('admin.users.update', $user), $this->payload($user, $user->email))->assertSessionHasErrors('contracting_agency_id');
        $this->actingAs($this->users['operador.produccion@siget.local'])
            ->patch(route('admin.users.update', $user), $this->payload($user, $user->email))->assertForbidden();
    }
}
