<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->where('code', 'indicators.view')->first();

        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }

    public function down(): void
    {
        Permission::query()->firstOrCreate(
            ['code' => 'indicators.view'],
            [
                'name' => 'Ver indicadores',
                'module' => 'indicators',
            ]
        );
    }
};
