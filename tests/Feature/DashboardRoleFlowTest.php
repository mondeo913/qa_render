<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ContractingAgency, Evidence, EvidenceReview, ReviewAssignment, Role, ScheduledLoad, ScheduledLoadDeliverable, User};
use App\Services\{CalendarPresentationService, DashboardAnalyticsService, DashboardSnapshotService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoleFlowTest extends TestCase
{
    use RefreshDatabase;

    private ContractingAgency $agency;
    private array $users;
    private array $units;
    private ScheduledLoad $load;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $this->agency = ContractingAgency::where('code', 'IMSS')->firstOrFail();
        foreach (['DIR_A', 'DIR_B'] as $code) $this->units[$code] = $this->agency->units()->where('code', $code)->firstOrFail();
        foreach (['ADMINISTRADOR', 'DIRECTOR_GENERAL', 'ENLACE_INSTITUCIONAL', 'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD', 'FISCALIZADOR'] as $role) {
            $unit = str_contains($role, 'PROGRAMACION') ? 'DIR_B' : (str_contains($role, 'TRANSMISION') ? 'DIR_A' : null);
            $this->users[$role] = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id,
                'contracting_agency_id' => $this->agency->id, 'organizational_unit_id' => $unit ? $this->units[$unit]->id : null, 'status' => 'ACTIVE']);
        }
        $this->load = ScheduledLoad::factory()->create(['contracting_agency_id' => $this->agency->id,
            'template_id' => $this->agency->templates()->firstOrFail()->id, 'effective_open_at' => now()->subDays(2),
            'effective_close_at' => now()->subDay(), 'status' => 'EN_CAPTURA']);
        ReviewAssignment::create(['scheduled_load_id' => $this->load->id, 'fiscalizador_id' => $this->users['FISCALIZADOR']->id,
            'assigned_by' => $this->users['ADMINISTRADOR']->id, 'active' => true]);
    }

    private function deliverable(string $direction, string $status, bool $evidence = true, ?User $responsible = null): ScheduledLoadDeliverable
    {
        $unit = $this->units[$direction];
        $requirement = $this->agency->templates()->firstOrFail()->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
        // Each load has one requirement per direction; multiple states use independent daily loads.
        $load = $this->load;
        if ($load->deliverables()->where('organizational_unit_id', $unit->id)->exists()) {
            $load = $load->replicate(); $load->save();
            ReviewAssignment::create(['scheduled_load_id' => $load->id, 'fiscalizador_id' => $this->users['FISCALIZADOR']->id,
                'assigned_by' => $this->users['ADMINISTRADOR']->id, 'active' => true]);
        }
        $responsible ??= $this->users[$direction === 'DIR_A' ? 'OPERADOR_TRANSMISION' : 'OPERADOR_PROGRAMACION_CONTINUIDAD'];
        $submitted = !in_array($status, ['PENDIENTE', 'EN_CAPTURA'], true);
        $d = ScheduledLoadDeliverable::create(['scheduled_load_id' => $load->id, 'template_requirement_id' => $requirement->id,
            'organizational_unit_id' => $unit->id, 'responsible_user_id' => $responsible->id, 'status' => $status,
            'due_at' => now()->subDay(), 'submitted_at' => $submitted ? now()->subDays(2) : null]);
        if ($evidence) Evidence::create(['scheduled_load_id' => $load->id, 'deliverable_id' => $d->id, 'submitted_by' => $responsible->id,
            'title' => 'Evidencia '.$direction, 'status' => $status, 'current_version' => 1, 'submitted_at' => $d->submitted_at]);
        return $d;
    }

    private function data(string $role, array $filters = []): array
    {
        return app(DashboardAnalyticsService::class)->forUser($this->users[$role], $filters);
    }

    public function test_shared_load_never_leaks_other_direction_into_funnels_or_recent_details(): void
    {
        $a = $this->deliverable('DIR_A', 'CERRADO'); $b = $this->deliverable('DIR_B', 'CERRADO');
        $this->load->update(['status' => 'VALIDADO_Y_CERRADO']);
        foreach (['DIRECTOR_TRANSMISION', 'OPERADOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD', 'OPERADOR_PROGRAMACION_CONTINUIDAD'] as $role) {
            $data = $this->data($role);
            $this->assertSame(1, $data['evidence_summary']['expected']);
            $this->assertSame(1, (int) $data['evidence_funnel']['CERRADO']);
            $this->assertCount(1, $data['direction_performance']);
            $this->assertCount(1, $data['recent']->first()->deliverables);
            $other = str_contains($role, 'PROGRAMACION') ? $a : $b;
            $this->assertSame(0, $this->data($role, ['organizational_unit_id' => (string) $other->organizational_unit_id])['workspace']['summary']['expected']);
            $this->assertSame(0, $this->data($role, ['responsible_id' => $other->responsible_user_id])['workspace']['summary']['expected']);
        }
    }

    public function test_drafts_remain_pending_and_expired_deadlines_match_calendar_without_mutating_workflow(): void
    {
        $this->deliverable('DIR_A', 'EN_CAPTURA'); $this->deliverable('DIR_B', 'PENDIENTE', false);
        $before = [$this->load->fresh()->getAttributes(), ScheduledLoadDeliverable::all()->toArray(), Evidence::all()->toArray(), AuditLog::count()];
        foreach ($this->users as $role => $user) {
            $data = $this->data($role);
            $expected = str_starts_with($role, 'OPERADOR_') || str_starts_with($role, 'DIRECTOR_') && $role !== 'DIRECTOR_GENERAL' ? 1 : 2;
            $this->assertSame($expected, $data['workspace']['summary']['pending'], $role);
            $this->assertSame($expected, array_sum(array_column($data['pending_by_due'], 'total')), $role);
            $this->assertSame($expected, $data['workspace']['summary']['overdue'], $role);
            $this->assertSame(1, $data['kpis']['overdue']);
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Plazo vencido');
        }
        $this->assertSame('overdue', app(CalendarPresentationService::class)->forLoad($this->load->fresh(), now())['key']);
        $this->assertSame($before, [$this->load->fresh()->getAttributes(), ScheduledLoadDeliverable::all()->toArray(), Evidence::all()->toArray(), AuditLog::count()]);
    }

    public function test_all_nine_deliverable_states_reconcile_into_four_exclusive_groups(): void
    {
        foreach (\App\Enums\DeliverableStatus::cases() as $status) $this->deliverable('DIR_A', $status->value);
        $data = $this->data('DIRECTOR_TRANSMISION'); $s = $data['workspace']['summary'];
        $this->assertSame(['expected'=>9,'pending'=>3,'review'=>2,'observed'=>2,'validated'=>2,'overdue'=>5], $s);
        $this->assertSame(9, $s['pending']+$s['review']+$s['observed']+$s['validated']);
        $this->assertSame(3, array_sum(array_column($data['pending_by_due'], 'total')));
        $this->assertSame(7, $data['evidence_summary']['received']);
        $this->assertSame(2, $data['evidence_summary']['validated']);
        $response = $this->actingAs($this->users['DIRECTOR_TRANSMISION'])->get(route('dashboard'));
        $response->assertOk()->assertSee('data-status-total="9"', false)->assertSee('data-siget-chart="dashboardStatusChart"', false);
    }

    public function test_combined_filters_select_deliverables_not_every_requirement_on_the_shared_load(): void
    {
        $a = $this->deliverable('DIR_A', 'ENVIADO'); $this->deliverable('DIR_B', 'VALIDADO');
        $filters = ['agency_id'=>$this->agency->id,'pauta_id'=>$this->load->calendar_import_id,'from'=>'2026-10','to'=>'2026-10',
            'organizational_unit_id'=>(string)$a->organizational_unit_id,'responsible_id'=>$a->responsible_user_id,'deliverable_group'=>'review'];
        foreach (['ADMINISTRADOR','DIRECTOR_GENERAL','ENLACE_INSTITUCIONAL','DIRECTOR_TRANSMISION','OPERADOR_TRANSMISION'] as $role) {
            $data = $this->data($role, $filters);
            $this->assertSame(1, $data['workspace']['summary']['expected']);
            $this->assertSame(1, $data['workspace']['summary']['review']);
            $this->assertSame(1, $data['evidence_summary']['received']);
            $this->assertSame(0, $data['evidence_summary']['validated']);
            $this->assertCount(1, $data['direction_performance']);
        }
        $this->assertSame(0, $this->data('DIRECTOR_GENERAL', $filters)['workspace']['summary']['validated']);
        $filters['from']=$filters['to']='2026-11';
        $this->assertSame(0, $this->data('DIRECTOR_GENERAL', $filters)['workspace']['summary']['expected']);
    }

    public function test_responsibles_with_the_same_name_remain_distinct(): void
    {
        $one = $this->users['OPERADOR_TRANSMISION']; $one->update(['name'=>'Mismo nombre']);
        $other = User::factory()->create(['role_id'=>$one->role_id,'organizational_unit_id'=>$one->organizational_unit_id,
            'contracting_agency_id'=>$this->agency->id,'name'=>'Mismo nombre']);
        $this->deliverable('DIR_A','PENDIENTE',false,$one); $this->deliverable('DIR_A','PENDIENTE',false,$other);
        $data = $this->data('DIRECTOR_TRANSMISION');
        $this->assertCount(2,$data['workspace']['by_responsible']);
        $this->assertCount(2,$data['evidence_by_responsible']);
        $this->assertEqualsCanonicalizing([$one->id,$other->id],array_column($data['workspace']['by_responsible'],'id'));
        $this->assertSame(1,$this->data('OPERADOR_TRANSMISION')['workspace']['summary']['expected']);
    }

    public function test_fiscalization_counts_only_active_assignments_and_own_current_version_decisions(): void
    {
        $a=$this->deliverable('DIR_A','CERRADO'); $b=$this->deliverable('DIR_B','ENVIADO');
        $e=$a->evidences()->firstOrFail(); $fiscal=$this->users['FISCALIZADOR'];
        EvidenceReview::create(['evidence_id'=>$e->id,'reviewer_id'=>$fiscal->id,'decision'=>'APROBADO','review_type'=>'FISCALIZATION','evidence_version'=>1]);
        EvidenceReview::create(['evidence_id'=>$b->evidences()->first()->id,'reviewer_id'=>$this->users['ADMINISTRADOR']->id,'decision'=>'APROBADO','review_type'=>'FISCALIZATION','evidence_version'=>1]);
        $this->assertSame(['pending'=>1,'approved'=>1,'observed'=>0,'not_submitted'=>0],$this->data('FISCALIZADOR')['workspace']['fiscal']);
        $e->update(['current_version'=>2]);
        $this->assertSame(2,$this->data('FISCALIZADOR')['workspace']['fiscal']['pending']);
        $this->assertSame('CERRADO',$a->fresh()->status->value);
        ReviewAssignment::query()->update(['active'=>false]);
        $this->assertSame(0,$this->data('FISCALIZADOR')['workspace']['summary']['expected']);
    }

    public function test_each_profile_renders_only_allowed_actions_and_reconciled_chart_data(): void
    {
        $this->deliverable('DIR_A','ENVIADO'); $this->deliverable('DIR_B','EN_REVISION');
        $profiles=['ADMINISTRADOR'=>'admin','DIRECTOR_GENERAL'=>'executive','ENLACE_INSTITUCIONAL'=>'institutional','DIRECTOR_TRANSMISION'=>'direction',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD'=>'direction','OPERADOR_TRANSMISION'=>'operator','OPERADOR_PROGRAMACION_CONTINUIDAD'=>'operator','FISCALIZADOR'=>'fiscal'];
        foreach ($profiles as $role=>$profile) {
            $user=$this->users[$role];
            $r=$this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-dashboard-profile="'.$profile.'"',false);
            if ($user->hasPermission('reports.export')) $r->assertSee('Exportar resumen'); else $r->assertDontSee('Exportar resumen');
            if ($profile!=='admin') $r->assertDontSee('Alertas de administración');
            if ($role==='DIRECTOR_TRANSMISION' || $profile==='institutional' || $profile==='fiscal') $r->assertSee($profile==='fiscal' ? 'Fiscalizar' : 'Revisar evidencia');
            if ($profile==='operator' || $role==='DIRECTOR_PROGRAMACION_CONTINUIDAD') $r->assertDontSee('Revisar evidencia');
            preg_match_all('/<script type="application\/json" data-siget-chart="[^"]+">(.*?)<\/script>/s',$r->getContent(),$matches);
            $this->assertNotEmpty($matches[1],$role);
            foreach ($matches[1] as $json) {
                $chart=json_decode($json,true,512,JSON_THROW_ON_ERROR);
                foreach ($chart['datasets'] as $dataset) $this->assertCount(count($chart['labels']),$dataset['data']);
                if ($chart['type']==='doughnut') $this->assertSame($this->data($role)['workspace']['summary']['expected'],array_sum($chart['datasets'][0]['data']));
            }
        }
    }

    public function test_report_metrics_and_exports_keep_dashboard_filters_and_exclude_unsent_drafts(): void
    {
        $a=$this->deliverable('DIR_A','EN_CAPTURA'); $this->deliverable('DIR_B','ENVIADO');
        foreach ($this->users as $role=>$user) {
            if (!$user->hasPermission('reports.view')) continue;
            $expected=$this->data($role)['evidence_summary'];
            $r=$this->actingAs($user)->get(route('reports.index'))->assertOk();
            $r->assertViewHas('evidenceSummary',fn($s)=>$s['expected']===$expected['expected'] && $s['received']===$expected['received'] && $s['pending']===$expected['pending']);
        }
        $filters=['agency_id'=>$this->agency->id,'responsible_id'=>$a->responsible_user_id,'deliverable_group'=>'pending','from'=>'2026-10','to'=>'2026-10'];
        $this->actingAs($this->users['ADMINISTRADOR'])->get(route('reports.index',$filters))->assertOk()
            ->assertViewHas('evidenceSummary',fn($s)=>$s['expected']===1 && $s['received']===0 && $s['pending']===1)
            ->assertViewHas('reportRows',fn($rows)=>$rows->count()===1 && $rows[0]['expected']===1 && $rows[0]['received']===0);
        $csv=$this->get(route('reports.csv',$filters))->assertOk()->streamedContent();
        $rows=array_map('str_getcsv',array_filter(explode("\n",trim($csv))));
        $this->assertCount(2,$rows);
        $this->assertSame('1',$rows[1][12]); // Expected deliverables.
        $this->assertSame('0',$rows[1][13]); // Sent deliverables: draft is not a submission.
        $this->assertSame('1',$rows[1][15]); // Still pending.
        $this->assertStringNotContainsString($this->users['OPERADOR_PROGRAMACION_CONTINUIDAD']->name,$csv);
        $xlsx=$this->get(route('reports.xlsx',$filters))->assertOk()->streamedContent();
        $path=tempnam(sys_get_temp_dir(),'siget-export-');
        try {
            file_put_contents($path,$xlsx);
            $sheet=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($path)->getActiveSheet();
            $this->assertSame(1,$sheet->getCell('M2')->getValue());
            $this->assertSame(0,$sheet->getCell('N2')->getValue());
            $this->assertSame(1,$sheet->getCell('P2')->getValue());
            $this->assertSame(2,$sheet->getHighestRow());
        } finally { unlink($path); }
        $pdf=$this->get(route('reports.pdf',$filters))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_month_filters_do_not_overflow_when_current_day_is_the_31st(): void
    {
        $this->deliverable('DIR_A','PENDIENTE',false);
        $this->load->update(['effective_open_at'=>'2026-02-15 10:00:00','effective_close_at'=>'2026-02-16 10:00:00']);
        $this->travelTo(now()->setDate(2026,10,31));
        $this->assertSame(1,$this->data('DIRECTOR_GENERAL',['from'=>'2026-02','to'=>'2026-02'])['workspace']['summary']['expected']);
        $this->actingAs($this->users['DIRECTOR_GENERAL'])->get(route('reports.index',['from'=>'2026-02','to'=>'2026-02']))
            ->assertOk()->assertViewHas('evidenceSummary',fn($s)=>$s['expected']===1);
    }

    public function test_expired_but_suspended_or_delivered_loads_do_not_become_deadline_alerts(): void
    {
        $d=$this->deliverable('DIR_A','PENDIENTE',false);
        foreach (['SUSPENDIDA','REPROGRAMADA','ENTREGADA','VALIDADO_Y_CERRADO'] as $state) {
            $this->load->update(['status'=>$state,'is_blocked'=>in_array($state,['SUSPENDIDA','REPROGRAMADA','VALIDADO_Y_CERRADO'])]);
            $this->assertSame(0,$this->data('DIRECTOR_TRANSMISION')['kpis']['overdue']);
        }
        $this->load->update(['status'=>'EN_CAPTURA','is_blocked'=>true]);
        $this->assertSame(0,$this->data('DIRECTOR_TRANSMISION')['workspace']['summary']['overdue']);
    }
}
