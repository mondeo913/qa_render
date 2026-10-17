<?php

namespace App\Console\Commands;

use App\Models\BackupExecution;
use App\Services\MonthlyBackupService;
use Illuminate\Console\Command;

final class SigetBackupStatus extends Command
{
    protected $signature = 'siget:backup-status';
    protected $description = 'Revisa la antigüedad del último respaldo mensual verificado';
    public function handle(MonthlyBackupService $service): int
    {
        $last = BackupExecution::where('status', 'VERIFIED')->whereNotNull('verified_at')->latest('verified_at')->first();
        if (!$last || $last->verified_at->lt(now()->subDays(config('backup.maximum_age_days')))) {
            $service->incident('No existe un respaldo mensual verificado en los últimos 35 días.');
            $this->error('Falta un respaldo mensual reciente. Revise el Centro de Operaciones.');
            return self::FAILURE;
        }
        $this->info('Último respaldo mensual: '.$last->verified_at->toIso8601String());
        return self::SUCCESS;
    }
}
