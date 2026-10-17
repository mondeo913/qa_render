<?php

namespace Tests\Feature;

use App\Models\{CalendarImport, CalendarImportRow, ContractingAgency, Evidence, EvidenceTemplate, Role, ScheduledLoad, ScheduledLoadDeliverable, User};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Bus, Storage};
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx};
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ScheduledLoad $load;
    private User $enlace;
    private array $operators = [], $directors = [], $deliverables = [];

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
        $row = CalendarImportRow::factory()->create(['calendar_import_id' => $import->id]);
        $template = EvidenceTemplate::where('contracting_agency_id', $agency->id)->firstOrFail();
        $this->load = ScheduledLoad::create(['contracting_agency_id' => $agency->id, 'calendar_import_id' => $import->id,
            'calendar_import_row_id' => $row->id, 'template_id' => $template->id, 'title' => 'Aceptación API',
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

    private function asToken(User $user): self
    {
        Auth::forgetGuards();
        return $this->withToken($user->createToken('Acceptance', ['siget:api', 'role:'.$user->role_id], now()->addHours(8))->plainTextToken);
    }

    private function upload(string $key): int
    {
        return $this->asToken($this->operators[$key])->post('/api/v1/evidences', [
            'deliverable_id' => $this->deliverables[$key]->id,
            'file' => UploadedFile::fake()->create('evidencia.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');
    }

    private function approve(string $key): int
    {
        $id = $this->upload($key);
        $this->asToken($this->operators[$key])->postJson("/api/v1/evidences/$id/submit")->assertOk()->assertJsonPath('data.status', 'ENVIADO');
        $this->asToken($this->directors[$key])->postJson("/api/v1/evidences/$id/reviews", ['decision' => 'APROBADO', 'comments' => 'Conforme'])->assertOk()->assertJsonPath('data.status', 'EN_REVISION');
        $this->asToken($this->enlace)->postJson("/api/v1/evidences/$id/reviews", ['decision' => 'APROBADO', 'comments' => 'Validación institucional'])->assertOk()->assertJsonPath('data.status', 'VALIDADO');
        return $id;
    }

    public function test_api_requires_token_and_returns_json_without_accept_header(): void
    {
        $this->get('/api/v1/loads')->assertUnauthorized()->assertHeader('content-type', 'application/json')->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/v1/register', [])->assertNotFound();
        $this->postJson('/api/v1/auth/token', ['email' => $this->enlace->email, 'password' => 'bad', 'device_name' => 'Test'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_hashes_expires_and_revokes_tokens_without_exposing_credentials(): void
    {
        $this->enlace->update(['password' => 'Secure_Api_2026!']);
        $result = $this->postJson('/api/v1/auth/token', ['email' => $this->enlace->email, 'password' => 'Secure_Api_2026!', 'device_name' => 'Infraestructura'])
            ->assertCreated()->assertJsonPath('token_type', 'Bearer');
        $token = $result->json('access_token');
        $this->assertNotSame($token, $this->enlace->tokens()->firstOrFail()->token);
        Auth::forgetGuards();
        $me = $this->withToken($token)->get('/api/v1/me')->assertOk()->assertJsonPath('data.id', $this->enlace->id);
        $this->assertStringNotContainsString('password', $me->getContent());
        $this->deleteJson('/api/v1/auth/token')->assertNoContent();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_expired_token_and_token_without_api_ability_are_rejected(): void
    {
        $token = $this->enlace->createToken('expired', ['siget:api', 'role:'.$this->enlace->role_id], now()->subSecond());
        $this->withToken($token->plainTextToken)->get('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $token = $this->enlace->createToken('wrong', ['reports']);
        $this->withToken($token->plainTextToken)->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_password_role_and_status_changes_revoke_tokens(): void
    {
        foreach ([['password' => 'New_Api_2026!'], ['role_id' => Role::where('code', 'DIRECTOR_GENERAL')->value('id')], ['status' => 'INACTIVE']] as $change) {
            $this->asToken($this->enlace);
            $this->enlace->update($change);
            $this->assertSame(0, $this->enlace->tokens()->count());
            Auth::forgetGuards();
            $this->get('/api/v1/me')->assertUnauthorized();
        }
    }

    public function test_direct_database_deactivation_and_inactive_role_reject_existing_token(): void
    {
        $this->asToken($this->operators['A']);
        User::whereKey($this->operators['A']->id)->update(['status' => 'INACTIVE']);
        $this->get('/api/v1/me')->assertUnauthorized();
        $this->asToken($this->operators['B']);
        $this->operators['B']->role->update(['active' => false]);
        $this->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_and_authenticated_rate_limits_are_enforced(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/v1/auth/token', ['email' => 'invalid@siget.local', 'password' => 'bad', 'device_name' => 'Test'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/token', ['email' => 'invalid@siget.local', 'password' => 'bad', 'device_name' => 'Test'])->assertStatus(429);
        config(['api.requests_per_minute' => 2]);
        $this->asToken($this->enlace)->get('/api/v1/me')->assertOk();
        $this->get('/api/v1/me')->assertOk();
        $this->get('/api/v1/me')->assertStatus(429);
    }

    public function test_load_and_file_resources_are_scoped_by_direction_and_responsible(): void
    {
        $id = $this->upload('B');
        $file = Evidence::findOrFail($id)->files()->firstOrFail();
        $this->asToken($this->directors['A'])->getJson('/api/v1/loads/'.$this->load->id)
            ->assertOk()->assertJsonCount(1, 'data.deliverables')->assertJsonPath('data.deliverables.0.id', $this->deliverables['A']->id);
        $this->getJson("/api/v1/evidences/$id")->assertForbidden();
        $this->get("/api/v1/files/{$file->id}/download")->assertForbidden();
        $other = $this->user('OPERADOR_PROGRAMACION_CONTINUIDAD', ['organizational_unit_id' => $this->operators['B']->organizational_unit_id, 'contracting_agency_id' => $this->load->contracting_agency_id]);
        $this->asToken($other)->getJson('/api/v1/loads/'.$this->load->id)->assertForbidden();
        $this->asToken($this->operators['B'])->get("/api/v1/files/{$file->id}/download")->assertOk();
        $result = $this->getJson("/api/v1/evidences/$id")->assertOk()->getContent();
        foreach (['storage_path', 'stored_name', 'storage_disk', 'metadata'] as $secret) $this->assertStringNotContainsString($secret, $result);
    }

    public function test_cross_agency_filter_and_pagination_cannot_expand_access(): void
    {
        $agency = ContractingAgency::where('code', 'IPAB')->firstOrFail();
        $this->asToken($this->enlace)->getJson('/api/v1/agencies')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/loads?contracting_agency_id='.$agency->id)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/loads?per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/loads?per_page=101')->assertUnprocessable();
    }

    public function test_calendar_preserves_agency_dates_color_and_bounded_range_for_readers(): void
    {
        foreach ([$this->operators['A'], $this->directors['B'], $this->enlace, $this->user('DIRECTOR_GENERAL')] as $user) {
            $this->asToken($user)->getJson('/api/v1/calendar/events?start=2026-10-01&end=2026-11-01')
                ->assertOk()->assertJsonPath('0.extendedProps.contracting_agency_id', $this->load->contracting_agency_id)
                ->assertJsonStructure([['backgroundColor', 'extendedProps' => ['appearance']]]);
        }
        $this->getJson('/api/v1/calendar/events?start=2026-01-01&end=2027-01-01')->assertUnprocessable();
        $this->getJson('/api/v1/calendar/events')->assertUnprocessable();
    }

    public function test_filename_pauta_import_confirmation_and_reconfirmation_guard(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Nombre del Archivo para bitácora'],
            ['01_XHSPRAG_26.07.07.TESTIGO.C14TATVBI2601_23.25.28.mp4'],
            ['02_XHSPRAG_26.07.15.TESTIGO.C14TATVBI2601_23.25.28.mp4']]);
        $path = tempnam(sys_get_temp_dir(), 'pauta');
        (new Xlsx($book))->save($path);
        try {
            $id = $this->asToken($this->enlace)->post('/api/v1/imports', ['contracting_agency_id' => $this->load->contracting_agency_id,
                'schedule_year' => 2026, 'calendar_file' => new UploadedFile($path, 'pauta.xlsx', null, null, true)])
                ->assertCreated()->assertJsonPath('data.status', 'VALIDATED')->json('data.id');
            $this->getJson("/api/v1/imports/$id")->assertOk()->assertJsonCount(2, 'rows');
            $this->postJson("/api/v1/imports/$id/confirm")->assertOk()->assertJsonPath('loads_created', 2);
            $this->postJson("/api/v1/imports/$id/confirm")->assertUnprocessable();
            $dates = ScheduledLoad::where('calendar_import_id', $id)->orderBy('effective_open_at')->get()->map(fn ($load) => $load->effective_open_at->toDateString())->all();
            $this->assertSame(['2026-07-07', '2026-07-15'], $dates);
            $this->assertSame(4, ScheduledLoadDeliverable::whereIn('scheduled_load_id', ScheduledLoad::where('calendar_import_id', $id)->select('id'))->count());
        } finally { @unlink($path); }
    }

    public function test_permission_and_ordering_failures_are_json_422_or_403_with_no_mutation(): void
    {
        $id = $this->upload('A');
        $this->asToken($this->directors['A'])->postJson("/api/v1/evidences/$id/reviews", ['decision' => 'APROBADO'])->assertUnprocessable();
        $this->asToken($this->operators['A'])->postJson("/api/v1/evidences/$id/submit")->assertOk();
        $this->post('/api/v1/evidences', ['deliverable_id' => $this->deliverables['A']->id, 'file' => UploadedFile::fake()->create('extra.pdf', 10)])->assertUnprocessable();
        $this->asToken($this->enlace)->postJson("/api/v1/evidences/$id/reviews", ['decision' => 'APROBADO'])->assertUnprocessable();
        $this->asToken($this->directors['A'])->postJson('/api/v1/loads/'.$this->load->id.'/close')->assertForbidden();
        $this->assertSame(0, Evidence::findOrFail($id)->reviews()->count());
    }

    public function test_complete_two_direction_flow_signature_closure_reopen_and_version_correction(): void
    {
        $first = $this->approve('A');
        $this->approve('B');
        $this->travel(2)->hours();
        $base = '/api/v1/loads/'.$this->load->id;
        $this->asToken($this->enlace)->postJson($base.'/checklist', ['evidences_correct' => true, 'package_prepared_for_signature' => true])->assertOk();
        $this->postJson($base.'/signature-package')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post($base.'/signed-document', ['file' => UploadedFile::fake()->create('firmado.pdf', 10), 'signer_name' => 'Director autorizado'])->assertCreated();
        $this->postJson($base.'/close', ['closing_comment' => 'Prueba integral API'])->assertOk()->assertJsonPath('data.status', 'VALIDADO_Y_CERRADO');
        $oldHash = $this->load->fresh()->closure->package_sha256;
        $this->postJson($base.'/reopen', ['deliverable_ids' => [$this->deliverables['A']->id], 'reason' => 'Corrección autorizada por revisión del expediente',
            'opens_at' => now()->subMinute()->toIso8601String(), 'closes_at' => now()->addHour()->toIso8601String()])->assertOk()->assertJsonPath('data.status', 'REABIERTA');
        $this->assertNotEmpty($oldHash);
        $this->assertTrue($this->load->fresh()->closure->reopened);
        $this->assertSame($first, $this->approve('A'));
        $this->assertSame(2, Evidence::findOrFail($first)->current_version);
        $this->asToken($this->enlace)->postJson($base.'/close')->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['event' => 'scheduled_load.reopened']);
    }

    public function test_fiscal_assignment_controls_reads_and_cannot_close_or_alter_institutional_validation(): void
    {
        $id = $this->approve('A');
        $fiscal = $this->user('FISCALIZADOR');
        $base = '/api/v1/loads/'.$this->load->id;
        $this->asToken($fiscal)->getJson($base)->assertForbidden();
        $this->asToken($this->enlace)->putJson($base.'/fiscal-assignment', ['fiscalizador_id' => $fiscal->id])->assertOk();
        $this->asToken($fiscal)->getJson($base)->assertOk();
        $this->postJson("/api/v1/evidences/$id/reviews", ['decision' => 'OBSERVADO', 'comments' => 'Observación de fiscalización'])->assertOk()->assertJsonPath('data.status', 'VALIDADO');
        $this->postJson($base.'/close')->assertForbidden();
    }

    public function test_dashboard_safe_aggregates_reconcile_with_visible_loads_and_reports_require_export_permission(): void
    {
        $this->asToken($this->directors['A'])->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.kpis.total', 1)->assertJsonPath('data.evidence_summary.expected', 1);
        $this->get('/api/v1/reports/csv')->assertForbidden();
        $this->asToken($this->user('DIRECTOR_GENERAL'))->get('/api/v1/reports/csv')->assertOk();
    }

    public function test_admin_general_users_receive_existing_and_new_agencies_without_crossing_direction(): void
    {
        $admin = $this->user('ADMINISTRADOR');
        $this->asToken($this->enlace)->get('/api/v1/admin/users')->assertForbidden();
        $this->asToken($admin)->get('/api/v1/admin/roles')->assertOk();
        $this->get('/api/v1/admin/agencies')->assertOk()->assertJsonCount(7, 'data');
        $userId = $this->postJson('/api/v1/admin/users', ['name' => 'Operativo general', 'email' => 'general@institucion.gob.mx',
            'password' => 'Operativo_General_2026!', 'role_id' => $this->operators['A']->role_id,
            'organizational_unit_id' => $this->operators['A']->organizational_unit_id, 'general_profile' => true])
            ->assertCreated()->assertJsonPath('data.general_profile', true)->assertJsonCount(7, 'data.scopes')->json('data.id');
        $agencyId = $this->postJson('/api/v1/admin/agencies', ['code' => 'NUEVA_API', 'name' => 'Dependencia API'])->assertCreated()->json('data.id');
        $general = User::findOrFail($userId);
        $scope = $general->scopes()->where('contracting_agency_id', $agencyId)->firstOrFail();
        $this->assertTrue((bool) $scope->can_write);
        $this->assertSame('DIR_A', \App\Models\OrganizationalUnit::findOrFail($scope->organizational_unit_id)->code);
        $this->asToken($general)->get('/api/v1/agencies')->assertOk()->assertJsonCount(8, 'data');
        $this->get('/api/v1/admin/users')->assertForbidden();
        $this->asToken($admin)->patchJson("/api/v1/admin/users/$userId/status", ['status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->patchJson("/api/v1/admin/users/$userId/status", ['status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->assertSame(0, $general->tokens()->count());
    }

    public function test_user_admin_validation_does_not_allow_wrong_direction_or_self_deactivation(): void
    {
        $admin = $this->user('ADMINISTRADOR');
        $this->asToken($admin)->postJson('/api/v1/admin/users', ['name' => 'Usuario inválido', 'email' => 'invalid@institucion.gob.mx',
            'password' => 'Strong_Password_2026!', 'role_id' => $this->operators['A']->role_id,
            'organizational_unit_id' => $this->operators['B']->organizational_unit_id, 'general_profile' => true])->assertUnprocessable();
        $this->patchJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'INACTIVE'])->assertUnprocessable();
        $this->assertSame('ACTIVE', $admin->fresh()->status);
    }

    public function test_enabled_antivirus_rejects_unsafe_uploads_and_clean_files_complete_the_whole_flow(): void
    {
        config(['siget.antivirus_enabled' => true, 'siget.antivirus_command' => 'clamdscan']);
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result(exitCode: 1)]);
        $this->asToken($this->operators['A'])->post('/api/v1/evidences', ['deliverable_id' => $this->deliverables['A']->id,
            'file' => UploadedFile::fake()->create('infectado.pdf', 10)])->assertUnprocessable();
        $this->assertDatabaseCount('evidence_files', 0);
        // Scanner exit protocol stub; production image acceptance additionally exercises real ClamAV/EICAR.
        \Illuminate\Support\Facades\Process::fake();
        $this->approve('A');
        $this->approve('B');
        $this->assertDatabaseCount('evidence_files', 2);
        $this->assertSame(2, \App\Models\EvidenceFile::where('antivirus_status', 'CLEAN')->count());
        $this->travel(2)->hours();
        $base = '/api/v1/loads/'.$this->load->id;
        $this->asToken($this->enlace)->postJson($base.'/checklist', ['evidences_correct' => true, 'package_prepared_for_signature' => true])->assertOk();
        $this->post($base.'/signed-document', ['file' => UploadedFile::fake()->create('firmado.pdf', 10)])->assertCreated();
        $this->postJson($base.'/close')->assertOk()->assertJsonPath('data.status', 'VALIDADO_Y_CERRADO');
        $this->assertSame(3, \App\Models\EvidenceFile::where('antivirus_status', 'CLEAN')->count());
        \Illuminate\Support\Facades\Process::assertRan(fn ($process) => $process->command[0] === 'clamdscan' && in_array('--stream', $process->command, true));
    }

    public function test_name_edit_preserves_explicit_scope_restrictions_when_optional_assignment_fields_are_omitted(): void
    {
        $general = $this->operators['A'];
        app(\App\Services\UserProfileScopeService::class)->apply($general, true);
        $restricted = $general->scopes()->where('contracting_agency_id', ContractingAgency::where('code', 'IPAB')->value('id'))->firstOrFail();
        $restricted->update(['can_write' => false]);
        $token = $general->createToken('Preserved device', ['siget:api', 'role:'.$general->role_id]);
        $this->asToken($this->user('ADMINISTRADOR'))->putJson('/api/v1/admin/users/'.$general->id, [
            'name' => 'Nombre actualizado', 'email' => $general->email, 'role_id' => $general->role_id])->assertOk();
        $this->assertFalse((bool) $restricted->fresh()->can_write);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertSame($general->contracting_agency_id, $general->fresh()->contracting_agency_id);
    }

    public function test_rescheduling_api_preserves_agency_and_requires_the_authorized_window_before_upload(): void
    {
        $opens = now()->addDay()->setTime(8, 0);
        $closes = $opens->copy()->addHours(8);
        $base = '/api/v1/loads/'.$this->load->id;
        $this->asToken($this->enlace)->postJson($base.'/reschedule', ['reason' => 'Reprogramación institucional de aceptación',
            'opens_at' => $opens->toIso8601String(), 'closes_at' => $closes->toIso8601String()])->assertOk()
            ->assertJsonPath('data.status', 'REPROGRAMADA')->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.agency.id', $this->load->contracting_agency_id);
        $this->asToken($this->operators['A'])->post('/api/v1/evidences', ['deliverable_id' => $this->deliverables['A']->id,
            'file' => UploadedFile::fake()->create('prematuro.pdf', 10)])->assertUnprocessable();
        $this->travelTo($opens->copy()->addMinute());
        $this->artisan('siget:open-rescheduled-loads')->assertSuccessful();
        $this->asToken($this->operators['A'])->getJson($base)->assertOk()->assertJsonPath('data.status', 'REPROGRAMADA_ABIERTA');
        $this->upload('A');
        $this->asToken($this->enlace)->postJson($base.'/reschedule', ['reason' => 'No se debe reprogramar una captura en curso',
            'opens_at' => $opens->toIso8601String(), 'closes_at' => $closes->toIso8601String()])->assertUnprocessable();
    }
}
