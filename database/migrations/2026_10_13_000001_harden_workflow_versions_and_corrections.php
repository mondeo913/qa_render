<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evidence_reviews', function (Blueprint $table) {
            // Null identifies historical decisions whose version was not recorded.
            $table->unsignedInteger('evidence_version')->nullable();
        });
        Schema::create('evidence_correction_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('scheduled_load_deliverables');
            $table->foreignId('authorized_by')->constrained('users');
            $table->text('reason');
            $table->timestampTz('opens_at');
            $table->timestampTz('closes_at');
            $table->timestampTz('used_at')->nullable();
            $table->unsignedInteger('evidence_version')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        // Update only workflow capabilities, preserving unrelated customized permissions.
        $closeCodes = ['scheduled_load.review', 'scheduled_load.verify', 'scheduled_load.signature_package',
            'scheduled_load.upload_signed', 'scheduled_load.close', 'scheduled_load.reopen'];
        $permissions = DB::table('permissions')->whereIn('code', $closeCodes)->pluck('id');
        $directors = DB::table('roles')->whereIn('code', ['DIRECTOR_GENERAL', 'DIRECTOR',
            'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD'])->pluck('id');
        DB::table('permission_role')->whereIn('role_id', $directors)->whereIn('permission_id', $permissions)->delete();
        $reviewId = DB::table('permissions')->where('code', 'evidence.review')->value('id');
        if ($reviewId) {
            foreach (DB::table('roles')->whereIn('code', ['DIRECTOR', 'DIRECTOR_TRANSMISION',
                'DIRECTOR_PROGRAMACION_CONTINUIDAD'])->pluck('id') as $id) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $id, 'permission_id' => $reviewId], []);
            }
            DB::table('permission_role')->whereIn('role_id', DB::table('roles')
                ->where('code', 'DIRECTOR_GENERAL')->pluck('id'))->where('permission_id', $reviewId)->delete();
        }
    }

    public function down(): void
    {
        // Do not restore broader permissions during rollback.
        Schema::dropIfExists('evidence_correction_windows');
        Schema::table('evidence_reviews', fn (Blueprint $table) => $table->dropColumn('evidence_version'));
    }
};
