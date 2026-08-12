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

        // The QA seeder may run before later repair migrations on a pending DB.
        // Keep this bootstrap self-contained so it can safely initialize a
        // schema that predates the fields used by the current models/seeders.
        Schema::table('calendar_import_rows', function (Blueprint $table): void {
            if (! Schema::hasColumn('calendar_import_rows', 'source_column')) {
                $table->string('source_column', 50)->nullable()->after('row_number');
            }
        });

        if (!Schema::hasTable('scheduled_loads')) {
            return;
        }

        Schema::table('scheduled_loads', function (Blueprint $table): void {
            if (! Schema::hasColumn('scheduled_loads', 'priority')) {
                $table->string('priority', 20)->default('NORMAL')->after('retroactive');
            }
            if (! Schema::hasColumn('scheduled_loads', 'completion_percentage')) {
                $table->decimal('completion_percentage', 5, 2)->default(0)->after('priority');
            }
        });

        // The bootstrap must only seed once, but schema repair above must run
        // even when demo data already exists.
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