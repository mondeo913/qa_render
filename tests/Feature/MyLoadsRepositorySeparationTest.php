<?php

namespace Tests\Feature;

use Tests\TestCase;

class MyLoadsRepositorySeparationTest extends TestCase
{
    public function test_my_loads_route_uses_a_dedicated_operational_view(): void
    {
        $this->assertTrue(true);
        $this->assertStringContainsString('mis-cargas', file_get_contents(resource_path('views/cargas/mis-cargas.blade.php')));
        $this->assertStringNotContainsString('repositorio.index', file_get_contents(resource_path('views/cargas/mis-cargas.blade.php')));
    }
}
