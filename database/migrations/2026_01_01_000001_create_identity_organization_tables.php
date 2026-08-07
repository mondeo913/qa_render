<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('module', 80);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('contracting_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 220);
            $table->string('legal_name', 260)->nullable();
            $table->string('email_domain', 160)->nullable();
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contracting_agency_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units');
            $table->string('code', 70);
            $table->string('name', 220);
            $table->string('unit_type', 50)->default('DIRECTION');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['contracting_agency_id', 'code']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained();
            $table->foreignId('contracting_agency_id')->nullable()->constrained();
            $table->foreignId('organizational_unit_id')->nullable()->constrained();
            $table->string('name', 200);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contracting_agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(false);
            $table->timestampsTz();
            $table->unique(['user_id','contracting_agency_id','organizational_unit_id'], 'user_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scopes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('contracting_agencies');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
