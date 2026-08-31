<?php

namespace App\Console\Commands;

use App\Models\ScheduledLoad;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class SigetQaInitialize extends Command
{
    protected $signature = 'siget:qa-init {--reset : Reconstruir completamente la base QA}';
    protected $description = 'Inicializa los usuarios y datos demostrativos del entorno QA ABCD.';

    public function handle(): int
    {
        if ($this->option('reset')) {
            $this->warn('Reconstruyendo completamente la base QA...');
            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ], $this->output);

            return self::SUCCESS;
        }

        if (!Schema::hasTable('users')) {
            $this->error('La tabla users todavía no existe. Ejecute las migraciones.');

            return self::FAILURE;
        }

        // El arranque de Codespaces sincroniza usuarios de forma independiente.
        // Por ello, que existan usuarios no significa que el catálogo de cargas
        // ya esté poblado. El tablero necesita cargas demostrativas para poder
        // construir sus filtros de dependencia, dirección y periodo.
        if (!ScheduledLoad::query()->exists()) {
            $this->info('No existen cargas programadas. Cargando datos demostrativos QA para poblar el Tablero de cargas...');
            Artisan::call('db:seed', [
                '--class' => 'QaDemoSeeder',
                '--force' => true,
            ], $this->output);

            return self::SUCCESS;
        }

        if (User::query()->exists()) {
            $this->info('La base ya contiene usuarios y cargas; se conserva la información existente.');

            return self::SUCCESS;
        }

        $this->info('Base vacía. Cargando usuarios, cargas y evidencias QA...');
        Artisan::call('db:seed', ['--force' => true], $this->output);

        return self::SUCCESS;
    }
}
