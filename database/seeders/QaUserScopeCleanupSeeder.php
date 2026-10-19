<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class QaUserScopeCleanupSeeder extends Seeder
{
    public function run(): void
    {
        app(\App\Services\QaUserStandardService::class)->sync();
    }
}
