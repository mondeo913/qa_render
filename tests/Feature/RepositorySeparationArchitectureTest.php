<?php

namespace Tests\Feature;

use Tests\TestCase;

class RepositorySeparationArchitectureTest extends TestCase
{
    public function test_operational_and_institutional_views_are_separate(): void
    {
        $operational = file_get_contents(resource_path('views/cargas/mis-cargas.blade.php'));
        $institutional = file_get_contents(resource_path('views/repositorio/index.blade.php'));

        $this->assertStringContainsString('Mis cargas', $operational);
        $this->assertStringContainsString('Repositorio por dependencia', $institutional);
        $this->assertStringNotContainsString("view('repositorio.index'", file_get_contents(app_path('Http/Controllers/MyLoadsController.php')));
    }
}
