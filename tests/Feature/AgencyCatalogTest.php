<?php
namespace Tests\Feature;

use App\Models\{ContractingAgency, EvidenceTemplate, OrganizationalUnit, TemplateRequirement};
use Database\Seeders\AgencyTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_abbreviated_name_with_an_existing_code_is_not_duplicated(): void
    {
        $agency = ContractingAgency::where('code', 'SEGOB')->firstOrFail();
        $agency->update(['code' => 'GOB', 'name' => 'Sría. Gobernación', 'legal_name' => null]);
        $this->seed(AgencyTemplateSeeder::class);
        $this->assertSame(7, ContractingAgency::count());
        $this->assertSame('GOB', $agency->fresh()->code);
    }

    public function test_catalog_is_idempotent_and_each_agency_has_the_workflow(): void
    {
        $existing = ContractingAgency::where('code', 'IMSS')->firstOrFail();
        $existing->update(['name' => 'IMSS personalizado', 'active' => false]);
        $this->seed(AgencyTemplateSeeder::class);
        $this->seed(AgencyTemplateSeeder::class);
        $this->assertSame(7, ContractingAgency::count());
        $this->assertSame('IMSS personalizado', $existing->fresh()->name);
        $this->assertFalse($existing->fresh()->active);
        foreach (ContractingAgency::all() as $agency) {
            $units = OrganizationalUnit::where('contracting_agency_id', $agency->id)->get();
            $this->assertEqualsCanonicalizing(['DIR_A', 'DIR_B'], $units->pluck('code')->all());
            $template = EvidenceTemplate::where('contracting_agency_id', $agency->id)->sole();
            $requirements = TemplateRequirement::where('template_id', $template->id)->get();
            $this->assertCount(2, $requirements);
            foreach ($requirements as $requirement) {
                $this->assertTrue($units->contains('id', $requirement->responsible_unit_id));
                $this->assertTrue($requirement->required);
            }
        }
    }
}
