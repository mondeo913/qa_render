<?php

namespace App\Console\Commands;

use App\Models\Evidence;
use App\Models\EvidenceReview;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Illuminate\Console\Command;

class SigetQaStatus extends Command
{
    protected $signature = 'siget:qa-status';
    protected $description = 'Muestra el estado resumido de los datos QA ABCD.';

    public function handle(): int
    {
        $this->table(
            ['Componente', 'Cantidad'],
            [
                ['Usuarios', User::query()->count()],
                ['Cargas', ScheduledLoad::query()->count()],
                ['Entregables', ScheduledLoadDeliverable::query()->count()],
                ['Evidencias', Evidence::query()->count()],
                ['Revisiones', EvidenceReview::query()->count()],
            ]
        );

        $this->line('Administrador: admin@siget.local');
        $this->line('Contraseña QA: SigetQA_2026_Cambiar!');

        return self::SUCCESS;
    }
}
