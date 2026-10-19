<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

final class FileScanService
{
    public function assertClean(UploadedFile $file): string
    {
        if (!config('siget.antivirus_enabled')) return 'SKIPPED';
        $command = config('siget.antivirus_command', 'clamscan');
        $arguments = [$command, '--no-summary'];
        if (basename($command) === 'clamdscan') $arguments[] = '--stream';
        $arguments[] = $file->getRealPath();
        try {
            $scan = Process::timeout(300)->run($arguments);
            if ($scan->exitCode() === 1) throw new WorkflowException('El archivo fue rechazado por el análisis antivirus.');
            if (!$scan->successful()) throw new WorkflowException('El antivirus no está disponible. Reintente cuando se restablezca el servicio.');
            return 'CLEAN';
        } catch (WorkflowException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new WorkflowException('No fue posible completar el análisis antivirus. El archivo no fue incorporado.');
        }
    }
}
