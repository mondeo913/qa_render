<?php

namespace Database\Seeders;

use App\Models\CalendarSuspension;
use Illuminate\Database\Seeder;

class CalendarSuspensionSeeder extends Seeder
{
    public function run(): void
    {
        CalendarSuspension::query()->updateOrCreate(
            ['name'=>'Suspensión institucional agosto-septiembre 2026'],
            [
                'description'=>'Todas las pautas del 25 de agosto al 8 de septiembre de 2026 quedan inhabilitadas y se reprograman de forma retroactiva después del 8 de septiembre.',
                'starts_at'=>'2026-08-25 00:00:00',
                'ends_at'=>'2026-09-08 23:59:59',
                'applies_to_all_agencies'=>true,
                'block_upload'=>true,
                'exclude_from_compliance'=>true,
                'active'=>true,
            ]
        );
    }
}
