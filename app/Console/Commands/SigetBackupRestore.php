<?php

namespace App\Console\Commands;

use App\Services\MonthlyBackupService;
use Illuminate\Console\Command;

final class SigetBackupRestore extends Command
{
    protected $signature = 'siget:backup-restore {directory} {--execute} {--monthly-drill} {--target-database=} {--target-repository=}';
    protected $description = 'Verifica un respaldo; opcionalmente restaura a destinos vacíos e independientes';

    public function handle(MonthlyBackupService $service): int
    {
        try {
            $directory = $this->argument('directory');
            $service->verify($directory);
            if (!$this->option('execute')) { $this->info('Integridad correcta. No se modificó ninguna base ni repositorio.'); return self::SUCCESS; }
            if (!$this->option('target-database') || !$this->option('target-repository')) throw new \RuntimeException('Indique ambos destinos independientes.');
            $service->restore($directory, $this->option('target-database'), $this->option('target-repository'));
            $this->info('Restauración completada en destinos independientes. Valide antes de configurar producción.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->option('monthly-drill')) {
                $backup = \App\Models\BackupExecution::where('metadata->manifest', $this->argument('directory').'/manifest.json')->first();
                $backup?->update(['status' => 'RESTORE_FAILED', 'verified_at' => null,
                    'error_message' => 'No se completó la restauración de prueba mensual.',
                    'metadata' => \Illuminate\Support\Arr::except($backup->metadata ?? [], ['restore_tested_at', 'restore_database'])]);
                $service->incident('La copia mensual no superó la restauración de prueba. Revise el registro de infraestructura.');
            }
            report($e); $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo restaurar el respaldo.'); return self::FAILURE;
        }
    }
}
