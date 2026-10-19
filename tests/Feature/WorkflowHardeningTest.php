<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\{AuditLog, CalendarImport, CalendarImportRow, ContractingAgency, Evidence, EvidenceCorrectionWindow, EvidenceFile, EvidenceTemplate, NotificationRecord, ReviewAssignment, Role, ScheduledLoad, ScheduledLoadDeliverable, User};
use App\Services\{AccessScopeService, AlertService, EvidenceCorrectionService, EvidenceRules, EvidenceService, EvidenceWorkflowService, InstitutionalClosureService};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, Storage};
use Tests\TestCase;

class WorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    private ScheduledLoad $load;
    private array $deliverables;
    private array $operators;
    private array $directors;
    private User $enlace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
        Storage::fake('local');
        config(['siget.repository_disk' => 'local', 'siget.antivirus_enabled' => false]);
        Bus::fake();
        $this->seed([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
        $agency = ContractingAgency::where('code', 'IMSS')->firstOrFail();
        $this->enlace = $this->user('ENLACE_INSTITUCIONAL', ['contracting_agency_id' => $agency->id]);
        $import = CalendarImport::factory()->create(['contracting_agency_id' => $agency->id, 'uploaded_by' => $this->enlace->id]);
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id, 'source_column' => 'P']);
        $template = EvidenceTemplate::where('contracting_agency_id', $agency->id)->firstOrFail();
        $this->load = ScheduledLoad::create(['contracting_agency_id' => $agency->id, 'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id, 'template_id' => $template->id, 'title' => 'Expediente de aceptación',
            'original_open_at' => now()->subHour(), 'original_close_at' => now()->addHour(),
            'effective_open_at' => now()->subHour(), 'effective_close_at' => now()->addHour(),
            'status' => 'ABIERTA', 'traffic_light' => 'BLUE', 'is_blocked' => false]);
        foreach (['A' => 'TRANSMISION', 'B' => 'PROGRAMACION_CONTINUIDAD'] as $key => $role) {
            $unit = $agency->units()->where('code', 'DIR_'.$key)->firstOrFail();
            $this->operators[$key] = $this->user('OPERADOR_'.$role, ['organizational_unit_id' => $unit->id, 'contracting_agency_id' => $agency->id]);
            $this->directors[$key] = $this->user('DIRECTOR_'.$role, ['organizational_unit_id' => $unit->id]);
            $req = $template->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
            $this->deliverables[$key] = ScheduledLoadDeliverable::create(['scheduled_load_id' => $this->load->id,
                'template_requirement_id' => $req->id, 'organizational_unit_id' => $unit->id,
                'responsible_user_id' => $this->operators[$key]->id, 'status' => 'PENDIENTE', 'due_at' => now()->addHour()]);
        }
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('code', $role)->firstOrFail()->id]);
    }

    private function upload(string $key): Evidence
    {
        return app(EvidenceService::class)->upload($this->deliverables[$key]->fresh(), UploadedFile::fake()->create('evidencia.pdf', 10, 'application/pdf'), $this->operators[$key]);
    }

    private function approve(string $key): Evidence
    {
        $e = $this->upload($key);
        app(EvidenceWorkflowService::class)->submit($e, $this->operators[$key]);
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors[$key], 'APROBADO', 'Conforme a pauta');
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->enlace, 'APROBADO', 'Conforme expediente');
        return $e->fresh();
    }

    private function closeExpediente(): \App\Models\LoadClosure
    {
        $this->approve('A');
        $this->approve('B');
        $this->travel(2)->hours();
        app(InstitutionalClosureService::class)->updateChecklist($this->load->fresh(), $this->enlace->id, true, true, 'Completo');
        app(InstitutionalClosureService::class)->uploadSignedDocument($this->load->fresh(), UploadedFile::fake()->create('firmado.pdf', 10), $this->enlace->id, ['signer_name' => 'Director autorizado']);
        return app(InstitutionalClosureService::class)->close($this->load->fresh(), $this->enlace->id, 'Cierre de aceptación');
    }

    public function test_consolidated_identity_cannot_review_its_retired_alias_submission(): void
    {
        $evidence = $this->upload('A');
        $alias = $this->user('DIRECTOR_TRANSMISION', ['organizational_unit_id' => $this->directors['A']->organizational_unit_id,
            'status' => 'INACTIVE', 'metadata' => ['qa_merged_into' => $this->directors['A']->id]]);
        $evidence->update(['submitted_by' => $alias->id, 'status' => 'ENVIADO']);
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('su propia entrega');
        app(EvidenceWorkflowService::class)->review($evidence->fresh(), $this->directors['A'], 'APROBADO', 'Conforme');
    }

    public function test_database_roundtrip_preserves_institutional_window_instants(): void
    {
        $load = $this->load->fresh();
        $this->assertSame(now()->subHour()->getTimestamp(), $load->effective_open_at->getTimestamp());
        $this->assertSame(now()->addHour()->getTimestamp(), $load->effective_close_at->getTimestamp());
        $this->assertTrue(app(EvidenceRules::class)->isEnabled($this->deliverables['A']->fresh()));
    }

    public function test_first_upload_moves_load_to_capture_and_records_direction(): void
    {
        $this->upload('A');
        $this->assertSame('EN_CAPTURA', $this->load->fresh()->status->value);
        $history = $this->load->statusHistory()->where('new_status', 'EN_CAPTURA')->firstOrFail();
        $this->assertSame($this->deliverables['A']->organizational_unit_id, $history->metadata['organizational_unit_id']);
    }

    public function test_director_cannot_open_download_or_review_other_direction_by_id(): void
    {
        $e = $this->upload('B');
        $access = app(AccessScopeService::class);
        $this->assertFalse($access->canAccessEvidence($this->directors['A'], $e));
        $this->assertFalse($access->canReviewEvidence($this->directors['A'], $e));
        $this->actingAs($this->directors['A'])->get(route('evidences.show', $e))->assertForbidden();
        $this->get(route('evidence-files.download', $e->files->first()))->assertForbidden();
        $this->postJson(route('evidences.review', $e), ['decision' => 'APROBADO'])->assertForbidden();
        $this->assertSame('EN_CAPTURA', $e->fresh()->status->value);
    }

    public function test_operator_cannot_access_another_responsible_in_same_load(): void
    {
        $other = $this->user('OPERADOR_TRANSMISION', ['organizational_unit_id' => $this->operators['A']->organizational_unit_id, 'contracting_agency_id' => $this->load->contracting_agency_id]);
        $e = $this->upload('A');
        $this->assertFalse(app(AccessScopeService::class)->canAccessEvidence($other, $e));
        $this->actingAs($other)->get(route('evidences.show', $e))->assertForbidden();
    }

    public function test_upload_after_submission_is_blocked_and_files_are_unchanged(): void
    {
        $e = $this->upload('A');
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        try { $this->upload('A'); $this->fail('Debe bloquear archivos enviados.'); } catch (WorkflowException $e) {}
        $this->assertSame(1, EvidenceFile::whereNotNull('evidence_id')->count());
    }

    public function test_validated_evidence_cannot_be_modified_without_authorization(): void
    {
        $e = $this->approve('A');
        $this->expectException(WorkflowException::class);
        $this->upload('A');
    }

    public function test_review_requires_submission_and_direction_approval(): void
    {
        $e = $this->upload('A');
        $this->actingAs($this->directors['A'])->postJson(route('evidences.review', $e), ['decision' => 'APROBADO'])->assertUnprocessable();
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        $this->actingAs($this->enlace)->postJson(route('evidences.review', $e), ['decision' => 'APROBADO'])->assertUnprocessable();
        $this->assertSame(0, $e->reviews()->count());
    }

    public function test_director_cannot_close_the_institutional_expediente(): void
    {
        $this->actingAs($this->directors['A'])->post(route('loads.close', $this->load))->assertForbidden();
        $this->post(route('loads.reopen', $this->load))->assertForbidden();
    }

    public function test_deactivation_revokes_existing_session_on_next_request(): void
    {
        $this->actingAs($this->operators['A']);
        User::whereKey($this->operators['A']->id)->update(['status' => 'INACTIVE']);
        $this->get(route('loads.show', $this->load))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_role_revokes_existing_session(): void
    {
        $this->actingAs($this->operators['A']);
        $this->operators['A']->role->update(['active' => false]);
        $this->getJson(route('loads.show', $this->load))->assertForbidden();
        $this->assertGuest();
    }

    public function test_observation_requires_reason_and_correction_creates_new_version(): void
    {
        $e = $this->upload('A');
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        $this->actingAs($this->directors['A'])->postJson(route('evidences.review', $e), ['decision' => 'OBSERVADO'])->assertUnprocessable();
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors['A'], 'OBSERVADO', 'Corregir bitácora');
        $e = $this->upload('A');
        $this->assertSame(2, $e->current_version);
        $this->assertSame(2, $e->files->count());
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        $this->assertFalse(app(EvidenceWorkflowService::class)->hasDirectionApproval($e->fresh()));
    }

    public function test_late_correction_window_is_scoped_consumed_and_traced(): void
    {
        $e = $this->approve('A');
        $this->approve('B');
        $this->travel(2)->hours();
        app(EvidenceCorrectionService::class)->authorize($this->load->fresh(), $this->enlace,
            [$this->deliverables['A']->id], now()->subMinute(), now()->addHour(), 'Corrección extemporánea autorizada');
        $this->assertTrue(app(EvidenceRules::class)->isEnabled($this->deliverables['A']->fresh()));
        $this->assertFalse(app(EvidenceRules::class)->isEnabled($this->deliverables['B']->fresh()));
        $e = $this->upload('A');
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors['A'], 'APROBADO', 'Corregido');
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->enlace, 'APROBADO', 'Correcto');
        $this->assertNotNull(EvidenceCorrectionWindow::firstOrFail()->used_at);
        $this->assertTrue(app(InstitutionalClosureService::class)->validateExpediente($this->load->fresh())['ready']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'evidence.correction.authorized']);
    }

    public function test_full_flow_closes_and_reopens_preserving_manifest_and_requires_new_signature(): void
    {
        $closure = $this->closeExpediente();
        $firstHash = $closure->package_sha256;
        $firstCertificate = $closure->closure_certificate_path;
        $this->assertSame('VALIDADO_Y_CERRADO', $this->load->fresh()->status->value);
        $this->assertCount(3, $closure->integrity_manifest['files']);
        Storage::disk('local')->assertExists($firstCertificate);
        $this->assertTrue($this->load->fresh()->is_blocked);

        app(EvidenceCorrectionService::class)->authorize($this->load->fresh(), $this->enlace,
            [$this->deliverables['A']->id], now()->subMinute(), now()->addHour(), 'Reapertura por corrección documentada', true);
        $this->assertSame('REABIERTA', $this->load->fresh()->status->value);
        $this->assertTrue($closure->fresh()->reopened);
        $this->assertSame($firstHash, AuditLog::where('event', 'scheduled_load.reopened')->firstOrFail()->old_values['closure']['package_sha256']);
        $this->assertSame(0, $this->load->signedDocuments()->where('active', true)->count());
        $this->assertFalse(app(InstitutionalClosureService::class)->validateExpediente($this->load->fresh())['ready']);

        $e = $this->upload('A');
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors['A'], 'APROBADO', 'Nueva versión aprobada');
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->enlace, 'APROBADO', 'Revalidado');
        app(InstitutionalClosureService::class)->updateChecklist($this->load->fresh(), $this->enlace->id, true, true, 'Nuevo cierre');
        $this->actingAs($this->enlace)->postJson(route('loads.close', $this->load))->assertUnprocessable();
        app(InstitutionalClosureService::class)->uploadSignedDocument($this->load->fresh(), UploadedFile::fake()->create('nuevo-firmado.pdf', 10), $this->enlace->id);
        $next = app(InstitutionalClosureService::class)->close($this->load->fresh(), $this->enlace->id, 'Segundo cierre');
        $this->assertNotSame($firstHash, $next->package_sha256);
        $this->assertSame('VALIDADO_Y_CERRADO', $this->load->fresh()->status->value);
        Storage::disk('local')->assertExists($firstCertificate);
        $this->assertDatabaseHas('load_status_history', ['scheduled_load_id' => $this->load->id, 'new_status' => 'REABIERTA']);
    }

    public function test_dashboards_reconcile_through_capture_review_signature_closure_and_reopening(): void
    {
        $analytics = app(\App\Services\DashboardAnalyticsService::class);
        $summary = fn (User $u) => $analytics->forUser($u)['workspace']['summary'];
        $this->assertSame(2, $summary($this->enlace)['pending']);
        $e = $this->upload('A');
        $this->assertSame(1, $summary($this->operators['A'])['pending']);
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
        $this->assertSame(1, $summary($this->directors['A'])['review']);
        $this->actingAs($this->directors['A'])->get(route('dashboard'))->assertOk()->assertSee('Revisar evidencia');
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors['A'], 'APROBADO', 'Dirección conforme');
        $this->assertSame(1, $summary($this->enlace)['review']);
        $this->actingAs($this->enlace)->get(route('dashboard'))->assertOk()->assertSee('Revisar evidencia');
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->enlace, 'APROBADO', 'Institucional conforme');
        $this->assertSame(1, $summary($this->directors['A'])['validated']);
        $this->assertSame(1, $summary($this->operators['B'])['pending']);
        $this->approve('B');
        $this->travel(2)->hours();
        $this->assertSame(0, $summary($this->enlace)['overdue']);
        app(InstitutionalClosureService::class)->updateChecklist($this->load->fresh(), $this->enlace->id, true, true, 'Completo');
        app(InstitutionalClosureService::class)->uploadSignedDocument($this->load->fresh(), UploadedFile::fake()->create('firma-dashboard.pdf', 10), $this->enlace->id);
        $closure = app(InstitutionalClosureService::class)->close($this->load->fresh(), $this->enlace->id, 'Cierre verificado');
        $before = [$this->load->fresh()->getAttributes(), $closure->fresh()->getAttributes(), EvidenceFile::count(), AuditLog::count()];
        foreach ([$this->enlace, ...array_values($this->directors), ...array_values($this->operators)] as $u) {
            $data = $analytics->forUser($u);
            $expected = $u->id === $this->enlace->id ? 2 : 1;
            $this->assertSame($expected, $data['workspace']['summary']['validated']);
            $this->assertSame($expected, (int) ($data['evidence_funnel']['CERRADO'] ?? 0) + (int) ($data['evidence_funnel']['VALIDADO'] ?? 0));
            $this->assertSame(1, $data['kpis']['closed']);
            $this->actingAs($u)->get(route('dashboard'))->assertOk();
        }
        $this->assertSame($before, [$this->load->fresh()->getAttributes(), $closure->fresh()->getAttributes(), EvidenceFile::count(), AuditLog::count()]);
        app(EvidenceCorrectionService::class)->authorize($this->load->fresh(), $this->enlace,
            [$this->deliverables['A']->id], now()->subMinute(), now()->addHour(), 'Corrección de versión', true);
        $this->assertSame(1, $summary($this->operators['A'])['observed']);
        $this->assertSame(1, $summary($this->operators['B'])['validated']);
        $this->assertSame(0, $analytics->forUser($this->enlace)['kpis']['closed']);
        $this->assertTrue($closure->fresh()->reopened);
    }

    public function test_closed_checklist_and_submission_cannot_mutate_state(): void
    {
        $closure = $this->closeExpediente();
        $this->actingAs($this->enlace)->patchJson(route('loads.checklist', $this->load), ['evidences_correct' => true, 'package_prepared_for_signature' => true])->assertUnprocessable();
        $e = $this->deliverables['A']->evidences()->firstOrFail();
        $this->actingAs($this->operators['A'])->postJson(route('evidences.submit', $e))->assertUnprocessable();
        $this->assertSame('VALIDADO_Y_CERRADO', $this->load->fresh()->status->value);
        $this->assertSame($closure->package_sha256, $closure->fresh()->package_sha256);
    }

    public function test_fiscalization_is_assigned_and_does_not_change_institutional_state(): void
    {
        $e = $this->approve('A');
        $fiscal = $this->user('FISCALIZADOR');
        $this->assertFalse(app(AccessScopeService::class)->canReviewEvidence($fiscal, $e));
        ReviewAssignment::create(['scheduled_load_id' => $this->load->id, 'fiscalizador_id' => $fiscal->id, 'assigned_by' => $this->enlace->id, 'active' => true]);
        $prior = $this->load->fresh()->status->value;
        app(EvidenceWorkflowService::class)->review($e, $fiscal, 'OBSERVADO', 'Hallazgo de fiscalización');
        $this->assertSame('VALIDADO', $e->fresh()->status->value);
        $this->assertSame($prior, $this->load->fresh()->status->value);
        $this->assertDatabaseHas('evidence_reviews', ['reviewer_id' => $fiscal->id, 'review_type' => 'FISCALIZATION', 'evidence_version' => 1]);
    }

    public function test_alerts_include_specific_roles_and_exclude_other_agencies(): void
    {
        $otherAgency = ContractingAgency::where('code', 'IPAB')->firstOrFail();
        $other = $this->user('ENLACE_INSTITUCIONAL', ['contracting_agency_id' => $otherAgency->id]);
        app(AlertService::class)->notifyClosure($this->load);
        foreach (array_merge($this->operators, $this->directors, [$this->enlace]) as $user) {
            $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'scheduled_load_id' => $this->load->id]);
        }
        $this->assertDatabaseMissing('notifications', ['user_id' => $other->id, 'scheduled_load_id' => $this->load->id]);
    }

    public function test_old_versions_do_not_satisfy_current_minimum(): void
    {
        $e = $this->upload('A');
        $e->update(['current_version' => 2, 'status' => 'CORREGIDO']);
        $this->expectException(WorkflowException::class);
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
    }

    public function test_maximum_files_is_enforced_at_upload(): void
    {
        $this->deliverables['A']->templateRequirement->update(['max_files' => 1]);
        $this->upload('A');
        $this->expectException(WorkflowException::class);
        $this->upload('A');
    }

    public function test_correction_request_cannot_include_foreign_deliverable(): void
    {
        $this->actingAs($this->enlace)->postJson(route('loads.corrections', $this->load), ['deliverable_ids' => [999999],
            'reason' => 'Corrección con alcance incorrecto', 'opens_at' => now()->toDateTimeString(), 'closes_at' => now()->addHour()->toDateTimeString()])->assertUnprocessable();
        $this->assertSame(0, EvidenceCorrectionWindow::count());
    }

    public function test_user_role_direction_mismatch_is_rejected_at_creation(): void
    {
        $admin = $this->user('ADMINISTRADOR');
        $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'Perfil inválido', 'email' => 'wrong@example.test',
            'password' => 'temporal-segura', 'role_id' => $this->operators['A']->role_id,
            'organizational_unit_id' => $this->operators['B']->organizational_unit_id, 'contracting_agency_id' => $this->load->contracting_agency_id])
            ->assertSessionHasErrors('organizational_unit_id');
        $this->assertDatabaseMissing('users', ['email' => 'wrong@example.test']);
    }
    public function test_direction_history_does_not_expose_other_direction_reviews(): void
    {
        $this->approve('A');
        $e = $this->upload('B');
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['B']);
        app(EvidenceWorkflowService::class)->review($e->fresh(), $this->directors['B'], 'OBSERVADO', 'Comentario reservado de Programación');
        $this->actingAs($this->directors['A'])->get(route('history.index'))->assertOk()->assertDontSee('Comentario reservado de Programación');
        $this->get(route('loads.show', $this->load))->assertOk()->assertDontSee('Comentario reservado de Programación');
    }

    public function test_read_only_scope_cannot_upload_to_another_dependency(): void
    {
        $other = ContractingAgency::where('code', 'IPAB')->firstOrFail();
        $unit = $other->units()->where('code', 'DIR_A')->firstOrFail();
        $load = ScheduledLoad::factory()->create(['contracting_agency_id' => $other->id]);
        $req = $other->templates()->firstOrFail()->requirements()->where('responsible_unit_id', $unit->id)->firstOrFail();
        $d = ScheduledLoadDeliverable::create(['scheduled_load_id' => $load->id, 'template_requirement_id' => $req->id,
            'organizational_unit_id' => $unit->id, 'status' => 'PENDIENTE']);
        $this->operators['A']->scopes()->create(['contracting_agency_id' => $other->id, 'organizational_unit_id' => $unit->id, 'can_read' => true, 'can_write' => false]);
        $this->assertTrue(app(AccessScopeService::class)->canAccessDeliverable($this->operators['A'], $d));
        $this->assertFalse(app(AccessScopeService::class)->canUploadDeliverable($this->operators['A'], $d));
    }

    public function test_correction_window_does_not_override_active_suspension(): void
    {
        app(EvidenceCorrectionService::class)->authorize($this->load, $this->enlace, [$this->deliverables['A']->id], now()->subMinute(), now()->addHour(), 'Corrección de prueba autorizada');
        \App\Models\CalendarSuspension::create(['name' => 'Suspensión temporal', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
            'applies_to_all_agencies' => true, 'block_upload' => true, 'active' => true]);
        $this->assertFalse(app(EvidenceRules::class)->isEnabled($this->deliverables['A']->fresh()));
    }

    public function test_antivirus_pending_blocks_submission_when_enabled(): void
    {
        $e = $this->upload('A');
        config(['siget.antivirus_enabled' => true]);
        $e->files()->update(['antivirus_status' => 'PENDING']);
        $this->expectException(WorkflowException::class);
        app(EvidenceWorkflowService::class)->submit($e, $this->operators['A']);
    }

    public function test_corrupted_evidence_blocks_closure_without_changing_state(): void
    {
        $e = $this->approve('A');
        $this->approve('B');
        $this->travel(2)->hours();
        app(InstitutionalClosureService::class)->updateChecklist($this->load->fresh(), $this->enlace->id, true, true, 'Completo');
        app(InstitutionalClosureService::class)->uploadSignedDocument($this->load->fresh(), UploadedFile::fake()->create('firmado.pdf', 10), $this->enlace->id);
        Storage::disk('local')->put($e->files->first()->storage_path, 'Contenido alterado');
        $this->actingAs($this->enlace)->postJson(route('loads.close', $this->load))->assertUnprocessable();
        $this->assertSame('VALIDADA', $this->load->fresh()->status->value);
        $this->assertNull($this->load->fresh()->closure);
    }

    public function test_misassigned_existing_director_is_denied_even_if_role_has_permissions(): void
    {
        $director = $this->directors['A'];
        $director->update(['organizational_unit_id' => $this->operators['B']->organizational_unit_id]);
        $this->assertFalse(app(AccessScopeService::class)->canAccessLoad($director->fresh(), $this->load));
    }

    public function test_pauta_import_generates_both_directions_and_cannot_be_confirmed_twice(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['O6' => 'OCTUBRE', 'O7' => 14, 'P7' => 15, 'Q7' => 16, 'A9' => 'Campaña de aceptación', 'G9' => 'CANAL CATORCE', 'O9' => 'X'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        $path = tempnam(sys_get_temp_dir(), 'pauta-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
            $upload = new UploadedFile($path, 'Pauta_aceptacion.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $service = app(\App\Services\CalendarImportService::class);
            $import = $service->uploadAndValidate($upload, $this->load->contracting_agency_id, $this->enlace->id, 2026);
            $this->assertSame(1, $service->confirm($import, $this->enlace->id));
            $load = ScheduledLoad::where('calendar_import_id', $import->id)->firstOrFail();
            $this->assertSame(2, $load->deliverables()->count());
            foreach (['A', 'B'] as $key) {
                $this->assertTrue($load->deliverables()->where('responsible_user_id', $this->operators[$key]->id)->exists());
            }
            $this->load = $load;
            foreach (['A', 'B'] as $key) {
                $this->deliverables[$key] = $load->deliverables()->where('responsible_user_id', $this->operators[$key]->id)->firstOrFail();
                $this->approve($key);
            }
            $this->travel(1)->days();
            app(InstitutionalClosureService::class)->updateChecklist($load->fresh(), $this->enlace->id, true, true, 'Pauta completa');
            app(InstitutionalClosureService::class)->uploadSignedDocument($load->fresh(), UploadedFile::fake()->create('pauta-firmada.pdf', 10), $this->enlace->id);
            app(InstitutionalClosureService::class)->close($load->fresh(), $this->enlace->id, 'Cierre desde Excel');
            $this->assertSame('VALIDADO_Y_CERRADO', $load->fresh()->status->value);
            $this->expectException(WorkflowException::class);
            $service->confirm($import->fresh(), $this->enlace->id);
        } finally { if (is_file($path)) { unlink($path); } }
    }

    public function test_enlace_cannot_import_or_change_template_of_other_dependency(): void
    {
        $other = ContractingAgency::where('code', 'IPAB')->firstOrFail();
        $import = CalendarImport::factory()->create(['contracting_agency_id' => $other->id]);
        $this->actingAs($this->enlace)->get(route('calendar.import.preview', $import))->assertForbidden();
        $template = $other->templates()->firstOrFail();
        $this->post(route('admin.templates.requirements.store'), ['template_id' => $template->id,
            'code' => 'FOREIGN', 'name' => 'Requisito ajeno', 'responsible_unit_id' => $other->units()->firstOrFail()->id,
            'allowed_extensions' => 'pdf', 'min_files' => 1, 'max_files' => 1, 'max_size_mb' => 10])->assertForbidden();
    }

    public function test_rescheduling_uses_current_suspensions_and_opens_once(): void
    {
        $this->actingAs($this->enlace)->postJson(route('loads.reschedule', $this->load), ['reason' => 'Reprogramación de aceptación',
            'opens_at' => now()->addDay()->toDateTimeString(), 'closes_at' => now()->addDays(2)->toDateTimeString()])->assertRedirect();
        $this->assertSame('REPROGRAMADA', $this->load->fresh()->status->value);
        $this->travel(1)->days();
        $this->artisan('siget:open-rescheduled-loads')->assertSuccessful();
        $this->artisan('siget:open-rescheduled-loads')->assertSuccessful();
        $this->assertSame('REPROGRAMADA_ABIERTA', $this->load->fresh()->status->value);
        $this->assertFalse($this->load->fresh()->is_blocked);
        $this->assertSame(1, $this->load->statusHistory()->where('new_status', 'REPROGRAMADA_ABIERTA')->count());
    }

}
