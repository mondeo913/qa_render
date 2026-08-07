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
