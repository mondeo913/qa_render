<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ContractingAgency, ReviewAssignment, Role, ScheduledLoad, User, UserScope};
use App\Services\{AccessScopeService, QaUserStandardService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaUserStandardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
    }

    public function test_eight_general_accounts_cover_both_agencies_without_direction_leaks(): void
    {
        $users = app(QaUserStandardService::class)->sync();
        $this->assertCount(8, $users);
        $agencies = ContractingAgency::where('active', true)->pluck('id')->sort()->values()->all();
        foreach ($users as $user) {
            $this->assertNull($user->contracting_agency_id);
            $this->assertStringNotContainsString('IMSS', $user->name);
            $this->assertStringNotContainsString('IPAB', $user->name);
        }
        foreach (['enlace@siget.local', 'operador.monitoreo@siget.local', 'operador.produccion@siget.local'] as $email) {
            $ids = app(AccessScopeService::class)->readableAgencyIds($users[$email]);
            sort($ids);
            $this->assertSame($agencies, $ids);
        }
        foreach (['operador.monitoreo@siget.local' => 'DIR_A', 'operador.produccion@siget.local' => 'DIR_B'] as $email => $direction) {
            $codes = $users[$email]->scopes()->with('organizationalUnit')->get()->pluck('organizationalUnit.code')->unique()->all();
            $this->assertSame([$direction], $codes);
        }
    }

    public function test_retirement_preserves_history_passwords_and_unmanaged_accounts(): void
    {
        $users = app(QaUserStandardService::class)->sync();
        $password = $users['enlace@siget.local']->getRawOriginal('password');
        $alias = User::factory()->create(['email' => 'enlace.IPAB@siget.local', 'role_id' => $users['enlace@siget.local']->role_id,
            'metadata' => ['qa' => true]]);
        $audit = AuditLog::create(['user_id' => $alias->id, 'event' => 'ORIGINAL_ACTION', 'entity_type' => User::class, 'entity_id' => $alias->id]);
        $real = User::factory()->create(['role_id' => $users['enlace@siget.local']->role_id, 'metadata' => []]);
        $scope = UserScope::create(['user_id' => $real->id, 'contracting_agency_id' => ContractingAgency::first()->id, 'can_read' => true, 'can_write' => false]);
        app(QaUserStandardService::class)->sync();
        $this->assertSame('INACTIVE', $alias->fresh()->status);
        $this->assertSame($users['enlace@siget.local']->id, $alias->fresh()->canonicalIdentityId());
        $this->assertSame($alias->id, $audit->fresh()->user_id);
        $this->assertSame($password, $users['enlace@siget.local']->fresh()->getRawOriginal('password'));
        $this->assertSame('ACTIVE', $real->fresh()->status);
        $this->assertDatabaseHas('user_scopes', ['id' => $scope->id]);
        $this->actingAs($users['admin@siget.local'])->get(route('admin.users'))->assertOk()->assertDontSee('enlace.IPAB@siget.local');
        $this->patch(route('admin.users.toggle', $alias))->assertStatus(422);
        $this->assertSame('INACTIVE', $alias->fresh()->status);
    }

    public function test_fiscal_assignments_are_consolidated_idempotently(): void
    {
        $users = app(QaUserStandardService::class)->sync();
        $canonical = $users['fiscalizador@siget.local'];
        $alias = User::factory()->create(['email' => 'fiscalizador.IPAB@siget.local', 'role_id' => $canonical->role_id, 'metadata' => ['qa' => true]]);
        $load = ScheduledLoad::factory()->create();
        $assignment = ReviewAssignment::create(['scheduled_load_id' => $load->id, 'fiscalizador_id' => $alias->id, 'assigned_by' => $users['admin@siget.local']->id, 'active' => true]);
        app(QaUserStandardService::class)->sync();
        app(QaUserStandardService::class)->sync();
        $this->assertFalse($assignment->fresh()->active);
        $this->assertSame(1, ReviewAssignment::where('scheduled_load_id', $load->id)->where('fiscalizador_id', $canonical->id)->where('active', true)->count());
        $this->assertSame(8, User::where('metadata->qa', true)->where('status', 'ACTIVE')->count());
    }
}
