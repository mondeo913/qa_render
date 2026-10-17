<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_template_has_usable_navigation_and_valid_scripts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('code', 'ADMINISTRADOR')->firstOrFail()->id, 'status' => 'ACTIVE']);
        foreach (['executive', 'compliance', 'pending', 'evidence', 'audit', 'builder'] as $report) {
            $response = $this->actingAs($user)->get(route('reports.index', ['report' => $report]));
            $response->assertOk()->assertSee('report-filter-grid')->assertSee('aria-current="page"', false)->assertSee('Periodo mensual:');
            $html = $response->getContent();
            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $html, $scripts);
            foreach ($scripts[1] as $script) $this->assertStringNotContainsString('<script', $script);
            if ($report === 'builder') {
                $response->assertSee('report-chip-list')->assertSee('Mostrar esta columna en la vista previa');
            } else {
                $response->assertSee('report-table-scroll');
            }
        }
    }

    public function test_read_only_report_roles_do_not_receive_template_builder(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach (['DIRECTOR_GENERAL', 'ENLACE_INSTITUCIONAL', 'FISCALIZADOR'] as $code) {
            $user = User::factory()->create(['role_id' => Role::where('code', $code)->firstOrFail()->id, 'status' => 'ACTIVE']);
            $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertDontSee('id="builderUse"', false)->assertDontSee('Plantillas de reportes');
        }
    }
}
