<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institutional_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('institutional_link_id')->constrained('users');
            $table->boolean('evidences_correct')->default(false);
            $table->boolean('package_prepared_for_signature')->default(false);
            $table->text('observations')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('load_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->unique()->constrained();
            $table->foreignId('institutional_review_id')->constrained();
            $table->foreignId('signed_document_id')->constrained();
            $table->foreignId('closed_by')->constrained('users');
            $table->timestampTz('closed_at');
            $table->text('closing_comment')->nullable();
            $table->char('package_sha256', 64);
            $table->json('integrity_manifest');
            $table->string('closure_certificate_path', 900)->nullable();
            $table->boolean('reopened')->default(false);
            $table->text('reopened_reason')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users');
            $table->timestampTz('reopened_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('load_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_load_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_load_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('subject', 260);
            $table->text('message');
            $table->string('action_url', 700)->nullable();
            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('event', 100);
            $table->string('entity_type', 180);
            $table->string('entity_id', 120)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestampsTz();
            $table->index(['entity_type','entity_id','created_at'], 'audit_entity_idx');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 160)->unique();
            $table->json('value');
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('load_status_history');
        Schema::dropIfExists('load_closures');
        Schema::dropIfExists('institutional_reviews');
    }
};
