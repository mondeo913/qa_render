<?php

namespace App\Services;

use App\Models\{BackupExecution, OperationalIncident};
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class MonthlyBackupService
{
    public function create(): BackupExecution
    {
        if (!app()->isDownForMaintenance()) throw new RuntimeException('Active mantenimiento y detenga app, worker y scheduler antes del respaldo.');
        if (config('database.default') !== 'pgsql') throw new RuntimeException('El respaldo de producción requiere PostgreSQL.');
        if (config('filesystems.default') !== config('siget.repository_disk')) throw new RuntimeException('Pautas y evidencias deben compartir el disco privado de producción.');
        $root = config('backup.directory');
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new RuntimeException('No se pudo crear el destino de respaldos.');
        $lock = fopen($root.'/.backup.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Ya existe un respaldo en ejecución.');
        $backup = null;
        try {
            $backup = BackupExecution::create(['backup_type' => 'MONTHLY_FULL', 'status' => 'RUNNING', 'started_at' => now(), 'metadata' => ['cadence' => 'monthly']]);
            if (!app(InfrastructureReadinessService::class)->repositoryReady()) throw new RuntimeException('El repositorio privado no coincide con VM2 o no está disponible.');
            $source = realpath(Storage::disk(config('siget.repository_disk'))->path(''));
            if (!$source || !is_dir($source)) throw new RuntimeException('El repositorio privado no está disponible.');
            if (str_starts_with(realpath($root).'/', $source.'/')) throw new RuntimeException('El respaldo no puede guardarse dentro del repositorio.');
            $files = $this->repositoryFiles($source);
            $databaseSize = (int) DB::selectOne('select pg_database_size(current_database()) as bytes')->bytes;
            $required = (int) ceil(($files['bytes'] + $databaseSize) * 1.3) + config('backup.minimum_free_bytes');
            if (disk_free_space($root) < $required) throw new RuntimeException('Espacio insuficiente: se requieren '.$required.' bytes incluyendo reserva.');
            $backup->update(['metadata' => ['cadence' => 'monthly', 'repository_files' => count($files['paths'])]]);
            $directory = $root.'/'.$backup->id;
            if (!mkdir($directory, 0700)) throw new RuntimeException('No se pudo crear el respaldo.');
            $databasePath = $directory.'/database.dump';
            $db = config('database.connections.pgsql');
            $dump = new Process(['pg_dump', '--format=custom', '--no-owner', '--no-acl', '--file='.$databasePath], null, $this->pgEnvironment($db));
            $dump->setTimeout(7200)->mustRun();
            $zipPath = $directory.'/repository.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo abrir el archivo del repositorio.');
            foreach ($files['paths'] as $relative) {
                if (!$zip->addFile($source.'/'.$relative, $relative) || !$zip->setCompressionName($relative, ZipArchive::CM_STORE)) {
                    $zip->close();
                    throw new RuntimeException('No se pudo incorporar un archivo del repositorio.');
                }
            }
            // ZipArchive removes an archive with no members; preserve an explicit empty-repository marker.
            if (!$files['paths']) $zip->addFromString('.siget-empty-repository', '');
            if (!$zip->close()) throw new RuntimeException('No se pudo finalizar el archivo del repositorio.');
            file_put_contents($directory.'/configuration.json', json_encode([
                'app_url' => config('app.url'), 'timezone' => config('app.timezone'),
                'backup_cadence' => 'monthly', 'repository_disk' => config('siget.repository_disk'),
                'release' => trim((string) @file_get_contents(base_path('RELEASE_VERSION'))),
                'note' => 'APP_KEY, contraseñas, certificados y .env se custodian por infraestructura fuera de este paquete.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $manifest = ['schema_version' => 1, 'id' => $backup->id, 'cadence' => 'monthly', 'type' => 'full',
                'created_at' => now()->toIso8601String(), 'repository_files' => count($files['paths']),
                'repository_bytes' => $files['bytes'], 'row_counts' => [], 'files' => []];
            foreach (['users', 'calendar_imports', 'scheduled_loads', 'scheduled_load_deliverables', 'evidences', 'evidence_files', 'evidence_reviews', 'audit_logs'] as $table) {
                $manifest['row_counts'][$table] = DB::table($table)->count();
            }
            foreach (['database.dump', 'repository.zip', 'configuration.json'] as $name) {
                $manifest['files'][$name] = ['sha256' => hash_file('sha256', $directory.'/'.$name), 'bytes' => filesize($directory.'/'.$name)];
                chmod($directory.'/'.$name, 0600);
            }
            file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($directory.'/manifest.json', 0600);
            $this->verify($directory);
            $backup->update(['status' => 'HASH_VERIFIED', 'finished_at' => now(),
                'database_file' => $databasePath, 'storage_file' => $zipPath,
                'database_sha256' => $manifest['files']['database.dump']['sha256'],
                'storage_sha256' => $manifest['files']['repository.zip']['sha256'],
                'size_bytes' => array_sum(array_column($manifest['files'], 'bytes')),
                'metadata' => $backup->metadata + ['manifest' => $directory.'/manifest.json', 'hash_verified_at' => now()->toIso8601String()]]);
            return $backup->fresh();
        } catch (\Throwable $exception) {
            $backup?->update(['status' => 'FAILED', 'finished_at' => now(), 'error_message' => 'El respaldo mensual no se completó. Consulte el registro de infraestructura.']);
            $this->incident('No se completó el respaldo mensual. Verifique espacio, PostgreSQL y repositorio.');
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function verify(string $directory): array
    {
        $manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        if (($manifest['schema_version'] ?? null) !== 1 || ($manifest['cadence'] ?? '') !== 'monthly') throw new RuntimeException('Manifiesto de respaldo no válido.');
        foreach (['database.dump', 'repository.zip', 'configuration.json'] as $name) {
            $entry = $manifest['files'][$name] ?? [];
            $path = $directory.'/'.$name;
            if (!is_file($path) || is_link($path) || !preg_match('/^[a-f0-9]{64}$/', $entry['sha256'] ?? '')
                || filesize($path) !== ($entry['bytes'] ?? null) || !hash_equals($entry['sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('El archivo '.$name.' está ausente o su integridad no coincide.');
            }
        }
        $this->inspectZip($directory.'/repository.zip', $manifest);
        return $manifest;
    }

    public function restore(string $directory, string $database, string $target): void
    {
        $manifest = $this->verify($directory);
        if (!preg_match('/^siget_restore_[a-z0-9_]+$/', $database)
            || $database === config('database.connections.pgsql.database')) {
            throw new RuntimeException('Use una base vacía independiente cuyo nombre comience con siget_restore_.');
        }
        if (!is_dir($target) || is_link($target) || count(scandir($target)) !== 2) throw new RuntimeException('El destino de archivos debe existir y estar completamente vacío.');
        if (disk_free_space($target) < $manifest['repository_bytes'] + config('backup.minimum_free_bytes')) throw new RuntimeException('Espacio insuficiente para restaurar.');
        config(['database.connections.siget_restore' => array_replace(config('database.connections.pgsql'), ['database' => $database])]);
        DB::purge('siget_restore');
        $owner = DB::connection('siget_restore')->selectOne('select pg_get_userbyid(datdba) = current_user as owned from pg_database where datname = current_database()');
        if (!$owner?->owned) throw new RuntimeException('La base aislada debe pertenecer al usuario de aplicación.');
        $tables = (int) DB::connection('siget_restore')->selectOne("select count(*) as total from information_schema.tables where table_schema not in ('pg_catalog', 'information_schema')")->total;
        if ($tables !== 0) throw new RuntimeException('La base de restauración contiene tablas. No se sobrescribe.');
        $db = config('database.connections.siget_restore');
        $process = new Process(['pg_restore', '--exit-on-error', '--clean', '--if-exists', '--no-owner', '--no-acl', '--dbname='.$database, $directory.'/database.dump'], null, $this->pgEnvironment($db));
        $process->setTimeout(7200)->mustRun();
        foreach ($manifest['row_counts'] ?? [] as $table => $expected) {
            if (!in_array($table, ['users', 'calendar_imports', 'scheduled_loads', 'scheduled_load_deliverables', 'evidences', 'evidence_files', 'evidence_reviews', 'audit_logs'], true)
                || DB::connection('siget_restore')->table($table)->count() !== $expected) throw new RuntimeException('La base restaurada no coincide con el manifiesto.');
        }
        $zip = new ZipArchive;
        if ($zip->open($directory.'/repository.zip') !== true) throw new RuntimeException('No se pudo leer el repositorio.');
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name === '.siget-empty-repository') continue;
                $path = $target.'/'.$name;
                if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
                $source = $zip->getStream($name);
                $output = fopen($path, 'xb');
                if (!$source || !$output) throw new RuntimeException('No se pudo restaurar un archivo.');
                try {
                    if (stream_copy_to_stream($source, $output) !== $zip->statIndex($index)['size']) throw new RuntimeException('Archivo restaurado incompleto.');
                } finally { fclose($source); fclose($output); }
                chmod($path, 0600);
            }
        } finally { $zip->close(); }
        foreach (DB::connection('siget_restore')->table('evidence_files')->select(['storage_path', 'sha256'])->cursor() as $file) {
            $this->assertRestoredHash($target, $file->storage_path, $file->sha256);
        }
        foreach (DB::connection('siget_restore')->table('calendar_imports')->select(['storage_path', 'sha256'])->cursor() as $file) {
            $this->assertRestoredHash($target, $file->storage_path, $file->sha256);
        }
        $backup = \Illuminate\Support\Str::isUuid($manifest['id'] ?? '') ? BackupExecution::whereKey($manifest['id'])->where('metadata->manifest', $directory.'/manifest.json')->first() : null;
        $backup?->update(['status' => 'VERIFIED', 'verified_at' => now(), 'error_message' => null,
            'metadata' => array_replace($backup->metadata ?? [], ['restore_tested_at' => now()->toIso8601String(), 'restore_database' => $database])]);
        if ($backup) OperationalIncident::where('code', 'BACKUP_MONTHLY')->where('status', 'OPEN')->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
    }

    public function incident(string $message): void
    {
        OperationalIncident::updateOrCreate(['code' => 'BACKUP_MONTHLY'], [
            'title' => 'Revisar respaldo mensual', 'severity' => 'HIGH', 'status' => 'OPEN', 'source' => 'backup',
            'description' => $message, 'opened_at' => now(), 'resolved_at' => null,
        ]);
    }

    private function repositoryFiles(string $root): array
    {
        $paths = []; $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('El repositorio contiene una entrada no regular.');
            $paths[] = substr($file->getPathname(), strlen($root) + 1);
            $bytes += $file->getSize();
        }
        sort($paths);
        return compact('paths', 'bytes');
    }

    private function inspectZip(string $path, array $manifest): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Archivo del repositorio dañado.');
        try {
            $seen = []; $bytes = 0; $files = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $zip->getExternalAttributesIndex($index, $os, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if (!$name || str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")
                    || preg_match('~(^|/)\.{1,2}(/|$)~', $name) || str_contains($name, ':') || isset($seen[$name])
                    || !in_array($type, [0, 0100000], true)) throw new RuntimeException('Ruta no permitida en el archivo del repositorio.');
                $seen[$name] = true;
                if ($name !== '.siget-empty-repository') { $bytes += $zip->statIndex($index)['size']; $files++; }
            }
            if ($bytes !== ($manifest['repository_bytes'] ?? null) || $files !== ($manifest['repository_files'] ?? null)) throw new RuntimeException('El contenido del repositorio no coincide con el manifiesto.');
        } finally { $zip->close(); }
    }

    private function pgEnvironment(array $db): array
    {
        return ['PGHOST' => (string) $db['host'], 'PGPORT' => (string) $db['port'],
            'PGDATABASE' => (string) $db['database'], 'PGUSER' => (string) $db['username'],
            'PGPASSWORD' => (string) $db['password'], 'PGSSLMODE' => $db['sslmode'] ?? 'prefer'];
    }

    private function assertRestoredHash(string $root, string $relative, string $hash): void
    {
        if (str_starts_with($relative, '/') || str_contains($relative, '\\') || preg_match('~(^|/)\.{1,2}(/|$)~', $relative)
            || !preg_match('/^[a-f0-9]{64}$/', $hash) || !is_file($root.'/'.$relative)
            || !hash_equals($hash, hash_file('sha256', $root.'/'.$relative))) {
            throw new RuntimeException('La evidencia o pauta restaurada no coincide con el hash de la base.');
        }
    }
}
