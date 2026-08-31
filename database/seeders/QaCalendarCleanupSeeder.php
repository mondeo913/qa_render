<?php

namespace Database\Seeders;

use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\ScheduledLoad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QaCalendarCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $agencyIds = ContractingAgency::query()
            ->whereIn('code', ['IMSS', 'IPAB'])
            ->pluck('id');

        if ($agencyIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($agencyIds): void {
            $imports = CalendarImport::query()
                ->whereIn('contracting_agency_id', $agencyIds)
                ->where('original_filename', 'like', 'Pauta_QA_%')
                ->pluck('id');

            if ($imports->isEmpty()) {
                return;
            }

            // Una versión anterior del seeder generó 48 filas y posteriormente
            // se redujo a 24. Las filas 25+ quedaron persistidas porque updateOrCreate()
            // no elimina registros históricos.
            $staleRowIds = CalendarImportRow::query()
                ->whereIn('calendar_import_id', $imports)
                ->where('row_number', '>', 24)
                ->pluck('id');

            if ($staleRowIds->isNotEmpty()) {
                ScheduledLoad::query()
                    ->whereIn('calendar_import_row_id', $staleRowIds)
                    ->delete();

                CalendarImportRow::query()
                    ->whereIn('id', $staleRowIds)
                    ->delete();
            }

            // Elimina cargas QA duplicadas que tengan exactamente la misma pauta,
            // dependencia, plantilla y ventana temporal; conserva la de menor ID.
            $duplicateIds = ScheduledLoad::query()
                ->select([
                    'contracting_agency_id',
                    'template_id',
                    'title',
                    'original_open_at',
                    'original_close_at',
                    DB::raw('MIN(id) AS keep_id'),
                ])
                ->whereIn('contracting_agency_id', $agencyIds)
                ->whereHas('calendarImport', fn ($query) => $query->where('original_filename', 'like', 'Pauta_QA_%'))
                ->groupBy([
                    'contracting_agency_id',
                    'template_id',
                    'title',
                    'original_open_at',
                    'original_close_at',
                ])
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->flatMap(function ($group) {
                    return ScheduledLoad::query()
                        ->where('contracting_agency_id', $group->contracting_agency_id)
                        ->where('template_id', $group->template_id)
                        ->where('title', $group->title)
                        ->where('original_open_at', $group->original_open_at)
                        ->where('original_close_at', $group->original_close_at)
                        ->where('id', '<>', $group->keep_id)
                        ->pluck('id');
                });

            if ($duplicateIds->isNotEmpty()) {
                ScheduledLoad::query()->whereIn('id', $duplicateIds)->delete();
            }
        });
    }
}
