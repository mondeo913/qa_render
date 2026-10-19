<?php

use App\Models\{ContractingAgency, OrganizationalUnit, Role, User};
use App\Services\QaUserStandardService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!app()->environment(['local', 'qa', 'testing']) || !Schema::hasTable('users')) return;
        if (!User::where('metadata->qa', true)->exists() || !ContractingAgency::where('active', true)->exists()) return;
        if (OrganizationalUnit::where('active', true)->whereIn('code', ['DIR_A', 'DIR_B'])->distinct()->count('code') !== 2) return;
        $codes = array_column(QaUserStandardService::DEFINITIONS, 0);
        if (Role::whereIn('code', $codes)->count() !== count($codes)) return;
        app(QaUserStandardService::class)->sync();
    }

    public function down(): void
    {
        // Historical identities are retained. Rollback must not silently reactivate aliases.
    }
};
