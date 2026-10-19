<?php

namespace App\Console\Commands;

use App\Services\MonthlyBackupService;
use Illuminate\Console\Command;

final class SigetBackupCreate extends Command
{
    protected $signature = 'siget:backup-create {--quiesced : Confirmar que infraestructura detuvo app, worker y scheduler} {--json}';
    protected $description = 'Crea y verifica el respaldo mensual completo de PostgreSQL y del repositorio privado';

    public function handle(MonthlyBackupService $service): int
    {
        if (!$this->option('quiesced')) { $this->error('Ejecute deploy/vm1/backup-monthly.sh para detener los escritores antes de copiar.'); return self::FAILURE; }
        try {
            $backup = $service->create();
            if ($this->option('json')) $this->line(json_encode(['id' => $backup->id, 'status' => $backup->status], JSON_THROW_ON_ERROR));
            else $this->info('Integridad del respaldo mensual verificada; complete la restauración de prueba: '.$backup->id);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'El respaldo falló. Consulte el registro.');
            return self::FAILURE;
        }
    }
}
