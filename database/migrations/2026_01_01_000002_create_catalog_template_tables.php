<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 200);
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['catalog_id','code']);
        });

        Schema::create('evidence_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contracting_agency_id')->constrained();
            $table->string('code', 80);
            $table->string('name', 240);
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->boolean('requires_director_signature')->default(true);
            $table->json('allowed_signed_extensions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestampsTz();
            $table->unique(['contracting_agency_id','code','version'], 'template_version_unique');
        });

        Schema::create('template_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('evidence_templates')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 240);
            $table->text('description')->nullable();
            $table->foreignId('responsible_unit_id')->nullable()->constrained('organizational_units');
            $table->string('responsible_role_code', 60)->default('OPERADOR');
            $table->boolean('required')->default(true);
            $table->boolean('requires_validation')->default(true);
            $table->boolean('requires_signature')->default(false);
            $table->unsignedInteger('min_files')->default(1);
            $table->unsignedInteger('max_files')->default(1);
            $table->unsignedInteger('max_size_mb')->default(100);
            $table->json('allowed_extensions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['template_id','code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_requirements');
        Schema::dropIfExists('evidence_templates');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('catalogs');
    }
};
