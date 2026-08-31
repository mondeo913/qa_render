<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AgencyTemplateSeeder::class,
            CalendarSuspensionSeeder::class,
            QaDemoSeeder::class,
            QaCalendarCleanupSeeder::class,
            QaUserScopeCleanupSeeder::class,
        ]);
    }
}
