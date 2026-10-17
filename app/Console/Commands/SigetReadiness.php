<?php

namespace App\Console\Commands;

use App\Services\InfrastructureReadinessService;
use Illuminate\Console\Command;

final class SigetReadiness extends Command
{
    protected $signature = 'siget:readiness';
    protected $description = 'Comprueba PostgreSQL, repositorio privado y antivirus para infraestructura';
    public function handle(InfrastructureReadinessService $service): int
    {
        $ready = $service->ready();
        $this->line($ready ? 'ready' : 'unavailable');
        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
