<?php
namespace App\Console\Commands;
use App\Services\QaUserStandardService;
use Illuminate\Console\Command;
class SigetQaUsers extends Command
{
    protected $signature = 'siget:qa-users {--password= : Cambiar explícitamente las contraseñas de las cuentas QA estándar}';
    protected $description = 'Unifica las cuentas QA por perfil y conserva datos, historial y contraseñas existentes.';
    public function handle(QaUserStandardService $standard): int
    {
        if (!app()->environment(['local', 'testing', 'qa'])) {
            $this->error('La normalización automática está restringida a entornos QA.');
            return self::FAILURE;
        }
        try {
            $users = $standard->sync($this->option('password') ?: null);
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        foreach ($users as $user) $this->line($user->email.' · '.$user->role->name);
        $this->info('Ocho perfiles QA estándar. Cuentas duplicadas retiradas; historial conservado.');
        return self::SUCCESS;
    }
}
