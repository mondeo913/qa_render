<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_load_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_requirement_id')->constrained();
            $table->foreignId('organizational_unit_id')->constrained();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users');
            $table->string('status', 30)->default('PENDIENTE')->index();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->text('observations')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['scheduled_load_id','template_requirement_id','organizational_unit_id'],
                'deliverable_assignment_unique'
            );
        });

        Schema::create('repository_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('repository_folders')->cascadeOnDelete();
            $table->foreignId('contracting_agency_id')->constrained();
            $table->foreignId('organizational_unit_id')->nullable()->constrained();
            $table->foreignId('scheduled_load_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('folder_type', 60);
            $table->string('name', 240);
            $table->string('path_key', 900)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deliverable_id')->constrained('scheduled_load_deliverables')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('repository_folders');
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('title', 260);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('EN_CAPTURA')->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->timestampsTz();
        });

        Schema::create('signed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('repository_folders');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('document_type', 60)->default('DIRECTOR_SIGNED_PACKAGE');
            $table->string('signer_name', 220)->nullable();
            $table->string('signer_position', 220)->nullable();
            $table->date('signed_on')->nullable();
            $table->string('official_number', 120)->nullable();
            $table->text('observations')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('signed_document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('repository_folders');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('storage_disk', 60);
            $table->string('storage_path', 900);
            $table->string('extension', 20);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->string('antivirus_status', 30)->default('PENDING');
            $table->unsignedInteger('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('decision', 30);
            $table->text('comments')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_reviews');
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('signed_documents');
        Schema::dropIfExists('evidences');
        Schema::dropIfExists('repository_folders');
        Schema::dropIfExists('scheduled_load_deliverables');
    }
};
