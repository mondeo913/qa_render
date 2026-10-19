<?php

namespace Tests\Feature;

use App\Models\{ContractingAgency, ReviewAssignment, Role, ScheduledLoad, ScheduledLoadDeliverable, User};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Bus};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserModuleFlowTest extends TestCase
{
    use RefreshDatabase;

    private ScheduledLoad $load;
    private array $users = [];

    public static function profiles(): array
    {
        return array_combine(self::roles(), array_map(fn ($role) => [$role], self::roles()));
    }

    private static function roles(): array
    {
        return ['ADMINISTRADOR', 'DIRECTOR_GENERAL', 'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD',
            'ENLACE_INSTITUCIONAL', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD', 'FISCALIZADOR'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
        Bus::fake();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $agency = ContractingAgency::where('code', 'IMSS')->firstOrFail();
        foreach (self::roles() as $role) {
            $direction = str_contains($role, 'PROGRAMACION') ? 'DIR_B' : (str_contains($role, 'TRANSMISION') ? 'DIR_A' : null);
            $this->users[$role] = User::factory()->create(['role_id' => Role::where('code', $role)->value('id'),
                'contracting_agency_id' => $agency->id,
                'organizational_unit_id' => $direction ? $agency->units()->where('code', $direction)->value('id') : null]);
        }
        $template = $agency->templates()->firstOrFail();
        $this->load = ScheduledLoad::factory()->create(['contracting_agency_id' => $agency->id, 'template_id' => $template->id,
            'effective_open_at' => now()->subHour(), 'effective_close_at' => now()->addHour(),
            'status' => 'ABIERTA', 'is_blocked' => false]);
        foreach (['DIR_A' => 'OPERADOR_TRANSMISION', 'DIR_B' => 'OPERADOR_PROGRAMACION_CONTINUIDAD'] as $direction => $role) {
            $unitId = $this->users[$role]->organizational_unit_id;
            ScheduledLoadDeliverable::create(['scheduled_load_id' => $this->load->id,
                'template_requirement_id' => $template->requirements()->where('responsible_unit_id', $unitId)->value('id'),
                'organizational_unit_id' => $unitId, 'responsible_user_id' => $this->users[$role]->id,
                'status' => 'PENDIENTE', 'due_at' => now()->addHour()]);
        }
        ReviewAssignment::create(['scheduled_load_id' => $this->load->id, 'fiscalizador_id' => $this->users['FISCALIZADOR']->id,
            'assigned_by' => $this->users['ENLACE_INSTITUCIONAL']->id, 'active' => true]);
    }

    #[DataProvider('profiles')]
    public function test_module_entry_points_enforce_the_role_matrix_and_do_not_change_the_expediente(string $role): void
    {
        $admin = ['ADMINISTRADOR'];
        $institutional = ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'];
        $capture = ['ADMINISTRADOR', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'];
        $alerts = ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL', 'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD',
            'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'];
        $matrix = [
            'dashboard' => self::roles(), 'calendar.index' => self::roles(), 'loads.board' => self::roles(),
            'repository.index' => self::roles(), 'reports.index' => self::roles(), 'history.index' => self::roles(),
            'intelligence' => self::roles(), 'admin.users' => $admin, 'admin.roles' => $admin, 'admin.agencies' => $admin,
            'admin.catalogs' => $admin, 'admin.settings' => $admin, 'admin.logs' => $admin,
            'admin.templates' => $institutional, 'review.inbox' => $institutional, 'loads.mine' => $capture,
            'alerts.index' => $alerts, 'operations.index' => $institutional, 'operations.backups' => $institutional,
        ];
        $before = [$this->load->fresh()->getAttributes(), $this->load->deliverables()->get()->toArray(), User::count()];
        $this->actingAs($this->users[$role]);
        foreach ($matrix as $route => $allowed) {
            $this->get(route($route))->assertStatus(in_array($role, $allowed, true) ? 200 : 403);
        }
        $this->get(route('loads.show', $this->load))->assertOk();
        $this->assertSame($before, [$this->load->fresh()->getAttributes(), $this->load->deliverables()->get()->toArray(), User::count()]);

        // The API uses a real bearer token and must preserve the same module boundary.
        Auth::forgetGuards();
        $user = $this->users[$role];
        $this->withToken($user->createToken('module-audit', ['siget:api', 'role:'.$user->role_id], now()->addHour())->plainTextToken);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->getJson('/api/v1/loads/'.$this->load->id)->assertOk()
            ->assertJsonCount(in_array($role, ['DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD',
                'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'], true) ? 1 : 2, 'data.deliverables');
        foreach (['users', 'agencies', 'roles'] as $catalog) {
            $this->getJson('/api/v1/admin/'.$catalog)->assertStatus($role === 'ADMINISTRADOR' ? 200 : 403);
        }
        $this->get('/api/v1/reports/csv')->assertStatus(in_array($role, ['ADMINISTRADOR', 'DIRECTOR_GENERAL', 'ENLACE_INSTITUCIONAL', 'FISCALIZADOR'], true) ? 200 : 403);
        if (!in_array($role, $institutional, true)) {
            foreach (['close', 'reopen', 'corrections', 'signature-package'] as $action) {
                $this->postJson('/api/v1/loads/'.$this->load->id.'/'.$action)->assertForbidden();
            }
            $this->postJson(route('loads.close', $this->load))->assertForbidden();
            $this->postJson(route('loads.reopen', $this->load))->assertForbidden();
        }
        $this->assertSame('ABIERTA', $this->load->fresh()->status->value);
        $this->assertSame(0, $this->load->closure()->count());
    }

    public function test_withdrawing_fiscal_assignment_removes_all_read_entry_points_immediately(): void
    {
        $user = $this->users['FISCALIZADOR'];
        $this->actingAs($user)->get(route('loads.show', $this->load))->assertOk();
        Auth::forgetGuards();
        $this->withToken($user->createToken('fiscal-audit', ['siget:api', 'role:'.$user->role_id], now()->addHour())->plainTextToken);
        $this->getJson('/api/v1/loads/'.$this->load->id)->assertOk();
        ReviewAssignment::query()->where('scheduled_load_id', $this->load->id)->update(['active' => false]);
        $this->getJson('/api/v1/loads/'.$this->load->id)->assertForbidden();
        $this->getJson('/api/v1/loads')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/agencies')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/calendar/events?start=2026-10-01&end=2026-11-01')->assertOk()->assertExactJson([]);
        $this->actingAs($user)->get(route('loads.show', $this->load))->assertForbidden();
    }
}
