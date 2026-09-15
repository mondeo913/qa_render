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

        // Repair fields required by the current QA universe.
        if (!Schema::hasColumn('calendar_import_rows', 'source_column')) {
            Schema::table('calendar_import_rows', function (Blueprint $table): void {
                $table->string('source_column', 50)->nullable()->after('row_number');
            });
        }

        if (!Schema::hasTable('scheduled_loads')) {
            return;
        }

        if (!Schema::hasColumn('scheduled_loads', 'priority')) {
            Schema::table('scheduled_loads', function (Blueprint $table): void {
                $table->string('priority', 20)->default('NORMAL')->after('retroactive');
            });
        }
        if (!Schema::hasColumn('scheduled_loads', 'completion_percentage')) {
            Schema::table('scheduled_loads', function (Blueprint $table): void {
                $table->decimal('completion_percentage', 5, 2)->default(0)->after('priority');
            });
        }

        if (!Schema::hasTable('review_assignments')) {
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

        if (Schema::hasTable('evidences') && !Schema::hasColumn('evidences', 'revision_number')) {
            Schema::table('evidences', function (Blueprint $table): void {
                $table->unsignedInteger('revision_number')->default(1)->after('current_version');
            });
        }

        if (Schema::hasTable('evidence_reviews') && !Schema::hasColumn('evidence_reviews', 'review_type')) {
            Schema::table('evidence_reviews', function (Blueprint $table): void {
                $table->string('review_type', 40)->default('INSTITUTIONAL')->after('comments');
            });
        }

        // On an existing QA database, the operator can explicitly run
        // QaUniverseSeeder. Do not invoke demo seeders from a migration.
    }

    public function down(): void
    {
        // Schema repair fields are intentionally retained.
    }
};
