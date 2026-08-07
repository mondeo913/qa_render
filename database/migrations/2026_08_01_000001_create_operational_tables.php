<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  Schema::create('operational_metrics',function(Blueprint $t){
   $t->uuid('id')->primary();$t->string('metric_key',120)->index();$t->decimal('metric_value',20,4);
   $t->string('unit',30)->nullable();$t->json('dimensions')->default('{}');$t->timestampTz('collected_at')->index();$t->timestampsTz();
  });
  Schema::create('operational_incidents',function(Blueprint $t){
   $t->uuid('id')->primary();$t->string('code',40)->unique();$t->string('title');$t->string('severity',20)->index();
   $t->string('status',30)->index();$t->string('source',80);$t->text('description');$t->foreignId('assigned_to')->nullable()->constrained('users');
   $t->timestampTz('opened_at')->index();$t->timestampTz('acknowledged_at')->nullable();$t->timestampTz('resolved_at')->nullable();
   $t->text('resolution')->nullable();$t->json('metadata')->default('{}');$t->timestampsTz();
  });
  Schema::create('alert_rules',function(Blueprint $t){
   $t->uuid('id')->primary();$t->string('code',80)->unique();$t->string('name');$t->string('metric_key',120)->index();
   $t->string('operator',10);$t->decimal('threshold',20,4);$t->string('severity',20);
   $t->integer('evaluation_window_minutes')->default(5);$t->integer('cooldown_minutes')->default(30);
   $t->json('channels')->default('["database","mail"]');$t->json('recipients')->default('[]');$t->boolean('enabled')->default(true)->index();$t->timestampsTz();
  });
  Schema::create('backup_executions',function(Blueprint $t){
   $t->uuid('id')->primary();$t->string('backup_type',30);$t->string('status',20)->index();$t->timestampTz('started_at');
   $t->timestampTz('finished_at')->nullable();$t->text('database_file')->nullable();$t->text('storage_file')->nullable();
   $t->char('database_sha256',64)->nullable();$t->char('storage_sha256',64)->nullable();$t->unsignedBigInteger('size_bytes')->nullable();
   $t->timestampTz('verified_at')->nullable();$t->text('error_message')->nullable();$t->json('metadata')->default('{}');$t->timestampsTz();
  });
  Schema::create('maintenance_windows',function(Blueprint $t){
   $t->uuid('id')->primary();$t->string('title');$t->timestampTz('starts_at')->index();$t->timestampTz('ends_at');
   $t->string('impact',20);$t->string('status',30)->index();$t->foreignId('approved_by')->nullable()->constrained('users');
   $t->foreignId('executed_by')->nullable()->constrained('users');$t->text('rollback_plan');$t->text('result')->nullable();$t->timestampsTz();
  });
 }
 public function down():void {
  Schema::dropIfExists('maintenance_windows');Schema::dropIfExists('backup_executions');
  Schema::dropIfExists('alert_rules');Schema::dropIfExists('operational_incidents');Schema::dropIfExists('operational_metrics');
 }
};
