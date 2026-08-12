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
use RuntimeException;

final class InstitutionalClosureService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ReportService $reports,
        private readonly AlertService $alerts,
        private readonly AccountingNoticeService $accounting,
        private readonly LoadProgressService $progress,
        private readonly LoadStatusService $loadStatus
    ) {}

    public function updateChecklist(
        ScheduledLoad $load,
        int $userId,
        bool $evidencesCorrect,
        bool $packagePrepared,
        ?string $observations
    ): InstitutionalReview {
        $review = InstitutionalReview::query()->updateOrCreate(
            ['scheduled_load_id'=>$load->id],
            [
                'institutional_link_id'=>$userId,
                'evidences_correct'=>$evidencesCorrect,
                'package_prepared_for_signature'=>$packagePrepared,
                'observations'=>$observations,
                'started_at'=>now(),
                'verified_at'=>$evidencesCorrect && $packagePrepared ? now() : null,
            ]
        );

        $newStatus = $evidencesCorrect && $packagePrepared
            ? ScheduledLoadStatus::LISTA_PARA_FIRMA->value
            : ScheduledLoadStatus::EN_REVISION_INSTITUCIONAL->value;

        $this->loadStatus->transition(
            $load,
            $newStatus,
            \App\Models\User::query()->find($userId),
            $evidencesCorrect && $packagePrepared
                ? 'Checklist institucional completo; expediente listo para firma.'
                : 'Checklist institucional actualizado.'
        );
        $load->update(['traffic_light'=>TrafficLight::PURPLE]);

        return $review;
    }

    public function uploadSignedDocument(
        ScheduledLoad $load,
        UploadedFile $file,
        int $userId,
        array $metadata = []
    ): SignedDocument {
        $allowed = $load->template->allowed_signed_extensions ?? ['pdf'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension,$allowed,true)) {
            throw new RuntimeException('El formato del documento firmado no está permitido.');
        }

        return DB::transaction(function () use ($load,$file,$userId,$metadata,$extension) {
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
                'antivirus_status'=>config('siget.antivirus_enabled') ? 'PENDING' : 'SKIPPED',
                'version'=>1,
            ]);

            $this->loadStatus->transition(
                $load,
                ScheduledLoadStatus::VALIDADA->value,
                \App\Models\User::query()->find($userId),
                'Documento firmado incorporado al expediente.'
            );
            $load->update([
                'traffic_light'=>TrafficLight::GREEN,
                'validated_at'=>now(),
                'validated_by'=>$userId,
            ]);

            return $document->fresh('files');
        });
    }

    public function close(ScheduledLoad $load, int $userId, ?string $comment): LoadClosure
    {
        return DB::transaction(function () use ($load,$userId,$comment) {
            $load->refresh()->load(['deliverables.templateRequirement','institutionalReview','signedDocuments.files']);

            $review = $load->institutionalReview;
            if (!$review || !$review->evidences_correct || !$review->package_prepared_for_signature) {
                throw new RuntimeException('Debe completar las dos verificaciones institucionales.');
            }

            $pending = $load->deliverables
                ->filter(fn ($item) => $item->templateRequirement->required)
                ->filter(fn ($item) => !in_array($item->status, [
                    DeliverableStatus::VALIDADO,
                    DeliverableStatus::CERRADO,
                ], true));

            if ($pending->isNotEmpty()) {
                throw new RuntimeException('Existen entregables obligatorios pendientes de validación.');
            }

            $signed = $load->signedDocuments->where('active',true)->sortByDesc('created_at')->first();
            if (!$signed || $signed->files->isEmpty()) {
                throw new RuntimeException('Debe adjuntar el documento firmado por el Director.');
            }

            $manifest = [
                'scheduled_load_id'=>$load->id,
                'closed_by'=>$userId,
                'closed_at'=>now()->toIso8601String(),
                'deliverables'=>$load->deliverables->pluck('id')->all(),
                'signed_document_id'=>$signed->id,
                'file_hashes'=>$signed->files->pluck('sha256')->all(),
                'status'=>ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
            ];
            $packageHash = hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR));

            $closure = LoadClosure::query()->updateOrCreate(
                ['scheduled_load_id'=>$load->id],
                [
                    'institutional_review_id'=>$review->id,
                    'signed_document_id'=>$signed->id,
                    'closed_by'=>$userId,
                    'closed_at'=>now(),
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
                'Expediente validado y cerrado.'
            );
            $load->update([
                'traffic_light'=>TrafficLight::DARK_GREEN,
                'is_blocked'=>true,
                'block_reason'=>'Carga validada y cerrada',
                'closed_at'=>now(),
                'closed_by'=>$userId,
                'row_version'=>$load->row_version + 1,
            ]);

            $this->audit->record('scheduled_load.closed',$load,[],[
                'closure_id'=>$closure->id,
                'package_sha256'=>$packageHash,
            ]);
            $this->alerts->notifyClosure($load);
            $this->progress->recalculate($load);
            $this->accounting->sendCompletionNotice($load->fresh());

            return $closure->fresh();
        });
    }
}
