<?php
namespace Tests\Feature;

use App\Models\{CalendarImport, CalendarImportRow, ContractingAgency, EvidenceTemplate, ScheduledLoad, ScheduledLoadDeliverable};
use App\Services\{LoadBoardService, QaUserStandardService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadBoardFilterFlowTest extends TestCase
{
    use RefreshDatabase;

    public static function directions(): array { return [['DIR_A', 'operador.monitoreo@siget.local'], ['DIR_B', 'operador.produccion@siget.local']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('directions')]
    public function test_filter_catalogs_remain_complete_when_switching_agencies_and_pautas(string $direction, string $email): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $users = app(QaUserStandardService::class)->sync();
        $operator = $users[$email];
        $imports = [];
        foreach ([['IMSS', $direction, 'Primera pauta.xlsx'], ['IMSS', $direction, 'Segunda pauta.xlsx'], ['IPAB', $direction, 'Otra dependencia.xlsx'],
            ['IPAB', $direction === 'DIR_A' ? 'DIR_B' : 'DIR_A', 'Otra dirección privada.xlsx']] as [$code, $unitCode, $name]) {
            $agency = ContractingAgency::where('code', $code)->firstOrFail();
            $unit = $agency->units()->where('code', $unitCode)->firstOrFail();
            $template = EvidenceTemplate::where('contracting_agency_id', $agency->id)->firstOrFail();
            $import = CalendarImport::factory()->create(['contracting_agency_id' => $agency->id, 'original_filename' => $name, 'status' => 'CONFIRMED']);
            $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
            $load = ScheduledLoad::factory()->create(['calendar_import_id' => $import->id, 'calendar_import_row_id' => $row->id,
                'contracting_agency_id' => $agency->id, 'template_id' => $template->id, 'status' => 'PROGRAMADA',
                'effective_open_at' => '2026-07-07 08:00:00', 'effective_close_at' => '2026-07-07 23:59:59']);
            $requirement = $template->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
            ScheduledLoadDeliverable::create(['scheduled_load_id' => $load->id, 'template_requirement_id' => $requirement->id,
                'organizational_unit_id' => $unit->id, 'responsible_user_id' => $unitCode === $direction ? $operator->id : null, 'status' => 'PENDIENTE']);
            $imports[] = $import;
        }
        $service = app(LoadBoardService::class);
        $selected = $service->forUser($operator, ['agency_id' => $imports[0]->contracting_agency_id, 'pauta_id' => $imports[0]->id]);
        $this->assertCount(7, $selected['agencies']);
        $this->assertSame(1, $selected['summary']['total']);
        $unitIds = implode(',', $selected['units']->flatMap(fn ($unit) => $unit->filter_unit_ids)->all());
        $all = $service->forUser($operator, ['unit_id' => $unitIds]);
        $this->assertCount(3, $all['pautas']);
        $this->assertSame(3, $all['summary']['total']);
        $other = $service->forUser($operator, ['agency_id' => $imports[2]->contracting_agency_id, 'unit_id' => $unitIds, 'from' => '2026-10', 'to' => '2026-10']);
        $this->assertSame(0, $other['summary']['total']);
        $this->assertSame([$imports[2]->id], $other['pautas']->pluck('id')->all());
        $other = $service->forUser($operator, ['agency_id' => $imports[2]->contracting_agency_id, 'unit_id' => $unitIds, 'pauta_id' => $imports[2]->id]);
        $this->assertSame(1, $other['summary']['total']);
        $this->assertSame(['2026-07'], $other['periods']->all());
        $this->actingAs($operator)->get(route('loads.board', ['pauta_id' => $imports[0]->id]))->assertOk()
            ->assertSee('Primera pauta.xlsx')->assertSee('Segunda pauta.xlsx')->assertSee('Otra dependencia.xlsx')
            ->assertDontSee('Otra dirección privada.xlsx');
    }
}
