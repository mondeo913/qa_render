<?php

namespace Tests\Feature;

use App\Services\HorizontalPautaParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HorizontalPautaParserTest extends TestCase
{
    public function test_it_detects_month_day_and_x_marks(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TELEVISION');
        $sheet->setCellValue('O6', 'JULIO');
        $sheet->setCellValue('O7', 1);
        $sheet->setCellValue('P7', 2);
        $sheet->setCellValue('Q7', 3);
        $sheet->setCellValue('A9', 'Campaña QA');
        $sheet->setCellValue('G9', 'CANAL CATORCE');
        $sheet->setCellValue('I9', 'PUEBLA');
        $sheet->setCellValue('P9', 'X');

        $path = tempnam(sys_get_temp_dir(), 'siget-pauta-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $items = app(HorizontalPautaParser::class)->parse($path, 2026);

        $this->assertCount(1, $items);
        $this->assertSame('P', $items[0]['source_column']);
        $this->assertSame('2026-07-02', $items[0]['date']->toDateString());
        $this->assertSame('Campaña QA', $items[0]['metadata']['campana']);

        @unlink($path);
    }
    public function test_headers_after_row_thirty_are_supported(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('O40', 'julio');
        foreach (['O' => 1, 'P' => 2, 'Q' => 3] as $column => $day) {
            $sheet->setCellValue($column.'41', $day);
        }
        $sheet->setCellValue('A43', 'Campaña');
        $sheet->setCellValue('P43', 'X');
        $path = tempnam(sys_get_temp_dir(), 'pauta-');
        try {
            (new Xlsx($book))->save($path);
            $items = app(HorizontalPautaParser::class)->parse($path, 2026);
            $this->assertCount(1, $items);
            $this->assertSame('2026-07-02', $items[0]['date']->toDateString());
        } finally { unlink($path); }
    }

    public function test_invalid_headers_raise_a_correctable_workflow_error(): void
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Portada');
        $path = tempnam(sys_get_temp_dir(), 'pauta-');
        try {
            (new Xlsx($book))->save($path);
            $this->expectException(\App\Exceptions\WorkflowException::class);
            $this->expectExceptionMessage('Hoja «Portada»');
            app(HorizontalPautaParser::class)->parse($path, 2026);
        } finally { unlink($path); }
    }

    public function test_filename_dates_override_horizontal_marks_and_preserve_traceability(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('CC8', 'Nombre del Archivo para bitácora');
        $sheet->setCellValue('A9', 'Campaña');
        $sheet->setCellValue('CC9', '01_XHSPRAG_26.07.07.TESTIGO.C14_23.25.28.mp4');
        $sheet->setCellValue('CC10', '02_XHSPRAG_26.08.21.TESTIGO.C14.MP4');
        $sheet->setCellValue('O6', 'JULIO');
        foreach (['O' => 1, 'P' => 2, 'Q' => 3] as $col => $day) $sheet->setCellValue($col.'7', $day);
        $sheet->setCellValue('O9', 'X');
        $path = tempnam(sys_get_temp_dir(), 'pauta-');
        try {
            (new Xlsx($book))->save($path);
            $items = app(HorizontalPautaParser::class)->parse($path, 2026);
            $this->assertCount(2, $items);
            $this->assertSame('2026-07-07', $items[0]['date']->toDateString());
            $this->assertSame('2026-08-21', $items[1]['date']->toDateString());
            $this->assertSame('CC', $items[0]['source_column']);
            $this->assertSame('BITACORA_FILENAME', $items[0]['format']);
            $this->assertSame(9, $items[0]['row_number']);
            foreach (['01_X_26.02.30.TESTIGO.C.mp4', '01_X_25.07.07.TESTIGO.C.mp4', 'sin_fecha.mp4', ''] as $invalid) {
                $sheet->setCellValue('CC10', $invalid);
                $sheet->setCellValue('A10', 'Campaña');
                (new Xlsx($book))->save($path);
                try {
                    app(HorizontalPautaParser::class)->parse($path, 2026);
                    $this->fail('Invalid filename accepted: '.$invalid);
                } catch (\App\Exceptions\WorkflowException $exception) {
                    $this->assertStringContainsString('CC10', $exception->getMessage());
                }
            }
        } finally { unlink($path); }
    }

    public function test_it_parses_the_included_qa_sample(): void
    {
        $path = public_path('samples/Pauta_Bienestar_QA.xlsx');
        $items = app(HorizontalPautaParser::class)->parse($path, 2026);

        $this->assertCount(13, $items);
        $this->assertSame('2026-07-07', $items[0]['date']->toDateString());
        $this->assertSame('U', $items[0]['source_column']);
        $this->assertSame('Programas de Bienestar', $items[0]['metadata']['campana']);
    }

}
