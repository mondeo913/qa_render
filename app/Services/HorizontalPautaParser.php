<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Exceptions\WorkflowException as RuntimeException;

final class HorizontalPautaParser
{
    private const MONTHS = [
        'ENERO' => 1,
        'FEBRERO' => 2,
        'MARZO' => 3,
        'ABRIL' => 4,
        'MAYO' => 5,
        'JUNIO' => 6,
        'JULIO' => 7,
        'AGOSTO' => 8,
        'SEPTIEMBRE' => 9,
        'SETIEMBRE' => 9,
        'OCTUBRE' => 10,
        'NOVIEMBRE' => 11,
        'DICIEMBRE' => 12,
    ];

    public function parse(string $absolutePath, int $year): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $items = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumnIndex = Coordinate::columnIndexFromString(
                $worksheet->getHighestDataColumn()
            );

            $filenameHeader = $this->detectFilenameHeader($worksheet, $highestRow, $highestColumnIndex);
            if ($filenameHeader !== null) {
                $items = array_merge($items, $this->parseFilenames($worksheet, $filenameHeader, $highestRow, $year));
                continue;
            }

            [$monthRow, $dayRow] = $this->detectHeaderRows(
                $worksheet,
                $highestRow,
                $highestColumnIndex
            );

            $columnDates = $this->buildColumnDates(
                $worksheet,
                $monthRow,
                $dayRow,
                $highestColumnIndex,
                $year
            );

            if ($columnDates === []) {
                continue;
            }

            $firstDateColumn = min(array_keys($columnDates));
            $dataStartRow = $this->detectDataStartRow(
                $worksheet,
                $dayRow,
                $highestRow,
                array_keys($columnDates)
            );

            for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                $metadata = $this->readMetadata($worksheet, $row);

                if (!$this->hasMeaningfulMetadata($metadata)) {
                    continue;
                }

