<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_import_rows', function (Blueprint $table) {
            $table->dropUnique('import_row_unique');
            $table->string('source_column', 12)->nullable()->after('row_number');
            $table->unique(
                ['calendar_import_id', 'sheet_name', 'row_number', 'source_column'],
                'import_cell_unique'
            );
        });

        Schema::table('scheduled_loads', function (Blueprint $table) {
            $table->string('priority', 20)->default('NORMAL')->index();
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestampTz('accounting_notified_at')->nullable();
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->unsignedInteger('revision_number')->default(1);
        });

        Schema::table('evidence_reviews', function (Blueprint $table) {
            $table->string('review_type', 30)->default('TECHNICAL')->index();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('accounting_notices');
        Schema::dropIfExists('review_assignments');

        Schema::table('evidence_reviews', function (Blueprint $table) {
            $table->dropColumn('review_type');
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn('revision_number');
        });

        Schema::table('scheduled_loads', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'completion_percentage',
                'accounting_notified_at',
            ]);
        });

        Schema::table('calendar_import_rows', function (Blueprint $table) {
            $table->dropUnique('import_cell_unique');
            $table->dropColumn('source_column');
            $table->unique(
                ['calendar_import_id', 'sheet_name', 'row_number'],
                'import_row_unique'
            );
        });
    }
};
