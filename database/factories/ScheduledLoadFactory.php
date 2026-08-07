<?php
namespace Database\Factories;
use App\Models\CalendarImport;
use App\Models\CalendarImportRow;
use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use App\Models\ScheduledLoad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledLoadFactory extends Factory {
    protected $model=ScheduledLoad::class;
    public function definition(): array {
        $agency=ContractingAgency::factory()->create();
        $user=User::factory()->create(['contracting_agency_id'=>$agency->id]);
        $import=CalendarImport::factory()->create([
            'contracting_agency_id'=>$agency->id,
            'uploaded_by'=>$user->id,
        ]);
        $row=CalendarImportRow::factory()->create(['calendar_import_id'=>$import->id]);
        $template=EvidenceTemplate::factory()->create(['contracting_agency_id'=>$agency->id]);
        return [
            'calendar_import_id'=>$import->id,
            'calendar_import_row_id'=>$row->id,
            'contracting_agency_id'=>$agency->id,
            'template_id'=>$template->id,
            'title'=>'Carga de prueba','period_label'=>'2026',
            'original_open_at'=>'2026-07-01 08:00:00','original_close_at'=>'2026-07-01 18:00:00',
            'effective_open_at'=>'2026-07-01 08:00:00','effective_close_at'=>'2026-07-01 18:00:00',
            'status'=>'PROGRAMADA','traffic_light'=>'GRAY','is_blocked'=>false
        ];
    }
}