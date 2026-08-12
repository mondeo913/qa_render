<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendar_import_rows')) {
            return;
        }

        // QaDemoSeeder uses source_column when creating calendar import rows.
        // This migration must be self-contained because it runs before any
        // later 2026-08-12 schema repair migration on a fresh/pending QA DB.
        if (! Schema::hasColumn('calendar_import_rows', 'source_column')) {
            Schema::table('calendar_import_rows', function (Blueprint $table): void {
                $table->string('source_column', 50)->nullable()->after('row_number');
            });
        }

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
                throw new \RuntimeException("No se pudo ejecutar {$seeder} durante el bootstrap QA.");
            }
        }

        if (! DB::table('scheduled_loads')->exists()) {
            throw new \RuntimeException('El bootstrap QA terminó sin crear cargas programadas.');
        }
    }

    public function down(): void
    {
        // QA demo data is intentionally preserved; this migration only repairs
        // the missing bootstrap path and does not own the demo records.
    }
};
