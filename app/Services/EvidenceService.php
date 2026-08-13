<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceFile;
use App\Models\RepositoryFolder;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class EvidenceService
{
    public function __construct(
        private readonly CalendarAvailabilityService $availability,
        private readonly AccessScopeService $access,
        private readonly AuditService $audit,
        private readonly LoadProgressService $progress,
        private readonly RepositoryService $repository
    ) {}

    public function upload(
        ScheduledLoadDeliverable $deliverable,
        UploadedFile $file,
        User $user,
        ?string $title = null
    ): Evidence {
        if (!$this->access->canAccessDeliverable($user, $deliverable)) {
            throw new RuntimeException(
                'No tiene acceso al entregable de esta dirección.'
            );
        }

        $load = $deliverable->scheduledLoad;

        if (!$this->availability->isEnabled($load, now())) {
            throw new RuntimeException('La fecha aún no está habilitada para carga.');
        }

        $requirement = $deliverable->templateRequirement;
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = array_values(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $requirement->allowed_extensions ?? []
        )));

        if (!in_array($extension, $allowedExtensions, true)) {
            $allowed = implode(', ', array_map(static fn ($value) => '.'.$value, $allowedExtensions));
            throw new RuntimeException(
                'Archivo no permitido. El formato '.$extension.' no está autorizado para esta evidencia.'
                .($allowed ? ' Formatos permitidos: '.$allowed.'.' : '')
            );
        }

        if ($file->getSize() > $requirement->max_size_mb * 1024 * 1024) {
            throw new RuntimeException('El archivo excede el tamaño máximo permitido.');
        }

        return DB::transaction(function () use (
            $deliverable,
            $file,
            $user,
            $title,
            $extension,
            $load
        ) {
            $evidence = Evidence::query()->firstOrCreate(
                ['deliverable_id' => $deliverable->id],
                [
                    'scheduled_load_id' => $deliverable->scheduled_load_id,
                    'submitted_by' => $user->id,
                    'title' => $title ?: $deliverable->templateRequirement->name,
                    'status' => 'EN_CAPTURA',
                    'current_version' => 1,
                    'revision_number' => 1,
                ]
            );

            $currentStatus = $evidence->status instanceof \BackedEnum
                ? $evidence->status->value
                : (string) $evidence->status;

            if (in_array($currentStatus, ['OBSERVADO', 'RECHAZADO'], true)) {
                $evidence->increment('current_version');
                $evidence->increment('revision_number');
                $evidence->update([
                    'status' => 'CORREGIDO',
                    'submitted_by' => $user->id,
                    'validated_at' => null,
                    'validated_by' => null,
                ]);
            }

            $folder = RepositoryFolder::query()
                ->where('scheduled_load_id', $deliverable->scheduled_load_id)
                ->where('organizational_unit_id', $deliverable->organizational_unit_id)
                ->first();

            if (!$folder) {
                $this->repository->createLoadTree($load, $user->id);
                $folder = RepositoryFolder::query()
                    ->where('scheduled_load_id', $deliverable->scheduled_load_id)
                    ->where('organizational_unit_id', $deliverable->organizational_unit_id)
                    ->first();
            }

            if (!$folder) {
                throw new RuntimeException('No se pudo crear el repositorio individual de la dirección.');
            }

            $disk = config(
                'siget.repository_disk',
                config('filesystems.default')
            );

            $storedName = Str::uuid().'.'.$extension;
            $path = $file->storeAs(
                'siget/evidences/'.$evidence->id.'/v'.$evidence->current_version,
                $storedName,
                $disk
            );

            EvidenceFile::query()->create([
                'evidence_id' => $evidence->id,
                'folder_id' => $folder->id,
                'uploaded_by' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'extension' => $extension,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'antivirus_status' => config('siget.antivirus_enabled')
                    ? 'PENDING'
                    : 'SKIPPED',
                'version' => $evidence->current_version,
                'metadata' => [
                    'organizational_unit_id' => $deliverable->organizational_unit_id,
                    'repository_folder_id' => $folder->id,
                    'repository_path' => $folder->path_key,
                    'qa_upload' => app()->environment('local'),
                ],
            ]);

            $evidence->update(['folder_id' => $folder->id]);
            $deliverable->update([
                'status' => $evidence->current_version > 1
                    ? 'CORREGIDO'
                    : 'EN_CAPTURA',
            ]);

            $this->progress->recalculate($deliverable->scheduledLoad);

            $this->audit->record(
                'evidence.file.uploaded',
                $evidence,
                [],
                [
                    'deliverable_id' => $deliverable->id,
                    'filename' => $file->getClientOriginalName(),
                    'version' => $evidence->current_version,
                    'organizational_unit_id' => $deliverable->organizational_unit_id,
                    'repository_folder_id' => $folder->id,
                    'repository_path' => $folder->path_key,
                ]
            );

            return $evidence->fresh(['files', 'deliverable']);
        });
    }
}
