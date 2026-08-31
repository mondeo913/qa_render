<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CalendarImportService
{
    public function __construct(
        private readonly HorizontalPautaParser $horizontalParser,
        private readonly SuspensionService $suspensions,
        private readonly RepositoryService $repository,
        private readonly AuditService $audit
    ) {}

    public function uploadAndValidate(
        UploadedFile $file,
        int $agencyId,
        int $userId,
        int $scheduleYear
    ): CalendarImport {
        $agency = ContractingAgency::query()
            ->whereKey($agencyId)
            ->where('active', true)
            ->firstOrFail();

        $disk = config('filesystems.default');
        $path = $file->store('siget/calendar-imports', $disk);
        $absolutePath = Storage::disk($disk)->path($path);
        $hash = hash_file('sha256', $absolutePath);

        $import = CalendarImport::query()->create([
            'contracting_agency_id' => $agency->id,
            'uploaded_by' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'sha256' => $hash,
            'workbook_version' => 'HORIZONTAL-X-'.$scheduleYear,
            'status' => 'VALIDATING',
        ]);

        try {
            $items = $this->horizontalParser->parse($absolutePath, $scheduleYear);
            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->where('active', true)
                ->latest('version')
                ->first();

            if (!$template) {
                throw new RuntimeException(
                    'La dependencia no tiene una plantilla PAUTA_MENSUAL activa.'
                );
            }

            foreach ($items as $item) {
                CalendarImportRow::query()->create([
                    'calendar_import_id' => $import->id,
                    'sheet_name' => $item['sheet_name'],
                    'row_number' => $item['row_number'],
                    'source_column' => $item['source_column'],
                    'contracting_agency_code' => $agency->code,
                    'organizational_unit_code' => 'MULTI',
                    'template_code' => $template->code,
                    'original_open_at' => $item['date']->startOfDay()->addHours(8),
                    'original_close_at' => $item['date']->endOfDay(),
                    'delivery_name' => $item['delivery_name'],
                    'payload' => [
                        'format' => 'HORIZONTAL_X',
                        'schedule_year' => $scheduleYear,
                        'source_column' => $item['source_column'],
                        'service' => $item['metadata'],
                    ],
                    'is_valid' => true,
                    'validation_messages' => [],
                ]);
            }

            $import->update([
                'status' => 'VALIDATED',
                'total_rows' => count($items),
                'valid_rows' => count($items),
                'error_rows' => 0,
                'warnings' => [
                    'Las marcas X de una misma fecha se agruparán en una sola carga.',
                    'Cada carga generará los entregables configurados para las dos direcciones.',
                ],
            ]);

            $this->audit->record('calendar.import.horizontal_validated', $import, [], [
                'items' => count($items),
                'year' => $scheduleYear,
                'sha256' => $hash,
            ]);

            return $import->fresh('rows');
        } catch (\Throwable $exception) {
            $import->update([
                'status' => 'WITH_ERRORS',
                'errors' => [$exception->getMessage()],
            ]);

            throw $exception;
        }
    }

    public function confirm(CalendarImport $import, int $userId): int
    {
        if ($import->error_rows > 0 || $import->status !== 'VALIDATED') {
            throw new RuntimeException(
                'La importación contiene errores o no está validada.'
            );
        }

        return DB::transaction(function () use ($import, $userId) {
            $agency = ContractingAgency::query()
                ->findOrFail($import->contracting_agency_id);

            $template = EvidenceTemplate::query()
                ->where('contracting_agency_id', $agency->id)
                ->where('code', 'PAUTA_MENSUAL')
                ->where('active', true)
                ->latest('version')
                ->with('requirements')
                ->firstOrFail();

            $groups = $import->rows()
                ->where('is_valid', true)
                ->orderBy('original_open_at')
                ->get()
                ->groupBy(fn (CalendarImportRow $row) =>
                    $row->original_open_at->toDateString()
                );

            $created = 0;

            foreach ($groups as $date => $rows) {
                $dateObject = CarbonImmutable::parse($date, config('app.timezone'));
                $serviceItems = $rows->map(fn (CalendarImportRow $row) => [
                    'sheet' => $row->sheet_name,
                    'row' => $row->row_number,
                    'column' => $row->source_column,
                    'name' => $row->delivery_name,
                    'service' => $row->payload['service'] ?? [],
                ])->values()->all();

                $load = ScheduledLoad::query()->create([
                    'calendar_import_id' => $import->id,
                    'calendar_import_row_id' => $rows->first()->id,
                    'contracting_agency_id' => $agency->id,
                    'template_id' => $template->id,
                    'title' => sprintf(
                        'Pauta TV %s · %d servicio%s',
                        $dateObject->format('d/m/Y'),
                        count($serviceItems),
                        count($serviceItems) === 1 ? '' : 's'
                    ),
                    'period_label' => ucfirst($dateObject->translatedFormat('F Y')),
                    'original_open_at' => $dateObject->startOfDay()->addHours(8),
                    'original_close_at' => $dateObject->endOfDay(),
                    'effective_open_at' => $dateObject->startOfDay()->addHours(8),
                    'effective_close_at' => $dateObject->endOfDay(),
                    'status' => 'PROGRAMADA',
                    'traffic_light' => 'GRAY',
                    'priority' => count($serviceItems) >= 5 ? 'ALTA' : 'NORMAL',
                    'completion_percentage' => 0,
                    'metadata' => [
                        'format' => 'HORIZONTAL_X',
                        'source_sha256' => $import->sha256,
                        'source_rows' => $rows->pluck('id')->all(),
                        'service_count' => count($serviceItems),
                        'services' => $serviceItems,
                    ],
                ]);

                foreach ($template->requirements()->where('active', true)->get() as $requirement) {
                    if (!$requirement->responsible_unit_id) {
                        throw new RuntimeException(
                            'Cada requisito debe tener una dirección responsable.'
                        );
                    }

                    $operatorId = User::query()
                        ->where(function ($query) use ($agency) {
                            $query->where('contracting_agency_id', $agency->id)
                                ->orWhereNull('contracting_agency_id');
                        })
                        ->where('organizational_unit_id', $requirement->responsible_unit_id)
                        ->whereHas('role', fn ($role) => $role->whereIn(
                            'code',
                            RoleCode::operatorValues()
                        ))
                        ->where('status', 'ACTIVE')
                        ->orderByRaw('CASE WHEN contracting_agency_id = ? THEN 0 ELSE 1 END', [$agency->id])
                        ->value('id');

                    ScheduledLoadDeliverable::query()->create([
                        'scheduled_load_id' => $load->id,
                        'template_requirement_id' => $requirement->id,
                        'organizational_unit_id' => $requirement->responsible_unit_id,
                        'responsible_user_id' => $operatorId,
                        'status' => 'PENDIENTE',
                        'due_at' => $load->effective_close_at,
                    ]);
                }

                $this->repository->createLoadTree($load, $userId);
                $this->suspensions->applyToLoad($load);
                $created++;
            }

            $import->update([
                'status' => 'CONFIRMED',
                'confirmed_at' => now(),
            ]);

            $this->audit->record('calendar.import.confirmed', $import, [], [
                'created_scheduled_loads' => $created,
                'source_marks' => $import->valid_rows,
            ]);

            return $created;
        });
    }
}
