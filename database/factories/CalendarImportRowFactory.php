<?php
namespace Database\Factories;
use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;
class CalendarImportRowFactory extends Factory {
    protected $model=CalendarImportRow::class;
    public function definition(): array {
        return [
            'calendar_import_id'=>CalendarImport::factory(),
            'sheet_name'=>'Pautas','row_number'=>2,
            'original_open_at'=>'2026-07-01 08:00:00',
            'original_close_at'=>'2026-07-01 18:00:00',
            'delivery_name'=>'Entrega de prueba','is_valid'=>true
        ];
    }
}
