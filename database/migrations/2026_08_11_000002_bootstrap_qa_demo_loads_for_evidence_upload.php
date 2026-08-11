<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('scheduled_loads')) {
            return;
        }

        // The Codespaces QA bootstrap previously created roles, units and users,
        // but did not create scheduled loads. Without loads, "Mis cargas" could
        // render the evidence section while offering no file input at all.
        if (DB::table('scheduled_loads')->exists()) {
            return;
        }

        foreach (['RolePermissionSeeder', 'AgencyTemplateSeeder', 'QaDemoSeeder'] as $seeder) {
            if (Artisan::call('db:seed', [
                '--class' => $seeder,
                '--force' => true,
            ]) !== 0) {
                throw new RuntimeException("No se pudo ejecutar {$seeder} durante el bootstrap QA.");
            }
        }

        if (! DB::table('scheduled_loads')->exists()) {
            throw new RuntimeException('El bootstrap QA terminó sin crear cargas programadas.');
        }
    }

    public function down(): void
    {
        // QA demo data is intentionally preserved; this migration only repairs
        // the missing bootstrap path and does not own the demo records.
    }
};
