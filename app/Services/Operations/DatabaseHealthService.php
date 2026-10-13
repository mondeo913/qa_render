<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;

final class DatabaseHealthService
{
    public function inspect(): array
    {
        $started = microtime(true);
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $version = $connection->selectOne('select sqlite_version() as version')->version;
            $database = $connection->getDatabaseName();
            return [
                'status' => 'UP',
                'driver' => $driver,
                'latency_ms' => round((microtime(true) - $started) * 1000, 2),
                'version' => $version,
                // SQLite does not expose PostgreSQL session metrics.
                'connections_total' => null,
                'connections_active' => null,
                'size_bytes' => is_file($database) ? filesize($database) : 0,
            ];
        }

        $version = $connection->selectOne('select version() as version')->version;
        $connections = $connection->selectOne("select count(*)::int total, count(*) filter(where state='active')::int active from pg_stat_activity where datname=current_database()");
        $size = $connection->selectOne('select pg_database_size(current_database())::bigint bytes');

        return [
            'status' => 'UP',
            'driver' => $driver,
            'latency_ms' => round((microtime(true) - $started) * 1000, 2),
            'version' => $version,
            'connections_total' => $connections->total,
            'connections_active' => $connections->active,
            'size_bytes' => (int) $size->bytes,
        ];
    }
}
