<?php
namespace Tests\Feature;

use App\Models\{ContractingAgency, EvidenceTemplate, Role, User, UserScope};
use App\Services\{AccessScopeService, AgencyWorkflowService, QaUserStandardService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_agency_is_repaired_without_changing_existing_permissions_or_templates(): void
    {
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $users = app(QaUserStandardService::class)->sync();
        $agency = ContractingAgency::create(['code' => 'REPARAR', 'name' => 'Dependencia existente', 'active' => true]);
        $restricted = User::factory()->create(['role_id' => Role::where('code', 'ENLACE_INSTITUCIONAL')->value('id')]);
        $service = app(AgencyWorkflowService::class);
        $service->provision($agency);
        $template = EvidenceTemplate::where('contracting_agency_id', $agency->id)->firstOrFail();
        $template->update(['name' => 'Plantilla personalizada', 'requires_director_signature' => false]);
        $scope = $users['operador.monitoreo@siget.local']->scopes()->where('contracting_agency_id', $agency->id)->firstOrFail();
        $scope->update(['can_write' => false]);
        $users['enlace@siget.local']->scopes()->where('contracting_agency_id', $agency->id)->delete();
        $service->provision($agency);
        $service->provision($agency);
        $this->assertSame(8, UserScope::where('contracting_agency_id', $agency->id)->count());
        $this->assertFalse($scope->fresh()->can_write);
        $this->assertSame('Plantilla personalizada', $template->fresh()->name);
        $this->assertFalse($template->fresh()->requires_director_signature);
        $this->assertSame(2, $template->requirements()->count());
        $this->assertSame(0, $restricted->scopes()->where('contracting_agency_id', $agency->id)->count());
        $this->assertFalse(app(AccessScopeService::class)->availableAgencies($restricted)->contains('id', $agency->id));
        $this->assertFalse(app(AccessScopeService::class)->availableAgencies($users['fiscalizador@siget.local'])->contains('id', $agency->id));
        $this->assertTrue(app(AccessScopeService::class)->availableAgencies($users['enlace@siget.local'])->contains('id', $agency->id));
    }
}
