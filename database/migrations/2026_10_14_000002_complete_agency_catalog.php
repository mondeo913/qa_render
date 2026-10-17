<?php

use Database\Seeders\AgencyTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new AgencyTemplateSeeder())->run();
        // QA conserva las cuentas generales y añade los alcances del catálogo nuevo.
        if (app()->environment(['local', 'testing', 'qa']) && \App\Models\User::query()->where('metadata->qa', true)->exists()) {
            app(\App\Services\QaUserStandardService::class)->sync();
        }
    }

    public function down(): void
    {
        // No eliminar dependencias que ya puedan tener cargas, evidencias o historial.
    }
};
