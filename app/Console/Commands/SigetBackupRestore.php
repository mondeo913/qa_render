<?php

namespace App\Console\Commands;

use App\Services\MonthlyBackupService;
use Illuminate\Console\Command;

final class SigetBackupRestore extends Command
{
    protected $signature = 'siget:backup-restore {directory} {--execute} {--target-database=} {--target-repository=}';
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
        } catch (\Throwable $e) { report($e); $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No se pudo restaurar el respaldo.'); return self::FAILURE; }
    }
}
