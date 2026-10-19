<?php
use App\Models\ContractingAgency;
use App\Services\AgencyWorkflowService;
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        ContractingAgency::where('active', true)->each(fn ($agency) => app(AgencyWorkflowService::class)->provision($agency));
    }
    public function down(): void {
        // Conservar alcances, plantillas y expedientes ya utilizados.
    }
};
