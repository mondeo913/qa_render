<?php

namespace Tests\Unit;

use App\Support\RoleMenu;
use PHPUnit\Framework\TestCase;

class RoleMenuTest extends TestCase
{
    public function test_role_menus_match_the_integrated_workflow(): void
    {
        $admin = collect(RoleMenu::for('ADMINISTRADOR'))->pluck('route');
        $directorTx = collect(RoleMenu::for('DIRECTOR_TRANSMISION'))->pluck('route');
        $directorPc = collect(RoleMenu::for('DIRECTOR_PROGRAMACION_CONTINUIDAD'))->pluck('route');
        $operatorTx = collect(RoleMenu::for('OPERADOR_TRANSMISION'))->pluck('route');
        $operatorPc = collect(RoleMenu::for('OPERADOR_PROGRAMACION_CONTINUIDAD'))->pluck('route');
        $fiscalizador = collect(RoleMenu::for('FISCALIZADOR'))->pluck('route');

        $this->assertTrue($admin->contains('intelligence'));
        $this->assertTrue($admin->contains('admin.templates'));
        $this->assertTrue($directorTx->contains('indicators.index'));
        $this->assertTrue($directorTx->contains('loads.board'));
        $this->assertTrue($directorPc->contains('repository.index'));
        $this->assertTrue($operatorTx->contains('calendar.index'));
        $this->assertTrue($operatorTx->contains('loads.mine'));
        $this->assertTrue($operatorTx->contains('loads.board'));
        $this->assertTrue($operatorPc->contains('loads.mine'));
        $this->assertTrue($fiscalizador->contains('repository.index'));
        $this->assertFalse($operatorTx->contains('admin.users'));
    }
}
