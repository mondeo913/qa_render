<?php
namespace Tests\Unit;
use App\Support\RolePresentation;
use PHPUnit\Framework\TestCase;
class K2RolePresentationTest extends TestCase {
    public function test_roles_have_distinct_dashboard_presentations(): void {
        $this->assertSame('Centro ejecutivo institucional',RolePresentation::for('DIRECTOR_GENERAL')['title']);
        $this->assertSame('Centro de revisión institucional',RolePresentation::for('ENLACE_INSTITUCIONAL')['title']);
        $this->assertSame('Dashboard de Transmisión',RolePresentation::for('DIRECTOR_TRANSMISION')['title']);
        $this->assertSame('Dashboard de Programación y Continuidad',RolePresentation::for('DIRECTOR_PROGRAMACION_CONTINUIDAD')['title']);
    }
}
