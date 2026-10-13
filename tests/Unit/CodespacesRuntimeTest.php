<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class CodespacesRuntimeTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = sys_get_temp_dir().'/siget-runtime-'.bin2hex(random_bytes(8));
        mkdir($this->fixture.'/.devcontainer', 0777, true);
        copy(dirname(__DIR__, 2).'/.devcontainer/qa-runtime.sh', $this->fixture.'/.devcontainer/qa-runtime.sh');
        file_put_contents($this->fixture.'/.devcontainer/lib-siget.sh', <<<'BASH'
STATE_DIR="$PROJECT_ROOT/.codespace"
LOG_DIR="$STATE_DIR/logs"
SUPERVISOR_CONFIG="$STATE_DIR/supervisor.conf"
record() { printf '%s\n' "$*" >> "$PROJECT_ROOT/calls.log"; }
ensure_directories() { mkdir -p "$LOG_DIR"; }
update_env_urls() { record urls; }
stop_legacy_laravel_server() { record stop-legacy; }
start_postgres() { record postgres; }
supervisor_is_running() { return 1; }
start_supervisor() { record supervisor; }
php() { record "php $*"; }
wait_for_http() { record "wait $*"; }
show_service_status() { record status; }
BASH);
        file_put_contents($this->fixture.'/.devcontainer/install-siget.sh', "#!/usr/bin/env bash\nprintf 'install\\n' >> calls.log\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixture);
        parent::tearDown();
    }

    public function test_first_start_delegates_to_installer(): void
    {
        $process = new Process(['bash', '.devcontainer/qa-runtime.sh'], $this->fixture);
        $process->mustRun();

        $this->assertSame("install\n", file_get_contents($this->fixture.'/calls.log'));
    }

    public function test_resume_starts_database_before_services_without_reinstalling(): void
    {
        mkdir($this->fixture.'/.codespace');
        touch($this->fixture.'/.codespace/application-installed');
        $process = new Process(['bash', '.devcontainer/qa-runtime.sh'], $this->fixture);
        $process->mustRun();

        $calls = file($this->fixture.'/calls.log', FILE_IGNORE_NEW_LINES);
        $this->assertSame(['urls', 'stop-legacy', 'postgres', 'supervisor'], array_slice($calls, 0, 4));
        $this->assertContains('wait http://127.0.0.1:8000/up SIGET 120', $calls);
        $this->assertContains('wait http://127.0.0.1:8025/readyz Mailpit 90', $calls);
        $this->assertNotContains('install', $calls);
        $this->assertStringNotContainsString('migrate', implode("\n", $calls));
        $this->assertFileExists($this->fixture.'/.codespace/application-installed');
    }

    public function test_codespaces_hooks_reference_existing_entry_points(): void
    {
        $root = dirname(__DIR__, 2);
        $config = json_decode(file_get_contents($root.'/.devcontainer/devcontainer.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['postCreateCommand', 'postStartCommand', 'postAttachCommand'] as $hook) {
            $this->assertMatchesRegularExpression('/^bash [a-zA-Z0-9.\/-]+$/', $config[$hook]);
            $this->assertFileExists($root.'/'.substr($config[$hook], 5));
        }
        $this->assertFileExists($root.'/.devcontainer/'.$config['build']['dockerfile']);
    }

    public function test_shell_entry_points_have_valid_syntax(): void
    {
        $root = dirname(__DIR__, 2);
        $scripts = array_merge(glob($root.'/*.sh'), glob($root.'/.devcontainer/*.sh'));
        $this->assertNotEmpty($scripts);
        foreach ($scripts as $script) {
            $process = new Process(['bash', '-n', $script]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), basename($script).': '.$process->getErrorOutput());
        }
    }
}
