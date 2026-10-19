<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB, Storage};

final class InfrastructureReadinessService
{
    public function repositoryReady(): bool
    {
        if (!config('infrastructure.require_repository')) return true;
        try {
            $disk = Storage::disk(config('siget.repository_disk'));
            $expected = (string) config('infrastructure.repository_id');
            return $expected !== '' && is_readable($disk->path('.siget-repository-id'))
                && hash_equals($expected, trim((string) file_get_contents($disk->path('.siget-repository-id'))));
        } catch (\Throwable $e) { report($e); return false; }
    }

    public function ready(): bool
    {
        try {
            DB::select('select 1');
            if (!$this->repositoryReady()) return false;
            if (config('siget.antivirus_enabled')) {
                $socket = @fsockopen('antivirus', 3310, $error, $message, 2);
                if (!$socket) return false;
                stream_set_timeout($socket, 2);
                fwrite($socket, "nPING\n");
                $pong = trim((string) fgets($socket));
                fclose($socket);
                if ($pong !== 'PONG') return false;
            }
            return true;
        } catch (\Throwable $e) { report($e); return false; }
    }
}
