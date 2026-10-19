<?php

namespace App\Console\Commands;

use App\Models\{Role, User};
use Database\Seeders\{AgencyTemplateSeeder, RolePermissionSeeder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rules\Password;

final class SigetInstall extends Command
{
    protected $signature = 'siget:install {--admin-name=} {--admin-email=} {--password-stdin}';
    protected $description = 'Instala catálogos y el primer administrador; nunca crea datos ni usuarios QA';

    public function handle(): int
    {
        if (User::whereHas('role', fn ($r) => $r->where('code', 'ADMINISTRADOR'))->exists()) {
            $this->info('Ya existe un administrador. Se conservan sus credenciales y configuración.');
            return self::SUCCESS;
        }
        $name = $this->option('admin-name') ?: $this->ask('Nombre del administrador');
        $email = mb_strtolower(trim($this->option('admin-email') ?: $this->ask('Correo institucional del administrador')));
        $password = $this->option('password-stdin') ? rtrim((string) fgets(STDIN), "\r\n") : $this->secret('Contraseña inicial (mínimo 12 caracteres, letras, números y símbolos)');
        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:200'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        DB::transaction(function () use ($name, $email, $password) {
            (new RolePermissionSeeder)->run();
            (new AgencyTemplateSeeder)->run();
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'status' => 'ACTIVE',
                'role_id' => Role::where('code', 'ADMINISTRADOR')->value('id'), 'metadata' => ['initial_administrator' => true]]);
            app(\App\Services\AuditService::class)->record('system.installed', $user, [], ['initial_administrator_id' => $user->id]);
        });
        $this->info('SIGET instalado. Un administrador inicial; ningún usuario, pauta ni evidencia de demostración.');
        return self::SUCCESS;
    }
}
