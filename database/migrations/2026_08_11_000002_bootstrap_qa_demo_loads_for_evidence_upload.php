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
        // schema that predates the fields/tables used by the current seeders.
        if (! Schema::hasColumn('calendar_import_rows', 'source_column')) {
            Schema::table('calendar_import_rows', function (Blueprint $table): void {
                $table->string('source_column', 50)->nullable()->after('row_number');
            });
        }

        if (!Schema::hasTable('scheduled_loads')) {
            return;
        }

        if (! Schema::hasColumn('scheduled_loads', 'priority')) {
            Schema::table('scheduled_loads', function (Blueprint $table): void {
                $table->string('priority', 20)->default('NORMAL')->after('retroactive');
            });
        }
        if (! Schema::hasColumn('scheduled_loads', 'completion_percentage')) {
            Schema::table('scheduled_loads', function (Blueprint $table): void {
                $table->decimal('completion_percentage', 5, 2)->default(0)->after('priority');
            });
        }

        // ReviewAssignment is used by QaDemoSeeder but the base migrations did
        // not create its table. Create it here before invoking the seeder.
        if (! Schema::hasTable('review_assignments')) {
            Schema::create('review_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('scheduled_load_id')->constrained('scheduled_loads')->cascadeOnDelete();
                $table->foreignId('fiscalizador_id')->constrained('users');
                $table->foreignId('assigned_by')->nullable()->constrained('users');
                $table->boolean('active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestampsTz();
                $table->unique(['scheduled_load_id', 'fiscalizador_id'], 'review_assignment_unique');
            });
        }

        // QaDemoSeeder also writes revision_number to evidences. The original
        // evidence repository migration predates that field, so repair it here
        // before invoking the seeder.
        if (Schema::hasTable('evidences') && ! Schema::hasColumn('evidences', 'revision_number')) {
            Schema::table('evidences', function (Blueprint $table): void {
                $table->unsignedInteger('revision_number')->default(1)->after('current_version');
            });
        }

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