<?php
use App\Models\{Permission, Role};
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {
        $permission = Permission::where('code', 'calendar.view')->first();
        if (!$permission) return;
        Role::whereIn('code', ['DIRECTOR_GENERAL', 'FISCALIZADOR'])->each(fn ($role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }
    public function down(): void {
        // Conservar permisos que el administrador puede haber asignado previamente.
    }
};
