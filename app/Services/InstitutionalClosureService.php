<?php
namespace App\Services;

use App\Enums\DeliverableStatus;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use App\Models\EvidenceFile;
use App\Models\InstitutionalReview;
use App\Models\LoadClosure;
use App\Models\ScheduledLoad;
use App\Models\SignedDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\WorkflowException as RuntimeException;

final class InstitutionalClosureService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ReportService $reports,
        private readonly AlertService $alerts,
        private readonly AccountingNoticeService $accounting,
        private readonly LoadProgressService $progress,
        private readonly LoadStatusService $loadStatus,
        private readonly EvidenceRules $rules,
        private readonly AccessScopeService $access
    ) {}

    /**
     * Validate the complete institutional expediente, not individual evidence.
     * Every required deliverable from every direction in the dependency must
     * satisfy the pauta, file rules, validation state and programmed dates.
     */
    public function validateExpediente(ScheduledLoad $load): array
    {
        $load->load([
            'agency.units',
            'deliverables.organizationalUnit',
            'deliverables.templateRequirement',
            'deliverables.evidences.files',
            'deliverables.evidences.reviews',
        ]);

        $errors = [];
        $required = $load->deliverables
            ->filter(fn ($d) => $d->templateRequirement?->required);

        $agencyUnitIds = $load->agency?->units
            ?->where('active', true)->where('unit_type', 'DIRECTION')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];
        $representedUnitIds = $required
            ->pluck('organizational_unit_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($agencyUnitIds !== []) {
            $missingUnits = array_values(array_diff($agencyUnitIds, $representedUnitIds));
            if ($missingUnits !== []) {
                $errors[] = 'La dependencia aún no tiene todos sus entregables de las direcciones activas.';
            }
        }

        if ($required->isEmpty()) {
            $errors[] = 'El expediente no tiene requisitos obligatorios.';
        }

        foreach ($required as $deliverable) {
            $requirement = $deliverable->templateRequirement;
            $unitName = $deliverable->organizationalUnit?->name ?? 'Dirección no identificada';
            $evidence = $deliverable->evidences->sortByDesc('id')->first();

            if (!$evidence) {
                $errors[] = "{$unitName}: falta la evidencia requerida '{$requirement?->name}'.";
                continue;
            }

            $files = $evidence->files->where('version', $evidence->current_version);
            foreach ($this->rules->fileErrors($evidence) as $error) {
                $errors[] = $unitName.': '.$error;
            }
            if ($evidence->reviews()->where('review_type', 'DIRECTION')->where('evidence_version', $evidence->current_version)->latest('id')->value('decision') !== 'APROBADO') {
                $errors[] = $unitName.': falta aprobación de Dirección para la versión actual.';
            }
            $minFiles = max(1, (int) ($requirement?->min_files ?? 1));
            $maxFiles = $requirement?->max_files;
            if ($files->count() < $minFiles) {
                $errors[] = "{$unitName}: '{$requirement?->name}' requiere al menos {$minFiles} archivo(s).";
            }
            if ($maxFiles !== null && $files->count() > (int) $maxFiles) {
                $errors[] = "{$unitName}: '{$requirement?->name}' excede el máximo de {$maxFiles} archivo(s).";
            }

            $allowed = collect($requirement?->allowed_extensions ?? [])
                ->map(fn ($ext) => strtolower(ltrim($ext, '.')))
                ->all();
            if ($allowed !== []) {
                foreach ($files as $file) {
                    if (!in_array(strtolower($file->extension), $allowed, true)) {
                        $errors[] = "{$unitName}: el archivo '{$file->original_name}' no cumple los formatos de la pauta.";
                    }
                }
            }

            if ($requirement?->max_size_mb) {
                $maxBytes = (int) $requirement->max_size_mb * 1024 * 1024;
                foreach ($files as $file) {
                    if ((int) $file->size_bytes > $maxBytes) {
                        $errors[] = "{$unitName}: el archivo '{$file->original_name}' excede {$requirement->max_size_mb} MB.";
                    }
                }
            }

            $evidenceStatus = $evidence->status instanceof \BackedEnum
                ? $evidence->status->value
                : (string) $evidence->status;
            if ($requirement?->requires_validation && !in_array($evidenceStatus, [
                DeliverableStatus::VALIDADO->value,
                DeliverableStatus::CERRADO->value,
            ], true)) {
                $errors[] = "{$unitName}: la evidencia '{$requirement?->name}' aún no está validada.";
            }

            $deliverableStatus = $deliverable->status instanceof \BackedEnum
                ? $deliverable->status->value
                : (string) $deliverable->status;
            if (!in_array($deliverableStatus, [
                DeliverableStatus::VALIDADO->value,
                DeliverableStatus::CERRADO->value,
            ], true)) {
                $errors[] = "{$unitName}: el entregable '{$requirement?->name}' aún no está validado.";
            }

            if (!$deliverable->submitted_at) {
                $errors[] = "{$unitName}: '{$requirement?->name}' no tiene fecha de entrega registrada.";
            } elseif ($deliverable->due_at && $deliverable->submitted_at->gt($deliverable->due_at)) {
                $authorized = \App\Models\EvidenceCorrectionWindow::query()->where('deliverable_id', $deliverable->id)
                    ->whereNotNull('used_at')->whereNull('revoked_at')->where('evidence_version', $evidence->current_version)
                    ->where('opens_at', '<=', $deliverable->submitted_at)->where('closes_at', '>=', $deliverable->submitted_at)->exists();
                if (!$authorized && !$load->isRetroactiveBackfillActive()) {
                    $errors[] = "{$unitName}: '{$requirement?->name}' fue entregado fuera de una ventana autorizada.";
                }
            }
        }

        if ($load->effective_open_at && $load->effective_close_at && now()->lt($load->effective_close_at)) {
            $errors[] = 'El periodo programado de la pauta aún no ha concluido; el expediente no puede cerrarse todavía.';
        }

        return [
            'ready' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'required_deliverables' => $required->count(),
            'directions' => $required->pluck('organizational_unit_id')->filter()->unique()->count(),
        ];
    }

    public function updateChecklist(
        ScheduledLoad $load,
        int $userId,
        bool $evidencesCorrect,
        bool $packagePrepared,
        ?string $observations
    ): InstitutionalReview {
        return DB::transaction(function () use ($load, $userId, $evidencesCorrect, $packagePrepared, $observations) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $operationalAt = $load->operationalTimestamp();
        $this->assertInstitutionalActor($load, $userId, 'scheduled_load.verify');
        if ($load->is_blocked) {
            throw new RuntimeException('El expediente está bloqueado.');
        }
        $validation = $this->validateExpediente($load);
        if ($evidencesCorrect && !$validation['ready']) {
            throw new RuntimeException('El expediente aún no cumple la pauta completa: '.implode(' ', $validation['errors']));
        }

        $review = InstitutionalReview::query()->updateOrCreate(
            ['scheduled_load_id'=>$load->id],
            [
                'institutional_link_id'=>$userId,
                'evidences_correct'=>$evidencesCorrect,
                'package_prepared_for_signature'=>$packagePrepared,
                'observations'=>$observations,
                'started_at'=>$operationalAt,
                'verified_at'=>$evidencesCorrect && $packagePrepared && $validation['ready'] ? $operationalAt : null,
            ]
        );

        $newStatus = $evidencesCorrect && $packagePrepared && $validation['ready']
            ? ScheduledLoadStatus::VALIDADA->value
            : ScheduledLoadStatus::EN_REVISION_INSTITUCIONAL->value;

        $this->loadStatus->transition(
            $load,
            $newStatus,
            \App\Models\User::query()->find($userId),
            $evidencesCorrect && $packagePrepared && $validation['ready']
                ? 'Checklist institucional completo; expediente validado y listo para cierre.'
                : 'Checklist institucional actualizado.'
        );
        $load->update(['traffic_light'=>TrafficLight::PURPLE]);

        return $review;
        });
    }

    public function uploadSignedDocument(
        ScheduledLoad $load,
        UploadedFile $file,
        int $userId,
        array $metadata = []
    ): SignedDocument {
        $this->assertInstitutionalActor($load, $userId, 'scheduled_load.upload_signed');
        if ($load->is_blocked) {
            throw new RuntimeException('El expediente está bloqueado.');
        }
        $loadStatus = $load->status instanceof \BackedEnum
            ? $load->status->value
            : (string) $load->status;
        if (!in_array($loadStatus, [
            ScheduledLoadStatus::VALIDADA->value,
            ScheduledLoadStatus::LISTA_PARA_FIRMA->value,
        ], true)) {
            throw new RuntimeException('El expediente debe estar VALIDADO antes de incorporar el documento firmado.');
        }

        $validation = $this->validateExpediente($load);
        if (!$validation['ready'] || !$load->institutionalReview?->verified_at) {
            throw new RuntimeException('Debe completar la validación institucional antes de incorporar el firmado.');
        }
        $allowed = $load->template->allowed_signed_extensions ?? ['pdf'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension,$allowed,true)) {
            throw new RuntimeException('El formato del documento firmado no está permitido.');
        }

        $antivirusStatus = app(FileScanService::class)->assertClean($file);

        return DB::transaction(function () use ($load,$file,$userId,$metadata,$extension,$antivirusStatus) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $operationalAt = $load->operationalTimestamp();
            if ($load->is_blocked || $load->status->value !== 'VALIDADA' || !$this->validateExpediente($load)['ready'] || !$load->institutionalReview?->verified_at) {
                throw new RuntimeException('La validación institucional cambió; revise el expediente.');
            }
            $load->signedDocuments()->where('active', true)->update(['active' => false]);
            $document = SignedDocument::query()->create([
                'scheduled_load_id'=>$load->id,
                'uploaded_by'=>$userId,
                'document_type'=>'DIRECTOR_SIGNED_PACKAGE',
                'signer_name'=>$metadata['signer_name'] ?? null,
                'signer_position'=>$metadata['signer_position'] ?? null,
                'signed_on'=>$metadata['signed_on'] ?? null,
                'official_number'=>$metadata['official_number'] ?? null,
                'observations'=>$metadata['observations'] ?? null,
                'active'=>true,
            ]);

            $disk = config('siget.repository_disk',config('filesystems.default'));
            $storedName = Str::uuid().'.'.$extension;
            $path = $file->storeAs('siget/signed/'.$load->id,$storedName,$disk);
            if (!$path) { throw new RuntimeException('No se pudo guardar el documento firmado.'); }

            EvidenceFile::query()->create([
                'signed_document_id'=>$document->id,
                'uploaded_by'=>$userId,
                'original_name'=>$file->getClientOriginalName(),
                'stored_name'=>$storedName,
                'storage_disk'=>$disk,
                'storage_path'=>$path,
                'extension'=>$extension,
                'mime_type'=>$file->getMimeType() ?: 'application/octet-stream',
                'size_bytes'=>$file->getSize(),
                'sha256'=>hash_file('sha256',$file->getRealPath()),
                'antivirus_status'=>$antivirusStatus,
                'version'=>1,
            ]);

            $load->update([
                'traffic_light'=>TrafficLight::GREEN,
                'validated_at'=>$load->validated_at ?? $operationalAt,
                'validated_by'=>$load->validated_by ?? $userId,
            ]);

            $this->audit->record('signed_document.uploaded', $document, [], ['uploaded_by' => $userId]);
            return $document->fresh('files');
        });
    }

    public function close(ScheduledLoad $load, int $userId, ?string $comment): LoadClosure
    {
        $this->assertInstitutionalActor($load, $userId);
        return DB::transaction(function () use ($load,$userId,$comment) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $operationalAt = $load->operationalTimestamp();
            $load->load(['deliverables.templateRequirement','institutionalReview','signedDocuments.files']);

            $loadStatus = $load->status instanceof \BackedEnum
                ? $load->status->value
                : (string) $load->status;
            if ($loadStatus !== ScheduledLoadStatus::VALIDADA->value) {
                throw new RuntimeException('La carga debe encontrarse en estado VALIDADA antes de cerrarse.');
            }

            $validation = $this->validateExpediente($load);
            if (!$validation['ready']) {
                throw new RuntimeException('El expediente no cumple la pauta completa: '.implode(' ', $validation['errors']));
            }

            $review = $load->institutionalReview;
            if (!$review || !$review->evidences_correct || !$review->package_prepared_for_signature) {
                throw new RuntimeException('Debe completar las dos verificaciones institucionales del expediente completo.');
            }

            $signed = $load->signedDocuments->where('active',true)->sortByDesc('created_at')->first();
            if (!$signed || $signed->files->isEmpty()) {
                throw new RuntimeException('Debe adjuntar el documento firmado por el Director.');
            }

            $files = $load->deliverables->flatMap(fn ($d) => $d->evidences->flatMap(fn ($e) => $e->files->where('version', $e->current_version)))->concat($signed->files);
            foreach ($files as $stored) {
                if (in_array($stored->antivirus_status, ['INFECTED', 'FAILED', 'ERROR'], true) || (config('siget.antivirus_enabled') && !in_array($stored->antivirus_status, ['CLEAN', 'PASSED'], true))) {
                    throw new RuntimeException('Falta verificación antivirus satisfactoria del expediente firmado.');
                }
                $storage = Storage::disk($stored->storage_disk);
                if (!$storage->exists($stored->storage_path)) {
                    throw new RuntimeException('Falta un archivo físico del expediente.');
                }
                $stream = $storage->readStream($stored->storage_path);
                if (!is_resource($stream)) { throw new RuntimeException('No se pudo verificar el archivo.'); }
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);
                if (!hash_equals($stored->sha256, hash_final($hash))) {
                    throw new RuntimeException('La integridad de un archivo del expediente cambió.');
                }
            }
            $manifest = [
                'scheduled_load_id'=>$load->id,
                'closed_by'=>$userId,
                'closed_at'=>$operationalAt->toIso8601String(),
                'deliverables'=>$load->deliverables->pluck('id')->all(),
                'signed_document_id'=>$signed->id,
                'file_hashes'=>$files->pluck('sha256')->all(),
                'files'=>$files->map(fn ($f) => ['id' => $f->id, 'sha256' => $f->sha256, 'version' => $f->version, 'storage_path' => $f->storage_path])->values()->all(),
                'status'=>ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
            ];
            $packageHash = hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR));

            $closure = LoadClosure::query()->updateOrCreate(
                ['scheduled_load_id'=>$load->id],
                [
                    'institutional_review_id'=>$review->id,
                    'signed_document_id'=>$signed->id,
                    'closed_by'=>$userId,
                    'reopened'=>false, 'reopened_reason'=>null, 'reopened_by'=>null, 'reopened_at'=>null,
                    'closed_at'=>$operationalAt,
                    'closing_comment'=>$comment,
                    'package_sha256'=>$packageHash,
                    'integrity_manifest'=>$manifest,
                ]
            );

            $certificate = $this->reports->generateClosureCertificate($load,$closure);
            $closure->update(['closure_certificate_path'=>$certificate]);

            $load->deliverables()->update(['status'=>DeliverableStatus::CERRADO->value]);
            $this->loadStatus->transition(
                $load,
                ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
                \App\Models\User::query()->find($userId),
                'Expediente completo de la dependencia validado y cerrado.'
            );
            $load->update([
                'traffic_light'=>TrafficLight::DARK_GREEN,
                'is_blocked'=>true,
                'block_reason'=>'Expediente de dependencia validado y cerrado',
                'closed_at'=>$operationalAt,
                'closed_by'=>$userId,
                'row_version'=>$load->row_version + 1,
            ]);

            $this->audit->record('scheduled_load.closed',$load,[],[
                'closure_id'=>$closure->id,
                'package_sha256'=>$packageHash,
                'scope'=>'DEPENDENCY_COMPLETE_EXPEDIENTE',
            ]);
            DB::afterCommit(fn () => $this->alerts->notifyClosure($load->fresh()));
            $this->progress->recalculate($load);
            DB::afterCommit(fn () => $this->accounting->sendCompletionNotice($load->fresh()));

            return $closure->fresh();
        });
    }
    private function assertInstitutionalActor(ScheduledLoad $load, int $userId, string $permission = 'scheduled_load.close'): void
    {
        $user = \App\Models\User::query()->findOrFail($userId);
        if (!in_array($user->role?->code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)
            || !$user->hasPermission($permission) || !$load->agency?->active || !$this->access->canAccessLoad($user, $load)) {
            throw new RuntimeException('El cierre institucional corresponde al enlace o administrador autorizado.');
        }
    }
}
