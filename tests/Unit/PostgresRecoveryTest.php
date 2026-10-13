<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PostgresRecoveryTest extends TestCase
{
    private string $fixture;
    private string $library;

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir().'/siget-pg-'.bin2hex(random_bytes(8));
        mkdir($this->fixture.'/data', 0777, true);
        mkdir($this->fixture.'/bin');
        $this->library = dirname(__DIR__, 2).'/.devcontainer/lib-siget.sh';
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixture);
    }

    private function fakeServer(int $major): void
    {
        foreach (['postgres', 'initdb', 'pg_ctl'] as $name) {
            file_put_contents($this->fixture.'/bin/'.$name, "#!/bin/bash\necho 'postgres (PostgreSQL) {$major}.1'\n");
            chmod($this->fixture.'/bin/'.$name, 0755);
        }
    }

    private function runShell(string $code): Process
    {
        $shell = 'source '.escapeshellarg($this->library).'; PGDATA='.escapeshellarg($this->fixture.'/data').'; SIGET_POSTGRES_BIN='.escapeshellarg($this->fixture.'/bin').'; '.$code;
        $process = new Process(['bash', '-c', $shell]);
        $process->run();
        return $process;
    }

    public function test_selects_server_matching_existing_data(): void
    {
        $this->fakeServer(16);
        file_put_contents($this->fixture.'/data/PG_VERSION', '16');
        $process = $this->runShell('find_postgres_bin');
        $this->assertTrue($process->isSuccessful());
        $this->assertSame($this->fixture.'/bin', trim($process->getOutput()));
    }

    public function test_wrong_major_stops_even_when_start_is_called_from_a_conditional(): void
    {
        $this->fakeServer(16);
        file_put_contents($this->fixture.'/data/PG_VERSION', '99');
        file_put_contents($this->fixture.'/data/evidence', 'preserve');
        $process = $this->runShell('if start_postgres; then exit 0; else exit 1; fi');
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('PostgreSQL 99', $process->getErrorOutput());
        $this->assertStringNotContainsString('/pg_ctl', $process->getErrorOutput());
        $this->assertSame('preserve', file_get_contents($this->fixture.'/data/evidence'));
    }

    public function test_incomplete_data_directory_is_not_deleted(): void
    {
        $this->fakeServer(16);
        file_put_contents($this->fixture.'/data/evidence', 'preserve');
        $process = $this->runShell('ensure_directories() { :; }; initialize_postgres');
        $this->assertFalse($process->isSuccessful());
        $this->assertSame('preserve', file_get_contents($this->fixture.'/data/evidence'));
    }

    public function test_live_process_pid_is_preserved_when_readiness_probe_fails(): void
    {
        file_put_contents($this->fixture.'/data/PG_VERSION', '16');
        $process = $this->runShell('echo $$ > "$PGDATA/postmaster.pid"; postgres_is_ready() { return 1; }; clear_stale_postmaster_pid; test -f "$PGDATA/postmaster.pid"');
        $this->assertTrue($process->isSuccessful());
    }
}
