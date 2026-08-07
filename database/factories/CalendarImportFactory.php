<?php
namespace Database\Factories;
use App\Models\CalendarImport;
use App\Models\ContractingAgency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
class CalendarImportFactory extends Factory {
    protected $model=CalendarImport::class;
    public function definition(): array {
        return [
            'contracting_agency_id'=>ContractingAgency::factory(),
            'uploaded_by'=>User::factory(),
            'original_filename'=>'pautas.xlsx','storage_path'=>'test/pautas.xlsx',
            'sha256'=>hash('sha256','test'),'status'=>'CONFIRMED'
        ];
    }
}
