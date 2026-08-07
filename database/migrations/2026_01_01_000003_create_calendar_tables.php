<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contracting_agency_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_filename');
            $table->string('storage_path', 700);
            $table->char('sha256', 64)->index();
            $table->string('workbook_version', 50)->nullable();
            $table->string('status', 30)->default('UPLOADED')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('calendar_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_import_id')->constrained()->cascadeOnDelete();
            $table->string('sheet_name', 120);
            $table->unsignedInteger('row_number');
            $table->string('contracting_agency_code', 50)->nullable();
            $table->string('organizational_unit_code', 70)->nullable();
            $table->string('template_code', 80)->nullable();
            $table->timestampTz('original_open_at')->nullable();
            $table->timestampTz('original_close_at')->nullable();
            $table->string('delivery_name', 240)->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_valid')->default(false);
            $table->json('validation_messages')->nullable();
            $table->timestampsTz();
            $table->unique(['calendar_import_id','sheet_name','row_number'], 'import_row_unique');
        });

        Schema::create('calendar_suspensions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 220)->unique();
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->boolean('applies_to_all_agencies')->default(true);
            $table->foreignId('contracting_agency_id')->nullable()->constrained();
            $table->boolean('block_upload')->default(true);
            $table->boolean('exclude_from_compliance')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        Schema::create('scheduled_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_import_id')->constrained();
            $table->foreignId('calendar_import_row_id')->constrained();
            $table->foreignId('contracting_agency_id')->constrained();
            $table->foreignId('template_id')->constrained('evidence_templates');
            $table->string('title', 260);
            $table->string('period_label', 120)->nullable();
            $table->timestampTz('original_open_at');
            $table->timestampTz('original_close_at');
            $table->timestampTz('effective_open_at');
            $table->timestampTz('effective_close_at');
            $table->string('status', 50)->default('PROGRAMADA')->index();
            $table->string('traffic_light', 20)->default('GRAY')->index();
            $table->boolean('is_blocked')->default(false);
            $table->text('block_reason')->nullable();
            $table->boolean('retroactive')->default(false);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->unsignedInteger('row_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['contracting_agency_id','effective_open_at','effective_close_at'], 'load_effective_window_idx');
        });

        Schema::create('load_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suspension_id')->nullable()->constrained('calendar_suspensions');
            $table->timestampTz('old_open_at');
            $table->timestampTz('old_close_at');
            $table->timestampTz('new_open_at')->nullable();
            $table->timestampTz('new_close_at')->nullable();
            $table->text('reason');
            $table->boolean('retroactive')->default(true);
            $table->string('status', 30)->default('PENDING')->index();
            $table->foreignId('reprogrammed_by')->nullable()->constrained('users');
            $table->timestampTz('reprogrammed_at')->nullable();
            $table->timestampTz('notification_sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['scheduled_load_id','suspension_id'],'load_reschedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_reschedules');
        Schema::dropIfExists('scheduled_loads');
        Schema::dropIfExists('calendar_suspensions');
        Schema::dropIfExists('calendar_import_rows');
        Schema::dropIfExists('calendar_imports');
    }
};
