<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\{ContractingAgency, ScheduledLoad, User};
use App\Services\{FileScanService, MonthlyBackupService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{File, Storage};
use Tests\TestCase;
use ZipArchive;

class InfrastructureDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/siget-infrastructure-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        Storage::fake('local');
        config(['siget.repository_disk' => 'local', 'siget.antivirus_enabled' => false, 'backup.minimum_free_bytes' => 0]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_clean_install_creates_only_initial_admin_and_catalogs_and_is_idempotent(): void
    {
        $this->assertSame(0, User::count());
        $arguments = ['--admin-name' => 'Administrador institucional', '--admin-email' => 'admin@institucion.gob.mx'];
        $this->artisan('siget:install', $arguments)
            ->expectsQuestion('Contraseña inicial (mínimo 12 caracteres, letras, números y símbolos)', 'Secure_Admin_2026!')->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertSame(0, User::where('metadata->qa', true)->count());
        $this->assertSame(0, ScheduledLoad::count());
        $this->assertSame(7, ContractingAgency::count());
        $this->assertSame('ADMINISTRADOR', User::firstOrFail()->role->code);
        $before = User::firstOrFail()->password;
        $this->artisan('siget:install', $arguments)->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertSame($before, User::firstOrFail()->password);
    }

    public function test_production_install_rejects_weak_password_without_creating_users(): void
    {
        $this->artisan('siget:install', ['--admin-name' => 'Admin', '--admin-email' => 'admin@institucion.gob.mx'])
            ->expectsQuestion('Contraseña inicial (mínimo 12 caracteres, letras, números y símbolos)', 'simple')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_qa_commands_cannot_reset_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('siget:qa-init', ['--reset' => true])->assertFailed();
        $this->artisan('siget:qa-users')->assertFailed();
        $this->artisan('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, User::count());
        $this->assertSame(0, ScheduledLoad::count());
    }

    public function test_missing_or_different_private_repository_blocks_api_without_accepting_local_fallback(): void
    {
        config(['infrastructure.require_repository' => true, 'infrastructure.repository_id' => 'expected-vm2-id']);
        $this->postJson('/api/v1/auth/token')->assertStatus(503);
        Storage::disk('local')->put('.siget-repository-id', 'wrong-repository');
        $this->postJson('/api/v1/auth/token')->assertStatus(503);
        Storage::disk('local')->put('.siget-repository-id', 'expected-vm2-id');
        $this->postJson('/api/v1/auth/token')->assertUnprocessable();
        $this->getJson('/ready')->assertOk()->assertJsonPath('status', 'ready');
    }

    public function test_backup_creation_requires_quiesced_writers_and_maintenance(): void
    {
        $this->artisan('siget:backup-create')->assertFailed();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mantenimiento');
        app(MonthlyBackupService::class)->create();
    }

    public function test_backup_verification_checks_all_hashes_and_actual_archive_sizes(): void
    {
        $this->bundle(['evidencias/testigo.pdf' => 'evidencia-verificable']);
        $service = app(MonthlyBackupService::class);
        $this->assertSame('monthly', $service->verify($this->directory)['cadence']);
        file_put_contents($this->directory.'/database.dump', 'base-alterada');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('integridad');
        $service->verify($this->directory);
    }

    public function test_restore_verify_only_does_not_write_target_or_require_a_running_postgres(): void
    {
        $this->bundle(['documentos/firmado.pdf' => 'documento']);
        $this->artisan('siget:backup-restore', ['directory' => $this->directory])->assertSuccessful();
        $this->assertSame(0, User::count());
        $this->assertFileDoesNotExist($this->directory.'/documentos/firmado.pdf');
    }

    public function test_archive_traversal_is_rejected_even_when_archive_hash_matches(): void
    {
        $this->bundle(['../outside.txt' => 'no debe extraerse']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ruta no permitida');
        app(MonthlyBackupService::class)->verify($this->directory);
    }

    public function test_missing_hash_is_rejected_and_restoration_cannot_target_live_database(): void
    {
        $this->bundle(['evidencia.pdf' => 'evidencia']);
        $manifest = json_decode(file_get_contents($this->directory.'/manifest.json'), true);
        unset($manifest['files']['database.dump']['sha256']);
        file_put_contents($this->directory.'/manifest.json', json_encode($manifest));
        $this->artisan('siget:backup-restore', ['directory' => $this->directory])->assertFailed();
        $this->bundle(['evidencia.pdf' => 'evidencia']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('base vacía independiente');
        app(MonthlyBackupService::class)->restore($this->directory, 'siget', $this->directory);
    }

    public function test_backup_status_flags_missing_or_stale_monthly_backup_in_operations_center(): void
    {
        $this->artisan('siget:backup-status')->assertFailed();
        $this->assertDatabaseHas('operational_incidents', ['code' => 'BACKUP_MONTHLY', 'status' => 'OPEN']);
        \App\Models\BackupExecution::create(['backup_type' => 'MONTHLY_FULL', 'status' => 'VERIFIED', 'started_at' => now(), 'verified_at' => now()]);
        $this->artisan('siget:backup-status')->assertSuccessful();
        $this->travel(36)->days();
        $this->artisan('siget:backup-status')->assertFailed();
    }

    public function test_antivirus_cannot_label_a_file_clean_if_scan_is_unavailable_or_infected(): void
    {
        config(['siget.antivirus_enabled' => true]);
        foreach ([0, 1, 2] as $exit) {
            $command = $this->directory.'/scanner-'.$exit;
            file_put_contents($command, '#!/bin/sh'."\nexit $exit\n");
            chmod($command, 0700);
            config(['siget.antivirus_command' => $command]);
            $file = UploadedFile::fake()->create('documento.pdf', 1);
            if ($exit === 0) { app(FileScanService::class)->assertClean($file); $this->addToAssertionCount(1); continue; }
            try { app(FileScanService::class)->assertClean($file); $this->fail('No debe aceptar el archivo.'); }
            catch (WorkflowException $e) { $this->assertStringContainsString($exit === 1 ? 'rechazado' : 'no está disponible', $e->getMessage()); }
        }
    }

    private function bundle(array $entries): void
    {
        file_put_contents($this->directory.'/database.dump', 'postgresql-fixture');
        file_put_contents($this->directory.'/configuration.json', json_encode(['backup_cadence' => 'monthly']));
        $zip = new ZipArchive;
        $zip->open($this->directory.'/repository.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $value) $zip->addFromString($name, $value);
        $zip->close();
        $manifest = ['schema_version' => 1, 'cadence' => 'monthly', 'repository_files' => count($entries),
            'repository_bytes' => array_sum(array_map('strlen', $entries)), 'files' => []];
        foreach (['database.dump', 'repository.zip', 'configuration.json'] as $name) {
            $manifest['files'][$name] = ['sha256' => hash_file('sha256', $this->directory.'/'.$name), 'bytes' => filesize($this->directory.'/'.$name)];
        }
        file_put_contents($this->directory.'/manifest.json', json_encode($manifest));
    }
}
