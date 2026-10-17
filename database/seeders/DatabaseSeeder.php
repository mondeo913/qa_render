<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'qa', 'testing'])) {
            $this->call([RolePermissionSeeder::class, AgencyTemplateSeeder::class]);
            return;
        }
        $this->call([
            RolePermissionSeeder::class,
            AgencyTemplateSeeder::class,
            CalendarSuspensionSeeder::class,
            QaUniverseSeeder::class,
            DashboardDemoDataSeeder::class,
        ]);
    }
}
