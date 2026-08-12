<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration can run after the QA evidence bootstrap, which may
        // have already repaired fields required by QaDemoSeeder. Keep every
        // schema change idempotent so those repairs do not cause duplicates.
        Schema::table('calendar_import_rows', function (Blueprint $table) {
            $table->dropUnique('import_row_unique');
            if (! Schema::hasColumn('calendar_import_rows', 'source_column')) {
                $table->string('source_column', 12)->nullable()->after('row_number');
            }
            $table->unique(
                ['calendar_import_id', 'sheet_name', 'row_number', 'source_column'],
                'import_cell_unique'
            );
        });

        Schema::table('scheduled_loads', function (Blueprint $table) {
            if (! Schema::hasColumn('scheduled_loads', 'priority')) {
                $table->string('priority', 20)->default('NORMAL')->index();
            }
            if (! Schema::hasColumn('scheduled_loads', 'completion_percentage')) {
                $table->decimal('completion_percentage', 5, 2)->default(0);
            }
            if (! Schema::hasColumn('scheduled_loads', 'accounting_notified_at')) {
                $table->timestampTz('accounting_notified_at')->nullable();
            }
        });

        Schema::table('evidences', function (Blueprint $table) {
            if (! Schema::hasColumn('evidences', 'revision_number')) {
                $table->unsignedInteger('revision_number')->default(1);
            }
        });

        Schema::table('evidence_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('evidence_reviews', 'review_type')) {
                $table->string('review_type', 30)->default('TECHNICAL')->index();
            }
        });

        if (! Schema::hasTable('review_assignments')) {
            Schema::create('review_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
                $table->foreignId('fiscalizador_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by')->constrained('users');
                $table->boolean('active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestampsTz();
                $table->unique(
                    ['scheduled_load_id', 'fiscalizador_id'],
                    'review_assignment_unique'
                );
            });
        }

        if (! Schema::hasTable('accounting_notices')) {
            Schema::create('accounting_notices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scheduled_load_id')->unique()->constrained()->cascadeOnDelete();
                $table->json('recipients');
                $table->string('status', 20)->default('PENDING')->index();
                $table->timestampTz('sent_at')->nullable();
                $table->json('payload')->nullable();
                $table->text('failure_message')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('report_exports')) {
            Schema::create('report_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requested_by')->constrained('users');
                $table->string('report_type', 80);
                $table->string('format', 20);
                $table->json('filters')->nullable();
                $table->string('status', 20)->default('GENERATED');
                $table->string('storage_path', 900)->nullable();
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('accounting_notices');
        Schema::dropIfExists('review_assignments');

        if (Schema::hasColumn('evidence_reviews', 'review_type')) {
            Schema::table('evidence_reviews', function (Blueprint $table) {
                $table->dropColumn('review_type');
            });
        }

        if (Schema::hasColumn('evidences', 'revision_number')) {
            Schema::table('evidences', function (Blueprint $table) {
                $table->dropColumn('revision_number');
            });
        }

        Schema::table('scheduled_loads', function (Blueprint $table) {
            foreach (['priority', 'completion_percentage', 'accounting_notified_at'] as $column) {
                if (Schema::hasColumn('scheduled_loads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('calendar_import_rows')) {
            Schema::table('calendar_import_rows', function (Blueprint $table) {
                $table->dropUnique('import_cell_unique');
                if (Schema::hasColumn('calendar_import_rows', 'source_column')) {
                    $table->dropColumn('source_column');
                }
                $table->unique(
                    ['calendar_import_id', 'sheet_name', 'row_number'],
                    'import_row_unique'
                );
            });
        }
    }
};