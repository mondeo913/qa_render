<?php
namespace App\Support;
use App\Enums\RoleCode;
final class RolePresentation {
    public static function for(?string $roleCode): array {
        return match ($roleCode) {
            RoleCode::DIRECTOR_GENERAL->value => ['title'=>'Centro ejecutivo institucional','subtitle'=>'Supervisión consolidada de las dos direcciones y todas las dependencias.','scope'=>'Alcance global','action'=>['route'=>'loads.board','label'=>'Tablero institucional','icon'=>'bi-kanban']],
            RoleCode::ENLACE_INSTITUCIONAL->value => ['title'=>'Centro de revisión institucional','subtitle'=>'Pautas, cargas recibidas, observaciones, validaciones y cierres.','scope'=>'Dependencias asignadas','action'=>['route'=>'loads.board','label'=>'Revisar cargas','icon'=>'bi-clipboard-check']],
            RoleCode::DIRECTOR_TRANSMISION->value => ['title'=>'Dashboard de Transmisión','subtitle'=>'Seguimiento de cumplimiento, cargas y riesgos de la Dirección de Transmisión.','scope'=>'Dirección de Transmisión','action'=>['route'=>'loads.board','label'=>'Tablero de dirección','icon'=>'bi-kanban']],
            RoleCode::DIRECTOR_PROGRAMACION_CONTINUIDAD->value => ['title'=>'Dashboard de Programación y Continuidad','subtitle'=>'Seguimiento de cumplimiento, cargas y riesgos de Programación y Continuidad.','scope'=>'Programación y Continuidad','action'=>['route'=>'loads.board','label'=>'Tablero de dirección','icon'=>'bi-kanban']],
            RoleCode::OPERADOR_TRANSMISION->value => ['title'=>'Mi operación de Transmisión','subtitle'=>'Cargas programadas, evidencias pendientes y fechas de entrega.','scope'=>'Cargas asignadas','action'=>['route'=>'loads.board','label'=>'Abrir mis cargas','icon'=>'bi-check2-square']],
            RoleCode::OPERADOR_PROGRAMACION_CONTINUIDAD->value => ['title'=>'Mi operación de Programación y Continuidad','subtitle'=>'Cargas programadas, evidencias pendientes y fechas de entrega.','scope'=>'Cargas asignadas','action'=>['route'=>'loads.board','label'=>'Abrir mis cargas','icon'=>'bi-check2-square']],
            RoleCode::FISCALIZADOR->value => ['title'=>'Bandeja de fiscalización','subtitle'=>'Asignaciones de revisión especializada y trazabilidad documental.','scope'=>'Asignaciones activas','action'=>['route'=>'repository.index','label'=>'Abrir asignaciones','icon'=>'bi-search']],
            RoleCode::ADMINISTRADOR->value => ['title'=>'Administración y operación de SIGET','subtitle'=>'Estado general, usuarios, catálogos, seguridad y operación de la plataforma.','scope'=>'Administración global','action'=>['route'=>'operations.index','label'=>'Centro de operaciones','icon'=>'bi-activity']],
            default => ['title'=>'Dashboard de seguimiento','subtitle'=>'Estado de las cargas y evidencias dentro de su alcance autorizado.','scope'=>'Mi alcance','action'=>['route'=>'loads.board','label'=>'Ver tablero','icon'=>'bi-kanban']],
        };
    }
}
