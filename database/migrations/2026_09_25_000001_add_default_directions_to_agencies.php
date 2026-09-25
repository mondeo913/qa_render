<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('contracting_agencies') || ! Schema::hasTable('organizational_units')) {
            return;
        }

        $now = now();
        $directions = [
            'DIR_A' => 'Dirección de Transmisión',
            'DIR_B' => 'Dirección de Programación y Continuidad',
        ];

        DB::table('contracting_agencies')->orderBy('id')->each(function (object $agency) use ($directions, $now): void {
            foreach ($directions as $code => $name) {
                DB::table('organizational_units')->updateOrInsert(
                    [
                        'contracting_agency_id' => $agency->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'unit_type' => 'DIRECTION',
                        'active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizational_units')) {
            return;
        }

        DB::table('organizational_units')
            ->whereIn('code', ['DIR_A', 'DIR_B'])
            ->where('unit_type', 'DIRECTION')
            ->delete();
    }
};