                foreach ($columnDates as $column => $date) {
                    $mark = $this->normalizeText((string) $worksheet
                        ->getCell([$column, $row])
                        ->getCalculatedValue());

                    if (!in_array($mark, ['X', '1', 'SI'], true)) {
                        continue;
                    }

                    $items[] = [
                        'sheet_name' => $worksheet->getTitle(),
                        'row_number' => $row,
                        'source_column' => Coordinate::stringFromColumnIndex($column),
                        'date' => $date,
                        'metadata' => $metadata,
                        'delivery_name' => $this->deliveryName($metadata, $date),
                        'first_date_column' => $firstDateColumn,
                    ];
                }
            }
        }

        if ($items === []) {
            throw new RuntimeException(
                'No se encontraron marcas X asociadas a meses y días en el archivo.'
            );
        }

        return $items;
    }

    private function detectFilenameHeader(object $worksheet, int $rows, int $columns): ?array
    {
        $headers = [];
        for ($row = 1; $row <= min($rows, 100); $row++) {
            for ($column = 1; $column <= $columns; $column++) {
                $label = \Illuminate\Support\Str::ascii(mb_strtolower((string) $worksheet->getCell([$column, $row])->getCalculatedValue()));
                $label = preg_replace('/[^a-z]/', '', $label);
                if (in_array($label, ['nombredelarchivoparabitacora', 'nombredearchivoparabitacora'], true)) {
                    $headers[] = [$row, $column];
                }
            }
        }
        if (count($headers) > 1) {
            throw new RuntimeException('Hoja «'.$worksheet->getTitle().'»: hay varias columnas de nombre de archivo para bitácora. Conserve un solo encabezado.');
        }
        return $headers[0] ?? null;
    }

    private function parseFilenames(object $worksheet, array $header, int $rows, int $year): array
    {
        [$headerRow, $column] = $header;
        $items = [];
        for ($row = $headerRow + 1; $row <= $rows; $row++) {
            $filename = trim((string) $worksheet->getCell([$column, $row])->getCalculatedValue());
            $metadata = $this->readMetadata($worksheet, $row);
            $cell = Coordinate::stringFromColumnIndex($column).$row;
            $location = 'Hoja «'.$worksheet->getTitle().'», celda '.$cell.': ';
            if ($filename === '') {
                if ($metadata['campana'] !== '' || $metadata['canal'] !== '' || $metadata['plaza'] !== '') {
                    throw new RuntimeException($location.'falta el nombre del archivo para bitácora.');
                }
                continue;
            }
            // Exigir el segmento de fecha anterior a TESTIGO, nunca la hora final.
            if (!preg_match('/_(\d{2})\.(\d{2})\.(\d{2})\.TESTIGO[._]/i', $filename, $match)) {
                throw new RuntimeException($location.'el nombre debe contener _AA.MM.DD.TESTIGO (por ejemplo, _26.07.07.TESTIGO).');
            }
            $fileYear = 2000 + (int) $match[1];
            $month = (int) $match[2];
            $day = (int) $match[3];
            if (!checkdate($month, $day, $fileYear)) {
                throw new RuntimeException($location.'el nombre contiene una fecha inexistente.');
            }
            if ($fileYear !== $year) {
                throw new RuntimeException($location.'el año del archivo ('.$fileYear.') no coincide con el año seleccionado ('.$year.').');
            }
            $date = CarbonImmutable::create($fileYear, $month, $day, 8, 0, 0, config('app.timezone'));
            $metadata['nombre_archivo_bitacora'] = $filename;
            $items[] = [
                'sheet_name' => $worksheet->getTitle(), 'row_number' => $row,
                'source_column' => Coordinate::stringFromColumnIndex($column),
                'date' => $date, 'metadata' => $metadata,
                'delivery_name' => $this->deliveryName($metadata, $date),
                'first_date_column' => $column, 'format' => 'BITACORA_FILENAME',
            ];
        }
        if ($items === []) {
            throw new RuntimeException('Hoja «'.$worksheet->getTitle().'»: la columna de archivos para bitácora no contiene registros.');
        }
        return $items;
    }

    private function detectHeaderRows(
        object $worksheet,
        int $highestRow,
        int $highestColumnIndex
    ): array {
        $searchLimit = min($highestRow, 100);
        $monthRows = [];
        $dayRows = [];

        for ($row = 1; $row <= $searchLimit; $row++) {
            $monthsFound = 0;
            $daysFound = 0;

            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = trim((string) $worksheet
                    ->getCell([$column, $row])
                    ->getCalculatedValue());
                $normalized = $this->normalizeText($value);

                if (isset(self::MONTHS[$normalized])) {
                    $monthsFound++;
                }

                if ($this->isDayValue($value)) {
                    $daysFound++;
                }
            }

            if ($monthsFound > 0) {
                $monthRows[$row] = $monthsFound;
            }

            // Tres días son suficientes para pautas parciales y archivos de prueba.
            if ($daysFound >= 3) {
                $dayRows[$row] = $daysFound;
            }
        }

        $bestPair = null;
        $bestScore = -1;

        foreach ($monthRows as $monthRow => $monthsFound) {
            foreach ($dayRows as $dayRow => $daysFound) {
                $distance = $dayRow - $monthRow;

                if ($distance < 0 || $distance > 3) {
                    continue;
                }

                $score = ($monthsFound * 100) + $daysFound - ($distance * 5);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestPair = [$monthRow, $dayRow];
                }
            }
        }

        if ($bestPair === null) {
            throw new RuntimeException(
                'Hoja «'.$worksheet->getTitle().'»: no fue posible identificar los encabezados. Se requiere una fila con meses completos (por ejemplo, ENERO) y, hasta tres filas debajo, al menos tres días enteros entre 1 y 31, dentro de las primeras 100 filas. Revise también las hojas auxiliares del archivo.'
            );
        }

        return $bestPair;
    }

    private function buildColumnDates(
        object $worksheet,
        int $monthRow,
        int $dayRow,
        int $highestColumnIndex,
        int $year
    ): array {
        $dates = [];
        $currentMonth = null;

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $monthValue = $this->normalizeText((string) $worksheet
                ->getCell([$column, $monthRow])
                ->getCalculatedValue());

            if (isset(self::MONTHS[$monthValue])) {
                $currentMonth = self::MONTHS[$monthValue];
            }

            $dayValue = $worksheet
                ->getCell([$column, $dayRow])
                ->getCalculatedValue();

            if (
                $currentMonth !== null
                && $this->isDayValue($dayValue)
                && checkdate($currentMonth, (int) $dayValue, $year)
            ) {
                $dates[$column] = CarbonImmutable::create(
                    $year,
                    $currentMonth,
                    (int) $dayValue,
                    8,
                    0,
                    0,
                    config('app.timezone')
                );
            }
        }

        return $dates;
    }

    private function detectDataStartRow(
        object $worksheet,
        int $dayRow,
        int $highestRow,
        array $dateColumns
    ): int {
        for ($row = $dayRow + 1; $row <= min($highestRow, $dayRow + 8); $row++) {
            $metadata = $this->readMetadata($worksheet, $row);

            if (!$this->hasMeaningfulMetadata($metadata)) {
                continue;
            }

            foreach ($dateColumns as $column) {
                $mark = $this->normalizeText((string) $worksheet
                    ->getCell([$column, $row])
                    ->getCalculatedValue());

                if (in_array($mark, ['X', '1', 'SI'], true)) {
                    return $row;
                }
            }
        }

        // Compatibilidad con el formato institucional: una fila de días de la semana.
        return min($highestRow, $dayRow + 2);
    }

    private function readMetadata(object $worksheet, int $row): array
    {
        $labels = [
            1 => 'campana',
            2 => 'version',
            3 => 'cobertura_principal',
            4 => 'cadena_televisora',
            5 => 'cobertura',
            6 => 'siglas',
            7 => 'canal',
            8 => 'espacio_programatico',
            9 => 'plaza',
            10 => 'canal_repetidor',
            11 => 'siglas_repetidor',
            12 => 'contenido',
            13 => 'franja_horaria',
            14 => 'formato_duracion',
        ];

        $metadata = [];

        foreach ($labels as $column => $key) {
            $metadata[$key] = trim((string) $worksheet
                ->getCell([$column, $row])
                ->getCalculatedValue());
        }

        return $metadata;
    }

    private function hasMeaningfulMetadata(array $metadata): bool
    {
        return collect($metadata)
            ->filter(fn ($value) => $value !== '')
            ->isNotEmpty();
    }

    private function deliveryName(array $metadata, CarbonImmutable $date): string
    {
        $parts = array_filter([
            $metadata['campana'] ?: 'Pauta TV',
            $metadata['canal'] ?: null,
            $metadata['plaza'] ?: null,
        ]);

        return implode(' · ', $parts).' · '.$date->format('d/m/Y');
    }

    private function isDayValue(mixed $value): bool
    {
        return is_numeric($value)
            && (float) $value === (float) (int) $value
            && (int) $value >= 1
            && (int) $value <= 31;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtoupper(
            str_replace(
                ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'],
                ['A', 'E', 'I', 'O', 'U', 'U'],
                $value
            )
        );
    }
}
