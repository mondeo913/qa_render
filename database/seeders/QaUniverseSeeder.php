<?php

namespace Database\Seeders;

use App\Models\CalendarImport;
use App\Models\ContractingAgency;
use App\Models\ScheduledLoad;
use App\Services\CalendarImportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class QaUniverseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AgencyTemplateSeeder::class,
        ]);

        DB::transaction(function (): void {
            $agencies = ContractingAgency::query()
                ->whereIn('code', ['IMSS', 'IPAB'])
                ->with(['units', 'templates.requirements'])
                ->orderBy('id')
                ->get();

            if ($agencies->count() !== 2) {
                throw new \RuntimeException('El universo QA requiere las dependencias IMSS e IPAB.');
            }

            $this->cleanQaOperationalData();
            $users = $this->createQaUsers();
            $this->seedPautasThroughRealFlow($agencies, $users);
        });
    }

    private function cleanQaOperationalData(): void
    {
        // Primero identificamos las importaciones QA y sus cargas asociadas.
        // Así se limpian también universos QA antiguos que no tengan metadata.qa.
        $qaImportIds = CalendarImport::query()
            ->where('original_filename', 'like', 'Pauta_QA_%')
            ->pluck('id');

        if ($qaImportIds->isNotEmpty()) {
            ScheduledLoad::query()
                ->whereIn('calendar_import_id', $qaImportIds)
                ->delete();
        }

        ScheduledLoad::query()
            ->whereRaw("COALESCE(metadata->>'qa','false') = 'true'")
            ->delete();

        if ($qaImportIds->isNotEmpty()) {
            CalendarImport::query()->whereIn('id', $qaImportIds)->delete();
        }

    }

    private function createQaUsers(): array
    {
        return app(\App\Services\QaUserStandardService::class)->sync();
    }

    private function seedPautasThroughRealFlow($agencies, array $users): void
    {
        $importService = app(CalendarImportService::class);
        $admin = $users['admin@siget.local'];

        foreach ($agencies as $agency) {
            $workbook = $this->buildPautaWorkbook($agency->code, 2026);
            $tempPath = tempnam(sys_get_temp_dir(), 'siget-qa-pauta-').'.xlsx';

            try {
                (new Xlsx($workbook))->save($tempPath);

                $upload = new UploadedFile(
                    $tempPath,
                    "Pauta_QA_{$agency->code}_2026.xlsx",
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    UPLOAD_ERR_OK,
                    true
                );

                $import = $importService->uploadAndValidate($upload, $agency->id, $admin->id, 2026);
                $importService->confirm($import, $admin->id);
            } finally {
                @unlink($tempPath);
            }
        }
    }

    private function buildPautaWorkbook(string $agencyCode, int $year): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pauta QA');

        $months = [
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
        ];

        // Cinco fechas por dependencia; dos dependencias = 10 cargas totales.
        $targetDates = [
            [1, 15],
            [2, 19],
            [3, 16],
            [5, 14],
            [8, 18],
        ];

        $column = 15;
        $targetColumns = [];

        foreach ($months as $monthNumber => $monthName) {
            $daysInMonth = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;

            foreach (range(1, $daysInMonth) as $day) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->setCellValue($letter.'1', $monthName);
                $sheet->setCellValue($letter.'2', $day);

                if (in_array([$monthNumber, $day], $targetDates, true)) {
                    $targetColumns[] = $column;
                }

                $column++;
            }
        }

        $rows = [
            3 => [
                'Campaña institucional '.$agencyCode,
                'v1',
                'Nacional',
                'Canal Catorce',
                'Nacional',
                'SIGET',
                'Canal Catorce',
                'Barra institucional',
                'Nacional',
                '',
                '',
                'Contenido general',
                '08:00-23:59',
                'SPOT / 30 seg',
            ],
            4 => [
                'Campaña institucional '.$agencyCode,
                'v1',
                'Nacional',
                'Canal Catorce',
                'Nacional',
                'SIGET',
                'Canal Catorce',
                'Continuidad',
                'Nacional',
                '',
                '',
                'Promoción institucional',
                '08:00-23:59',
                'SPOT / 30 seg',
            ],
        ];

        foreach ($rows as $rowNumber => $values) {
            foreach ($values as $index => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $value);
            }
        }

        foreach ($targetColumns as $position => $targetColumn) {
            $letter = Coordinate::stringFromColumnIndex($targetColumn);
            $sheet->setCellValue($letter.'3', 'X');

            // Tres fechas tienen una segunda marca para probar la agrupación del importador.
            if (in_array($position, [1, 3, 4], true)) {
                $sheet->setCellValue($letter.'4', 'X');
            }
        }

        return $spreadsheet;
    }
}
