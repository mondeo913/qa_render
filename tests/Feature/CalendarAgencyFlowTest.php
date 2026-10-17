<?php
namespace Tests\Feature;

use App\Models\{CalendarImport, ContractingAgency, ReviewAssignment, ScheduledLoad, User};
use App\Services\{AccessScopeService, CalendarImportService, QaUserStandardService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Storage};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CalendarAgencyFlowTest extends TestCase
{
    use RefreshDatabase;

    public static function agencies(): array
    {
        return array_map(fn ($code) => [$code], ['IMSS', 'IPAB', 'SEGOB', 'SENER', 'BIENESTAR', 'BANOBRAS', 'SEMAR', 'NUEVA']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('agencies')]
    public function test_new_agency_is_selectable_and_import_dates_reach_both_directions(string $code): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        Storage::fake('local');
        config(['siget.repository_disk' => 'local']);
        Bus::fake();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $users = app(QaUserStandardService::class)->sync();
        $enlace = $users['enlace@siget.local'];
        if ($code === 'NUEVA') {
            $this->actingAs($users['admin@siget.local'])->post(route('admin.agencies.store'), [
                'code' => $code, 'name' => 'Dependencia nueva', 'legal_name' => 'Dependencia nueva',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $agency = ContractingAgency::where('code', $code)->firstOrFail();
        $this->assertSame(0, ScheduledLoad::where('contracting_agency_id', $agency->id)->count());
        $this->actingAs($enlace)->get(route('calendar.index'))->assertOk()
            ->assertViewHas('importAgencies', fn ($agencies) => $agencies->contains('id', $agency->id))
            ->assertViewHas('agencies', fn ($agencies) => $agencies->count() === ($code === 'NUEVA' ? 8 : 7));
        foreach (['operador.monitoreo@siget.local', 'operador.produccion@siget.local', 'director@siget.local', 'director.produccion@siget.local'] as $email) {
            $this->actingAs($users[$email])->get(route('calendar.index'))->assertOk()
                ->assertViewHas('agencies', fn ($agencies) => $agencies->contains('id', $agency->id));
            $this->get('/dashboard')->assertOk()->assertViewHas('agencies', fn ($agencies) => $agencies->contains('id', $agency->id));
            $this->get(route('repository.index'))->assertOk()
                ->assertViewHas('dependencySummaries', fn ($rows) => $rows->count() === ($code === 'NUEVA' ? 8 : 7)
                    && $rows->firstWhere('agency.id', $agency->id)->load_count === 0);
        }
        $this->actingAs($enlace);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('CC8', 'Nombre del Archivo para bitacora');
        foreach ([9 => '26.07.07', 10 => '26.07.15'] as $row => $date) {
            $sheet->setCellValue('A'.$row, 'Programas de Bienestar');
            $sheet->setCellValue('CC'.$row, '01_XHSPRAG_'.$date.'.TESTIGO.C14.mp4');
        }
        $path = tempnam(sys_get_temp_dir(), 'pauta-');
        try {
            (new Xlsx($book))->save($path);
            $file = new UploadedFile($path, 'pauta.xlsx', null, null, true);
            $this->post(route('calendar.import.store'), ['calendar_file' => $file,
                'contracting_agency_id' => $agency->id, 'schedule_year' => 2026])->assertRedirect();
            $import = CalendarImport::latest('id')->firstOrFail();
            $this->assertSame('VALIDATED', $import->status);
            $this->get(route('calendar.import.preview', $import))->assertOk()->assertSee($agency->name);
            $this->post(route('calendar.import.confirm', $import))->assertRedirect(route('calendar.index', [
                'contracting_agency_id' => $agency->id, 'date' => '2026-07-07']));
            $loads = ScheduledLoad::where('calendar_import_id', $import->id)->with('deliverables')->get();
            $this->assertCount(2, $loads);
            $this->assertSame(2, \App\Models\RepositoryFolder::whereIn('scheduled_load_id', $loads->pluck('id'))->where('folder_type', 'SCHEDULED_LOAD')->count());
            $this->assertSame(4, \App\Models\RepositoryFolder::whereIn('scheduled_load_id', $loads->pluck('id'))->where('folder_type', 'OPERATIONAL_UNIT')->count());
            foreach ($loads as $load) {
                $this->assertSame($agency->id, $load->contracting_agency_id);
                $this->assertSame('BITACORA_FILENAME', $load->metadata['format']);
                $this->assertCount(2, $load->deliverables);
                foreach (['TRANSMISION' => 'operador.monitoreo@siget.local', 'PROGRAMACION_CONTINUIDAD' => 'operador.produccion@siget.local'] as $role => $email) {
                    $operator = $users[$email];
                    $deliverable = $load->deliverables->firstWhere('responsible_user_id', $operator->id);
                    $this->assertNotNull($deliverable);
                    $this->assertTrue(app(AccessScopeService::class)->canAccessDeliverable($operator, $deliverable));
                    $other = $load->deliverables->firstWhere('id', '!=', $deliverable->id);
                    $this->assertFalse(app(AccessScopeService::class)->canAccessDeliverable($operator, $other));
                    $director = User::whereHas('role', fn ($q) => $q->where('code', 'DIRECTOR_'.$role))->firstOrFail();
                    $this->assertTrue(app(AccessScopeService::class)->canAccessDeliverable($director, $deliverable));
                    $this->assertFalse(app(AccessScopeService::class)->canAccessDeliverable($director, $other));
                }
            }
            foreach ($loads as $load) {
                ReviewAssignment::create(['scheduled_load_id' => $load->id, 'fiscalizador_id' => $users['fiscalizador@siget.local']->id,
                    'assigned_by' => $users['admin@siget.local']->id, 'active' => true]);
            }
            foreach (array_keys($users) as $email) {
                $this->actingAs($users[$email])->getJson(route('calendar.events', ['start' => '2026-07-01', 'end' => '2026-08-01', 'contracting_agency_id' => $agency->id]))
                    ->assertOk()->assertJsonCount(2)->assertJsonPath('0.extendedProps.contracting_agency_id', $agency->id)
                    ->assertJsonPath('0.extendedProps.agency', $agency->name)
                    ->assertJsonPath('0.backgroundColor', '#b91c1c')
                    ->assertJsonPath('0.extendedProps.appearance.key', 'overdue');
                $this->getJson(route('calendar.programmed-dates', ['start' => '2026-07-01', 'end' => '2026-08-01', 'contracting_agency_id' => $agency->id]))
                    ->assertOk()->assertExactJson(['dates' => ['2026-07-07', '2026-07-15']]);
                $this->get(route('repository.index', ['agency_id' => $agency->id]))->assertOk()
                    ->assertViewHas('loads', fn ($visible) => $visible->count() === 2)
                    ->assertViewHas('dependencySummaries', fn ($rows) => $rows->count() === 1
                        && $rows->first()->monthlyPautas->count() === 1 && $rows->first()->load_count === 2);
                if (in_array($email, ['director.general@siget.local', 'fiscalizador@siget.local'], true)) {
                    $this->get(route('calendar.index'))->assertOk()->assertDontSee('Validar pauta');
                    $this->post(route('calendar.import.confirm', $import))->assertForbidden();
                }
                if (str_starts_with($email, 'operador.')) {
                    $this->get(route('loads.mine', ['agency_id' => $agency->id]))->assertOk()
                        ->assertViewHas('loads', fn ($visible) => $visible->count() === 2);
                }

            }
        } finally { @unlink($path); }
    }
}
