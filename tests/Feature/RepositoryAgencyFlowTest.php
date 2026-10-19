<?php

namespace Tests\Feature;

use App\Models\{CalendarImport, CalendarImportRow, ContractingAgency, Evidence, EvidenceFile, RepositoryFolder, ReviewAssignment, Role, ScheduledLoad, ScheduledLoadDeliverable, User};
use App\Services\{EvidenceService, QaUserStandardService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Storage};
use Tests\TestCase;

class RepositoryAgencyFlowTest extends TestCase
{
    use RefreshDatabase;

    private $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(12, 0));
        Storage::fake('local');
        config(['siget.repository_disk'=>'local', 'siget.antivirus_enabled'=>false]);
        Bus::fake();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $this->users = app(QaUserStandardService::class)->sync();
    }

    private function user(string $role): User
    {
        return collect($this->users)->first(fn ($u) => $u->role->code === $role);
    }

    private function load(string $code, string $name='Pauta de prueba.xlsx', ?CalendarImport $import=null, ?string $date=null): ScheduledLoad
    {
        $agency=ContractingAgency::where('code',$code)->firstOrFail();
        $template=$agency->templates()->firstOrFail();
        $import ??= CalendarImport::factory()->create(['contracting_agency_id'=>$agency->id,'uploaded_by'=>$this->user('ENLACE_INSTITUCIONAL')->id,'original_filename'=>$name]);
        $row=CalendarImportRow::factory()->create(['calendar_import_id'=>$import->id]);
        $opens=$date ? \Carbon\Carbon::parse($date)->setTime(11,0) : now()->subHour();
        $closes=$opens->copy()->addHours(2);
        $load=ScheduledLoad::create(['contracting_agency_id'=>$agency->id,'template_id'=>$template->id,'calendar_import_id'=>$import->id,
            'calendar_import_row_id'=>$row->id,'title'=>'Carga '.$name.' '.$opens->format('Y-m-d'),'period_label'=>'Periodo contractual',
            'original_open_at'=>$opens,'original_close_at'=>$closes,'effective_open_at'=>$opens,'effective_close_at'=>$closes,
            'status'=>'ABIERTA','traffic_light'=>'BLUE','is_blocked'=>false]);
        foreach ($template->requirements()->with('responsibleUnit')->get() as $requirement) {
            $unit=$agency->units()->findOrFail($requirement->responsible_unit_id);
            $operator=$this->user($unit->code==='DIR_A' ? 'OPERADOR_TRANSMISION' : 'OPERADOR_PROGRAMACION_CONTINUIDAD');
            ScheduledLoadDeliverable::create(['scheduled_load_id'=>$load->id,'template_requirement_id'=>$requirement->id,
                'organizational_unit_id'=>$unit->id,'responsible_user_id'=>$operator->id,'status'=>'PENDIENTE','due_at'=>$closes]);
        }
        return $load;
    }

    private function upload(ScheduledLoad $load, string $direction, string $filename): EvidenceFile
    {
        $deliverable=$load->deliverables()->whereHas('organizationalUnit',fn($q)=>$q->where('code',$direction))->firstOrFail();
        $operator=User::findOrFail($deliverable->responsible_user_id);
        $this->actingAs($operator);
        $evidence=app(EvidenceService::class)->upload($deliverable,UploadedFile::fake()->create($filename,10,'application/pdf'),$operator);
        return $evidence->files()->latest('id')->firstOrFail();
    }

    public function test_seven_folders_exist_even_when_only_three_agencies_have_loads(): void
    {
        $imss=$this->load('IMSS'); $this->load('IPAB'); $this->load('BIENESTAR');
        ReviewAssignment::create(['scheduled_load_id'=>$imss->id,'fiscalizador_id'=>$this->user('FISCALIZADOR')->id,'assigned_by'=>$this->user('ADMINISTRADOR')->id,'active'=>true]);
        foreach ($this->users as $user) {
            $expected=$user->role->code==='FISCALIZADOR' ? 1 : 7;
            $response=$this->actingAs($user)->get(route('repository.index'))->assertOk()
                ->assertViewHas('agencies',fn($rows)=>$rows->count()===$expected)
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===$expected && $rows->pluck('agency.id')->unique()->count()===$expected);
            $this->assertSame($expected,substr_count($response->getContent(),'data-repository-folder-id='));
            if ($expected===7) $response->assertViewHas('dependencySummaries',fn($rows)=>$rows->where('load_count',0)->count()===4);
        }
    }

    public function test_selecting_empty_dependency_shows_its_folder_and_no_other_agency_files_without_creating_data(): void
    {
        $load=$this->load('IMSS'); $this->upload($load,'DIR_B','Solo_IMSS.pdf');
        $empty=ContractingAgency::where('code','SEGOB')->firstOrFail();
        $before=[ScheduledLoad::count(),ScheduledLoadDeliverable::count(),Evidence::count(),EvidenceFile::count(),RepositoryFolder::count()];
        foreach (['OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD','ENLACE_INSTITUCIONAL','DIRECTOR_GENERAL','ADMINISTRADOR'] as $role) {
            $this->actingAs($this->user($role))->get(route('repository.index',['agency_id'=>$empty->id]))->assertOk()
                ->assertViewHas('agencies',fn($rows)=>$rows->count()===7)
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->agency->id===$empty->id && $rows->first()->load_count===0)
                ->assertViewHas('loads',fn($rows)=>$rows->count()===0)
                ->assertViewHas('recentFiles',fn($rows)=>$rows->isEmpty())
                ->assertViewHas('usedBytes',fn($bytes)=>(int)$bytes===0)
                ->assertSee('Su carpeta está disponible')->assertDontSee('Solo_IMSS.pdf');
        }
        $this->assertSame($before,[ScheduledLoad::count(),ScheduledLoadDeliverable::count(),Evidence::count(),EvidenceFile::count(),RepositoryFolder::count()]);
    }

    public function test_new_agency_appears_in_both_operator_repositories_before_the_first_pauta(): void
    {
        $this->actingAs($this->user('ADMINISTRADOR'))->post(route('admin.agencies.store'),['code'=>'NUEVA_REPO','name'=>'Dependencia nueva repositorio','legal_name'=>'Dependencia nueva repositorio'])->assertRedirect()->assertSessionHasNoErrors();
        $new=ContractingAgency::where('code','NUEVA_REPO')->firstOrFail();
        foreach (['OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD'] as $role) {
            $this->actingAs($this->user($role))->get(route('repository.index'))->assertOk()
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===8 && $rows->firstWhere('agency.id',$new->id)->load_count===0);
        }
        $this->assertSame(0,RepositoryFolder::count());
    }

    public function test_recent_files_counts_and_directions_follow_the_selected_agency_for_both_operators(): void
    {
        $imss=$this->load('IMSS'); $ipab=$this->load('IPAB');
        $files=[];
        foreach (['DIR_A','DIR_B'] as $direction) {
            $files[$direction]=$this->upload($imss,$direction,'IMSS_'.$direction.'.pdf');
            $this->upload($ipab,$direction,'IPAB_'.$direction.'.pdf');
        }
        foreach (['OPERADOR_TRANSMISION'=>'DIR_A','OPERADOR_PROGRAMACION_CONTINUIDAD'=>'DIR_B'] as $role=>$direction) {
            $expected=$files[$direction];
            $response=$this->actingAs($this->user($role))->get(route('repository.index',['agency_id'=>$imss->contracting_agency_id]))->assertOk()
                ->assertViewHas('recentFiles',fn($rows)=>$rows->pluck('id')->all()===[$expected->id])
                ->assertViewHas('usedBytes',fn($bytes)=>(int)$bytes===(int)$expected->size_bytes)
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->file_count===1 && $rows->first()->directions->count()===1)
                ->assertSee('IMSS_'.$direction.'.pdf')->assertDontSee('IPAB_'.$direction.'.pdf')
                ->assertDontSee('data-repository-report',false);
            $other=$direction==='DIR_A' ? 'DIR_B' : 'DIR_A';
            $response->assertDontSee('IMSS_'.$other.'.pdf')->assertDontSee($imss->deliverables()->whereHas('organizationalUnit',fn($q)=>$q->where('code',$other))->first()->organizationalUnit->name);
        }
    }

    public function test_shared_load_hides_another_responsible_in_the_same_direction(): void
    {
        $load=$this->load('IMSS');
        $ownFile=$this->upload($load,'DIR_B','Propio.pdf');
        $operator=$this->user('OPERADOR_PROGRAMACION_CONTINUIDAD');
        $unit=$load->deliverables()->where('responsible_user_id',$operator->id)->firstOrFail()->organizationalUnit;
        $other=User::factory()->create(['role_id'=>$operator->role_id,'organizational_unit_id'=>$unit->id,
            'contracting_agency_id'=>$load->contracting_agency_id,'status'=>'ACTIVE']);
        $requirement=\App\Models\TemplateRequirement::create(['template_id'=>$load->template_id,'code'=>'OTRO_RESPONSABLE',
            'name'=>'Entregable reservado de otro responsable','responsible_unit_id'=>$unit->id,'required'=>true,
            'min_files'=>1,'max_files'=>3,'allowed_extensions'=>['pdf'],'active'=>true]);
        $deliverable=ScheduledLoadDeliverable::create(['scheduled_load_id'=>$load->id,'template_requirement_id'=>$requirement->id,
            'organizational_unit_id'=>$unit->id,'responsible_user_id'=>$other->id,'status'=>'PENDIENTE','due_at'=>now()->addHour()]);
        $this->actingAs($other);
        app(EvidenceService::class)->upload($deliverable,UploadedFile::fake()->create('De_otro_responsable.pdf',10,'application/pdf'),$other);
        $this->actingAs($operator)->get(route('repository.index',['agency_id'=>$load->contracting_agency_id]))->assertOk()
            ->assertViewHas('recentFiles',fn($rows)=>$rows->pluck('id')->all()===[$ownFile->id])
            ->assertViewHas('dependencySummaries',fn($rows)=>$rows->first()->file_count===1 && $rows->first()->monthlyPautas->first()->deliverables->count()===1)
            ->assertSee('Propio.pdf')->assertDontSee('De_otro_responsable.pdf')->assertDontSee('Entregable reservado de otro responsable');
    }

    public function test_shared_template_does_not_merge_different_imported_pautas_and_all_dates_have_links(): void
    {
        $one=$this->load('IMSS','Pauta primera.xlsx',date:'2026-07-07');
        $two=$this->load('IMSS','Pauta segunda.xlsx',date:'2026-07-15');
        $three=$this->load('IMSS',import:$one->calendarImport,date:'2026-07-22');
        foreach (['OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD','ENLACE_INSTITUCIONAL'] as $role) {
            $r=$this->actingAs($this->user($role))->get(route('repository.index',['agency_id'=>$one->contracting_agency_id]))->assertOk()
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->first()->monthlyPautas->count()===2 && $rows->first()->monthlyPautas->firstWhere('pauta_id',$one->calendar_import_id)->load_count===2)
                ->assertSee('Pauta primera.xlsx')->assertSee('Pauta segunda.xlsx');
            foreach ([$one,$two,$three] as $load) $r->assertSee('data-repository-load-id="'.$load->id.'"',false)->assertSee(route('loads.show',$load));
            if ($role==='ENLACE_INSTITUCIONAL') {
                preg_match_all('/href="([^"]+)"[^>]*data-repository-report/',$r->getContent(),$matches);
                $this->assertCount(2,$matches[1]);
                foreach ($matches[1] as $url) {
                    parse_str(parse_url(html_entity_decode($url),PHP_URL_QUERY),$filters);
                    $this->assertSame('2026-07',$filters['from']); $this->assertSame('2026-07',$filters['to']);
                    $this->assertContains((int)$filters['pauta_id'],[$one->calendar_import_id,$two->calendar_import_id]);
                    $this->get(html_entity_decode($url))->assertOk();
                }
            }
        }
    }

    public function test_search_by_pauta_month_and_dependency_filters_cards_details_and_files_together(): void
    {
        $one=$this->load('IMSS','Buscar primera.xlsx',date:'2026-07-07');
        $two=$this->load('IMSS','Buscar segunda.xlsx',date:'2026-08-15');
        $operator=$this->user('OPERADOR_PROGRAMACION_CONTINUIDAD');
        foreach (['primera.xlsx'=>[$one->id], '2026-07'=>[$one->id], '2026-08'=>[$two->id]] as $term=>$ids) {
            $this->actingAs($operator)->get(route('repository.index',['q'=>$term]))->assertOk()
                ->assertViewHas('loads',fn($rows)=>$rows->pluck('id')->all()===$ids)
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->load_count===1);
        }
        $this->get(route('repository.index',['q'=>'SEGOB']))->assertOk()
            ->assertViewHas('loads',fn($rows)=>$rows->isEmpty())
            ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->agency->code==='SEGOB');
        $this->get(route('repository.index',['agency_id'=>$one->contracting_agency_id,'q'=>'No existe']))->assertOk()
            ->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->load_count===0)->assertSee('Sin cargas que coincidan');
    }

    public function test_status_date_and_pauta_filters_apply_to_recent_files_and_summary_counts(): void
    {
        $one=$this->load('IMSS','Primera.xlsx'); $fileOne=$this->upload($one,'DIR_B','Primera.pdf');
        $two=$this->load('IMSS','Segunda.xlsx'); $fileTwo=$this->upload($two,'DIR_B','Segunda.pdf');
        $one->update(['status'=>'OBSERVADA','effective_open_at'=>now()->subDay()]);
        $operator=$this->user('OPERADOR_PROGRAMACION_CONTINUIDAD');
        foreach ([['status'=>'OBSERVADA'],['to'=>now()->subDay()->toDateString()],['pauta_id'=>$one->calendar_import_id]] as $filters) {
            $this->actingAs($operator)->get(route('repository.index',$filters))->assertOk()
                ->assertViewHas('loads',fn($rows)=>$rows->pluck('id')->all()===[$one->id])
                ->assertViewHas('recentFiles',fn($rows)=>$rows->pluck('id')->all()===[$fileOne->id])
                ->assertViewHas('dependencySummaries',fn($rows)=>$rows->sum('file_count')===1 && $rows->sum('load_count')===1);
        }
    }

    public function test_showing_empty_folders_never_grants_access_to_an_unauthorized_agency_or_assignment(): void
    {
        $imss=$this->load('IMSS'); $ipab=$this->load('IPAB');
        $unit=ContractingAgency::where('code','IMSS')->first()->units()->where('code','DIR_B')->firstOrFail();
        $restricted=User::factory()->create(['role_id'=>Role::where('code','OPERADOR_PROGRAMACION_CONTINUIDAD')->value('id'),
            'contracting_agency_id'=>$imss->contracting_agency_id,'organizational_unit_id'=>$unit->id,'status'=>'ACTIVE']);
        $this->actingAs($restricted)->get(route('repository.index'))->assertOk()->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1 && $rows->first()->agency->id===$imss->contracting_agency_id);
        $this->get(route('repository.index',['agency_id'=>$ipab->contracting_agency_id]))->assertForbidden();
        $fiscal=$this->user('FISCALIZADOR');
        $this->actingAs($fiscal)->get(route('repository.index'))->assertOk()->assertViewHas('dependencySummaries',fn($rows)=>$rows->isEmpty());
        $assignment=ReviewAssignment::create(['scheduled_load_id'=>$imss->id,'fiscalizador_id'=>$fiscal->id,'assigned_by'=>$this->user('ADMINISTRADOR')->id,'active'=>true]);
        $this->get(route('repository.index'))->assertOk()->assertViewHas('dependencySummaries',fn($rows)=>$rows->count()===1);
        $this->get(route('repository.index',['agency_id'=>$ipab->contracting_agency_id]))->assertForbidden();
        $assignment->update(['active'=>false]);
        $this->get(route('repository.index',['agency_id'=>$imss->contracting_agency_id]))->assertForbidden();
    }
}
