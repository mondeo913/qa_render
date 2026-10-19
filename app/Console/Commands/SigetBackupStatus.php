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
        $last = BackupExecution::where('backup_type', 'MONTHLY_FULL')->where('status', 'VERIFIED')->whereNotNull('verified_at')
            ->where('started_at', '>=', now()->subDays(config('backup.maximum_age_days')))
            ->whereNotNull('metadata->restore_tested_at')->latest('started_at')->first();
        if (!$last || $last->verified_at->lt(now()->subDays(config('backup.maximum_age_days')))) {
            $service->incident('No existe un respaldo mensual verificado en los últimos 35 días.');
            $this->error('Falta un respaldo mensual reciente. Revise el Centro de Operaciones.');
            return self::FAILURE;
        }
        $this->info('Última copia mensual: '.$last->started_at->toIso8601String().'; verificación: '.$last->verified_at->toIso8601String());
        return self::SUCCESS;
    }
}
