<?php

namespace App\Support;

final class RoleMenu
{
    public static function for(string $roleCode): array
    {
        return match ($roleCode) {
            'ADMINISTRADOR' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-grid'],
                ['label' => 'Calendario y pautas', 'route' => 'calendar.index', 'icon' => 'bi-calendar3'],
                ['label' => 'Tablero de cargas', 'route' => 'loads.board', 'icon' => 'bi-kanban'],
                ['label' => 'Repositorio', 'route' => 'repository.index', 'icon' => 'bi-folder2-open'],
                ['label' => 'Inteligencia', 'route' => 'intelligence', 'icon' => 'bi-graph-up-arrow'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'bi-file-bar-graph'],
                ['label' => 'Notificaciones', 'route' => 'alerts.index', 'icon' => 'bi-bell'],
                ['label' => 'Usuarios', 'route' => 'admin.users', 'icon' => 'bi-people'],
                ['label' => 'Roles', 'route' => 'admin.roles', 'icon' => 'bi-shield-lock'],
                ['label' => 'Dependencias', 'route' => 'admin.agencies', 'icon' => 'bi-building'],
                ['label' => 'Plantillas', 'route' => 'admin.templates', 'icon' => 'bi-file-earmark-text'],
                ['label' => 'Catálogos', 'route' => 'admin.catalogs', 'icon' => 'bi-list-columns'],
                ['label' => 'Configuración', 'route' => 'admin.settings', 'icon' => 'bi-gear'],
                ['label' => 'Auditoría', 'route' => 'admin.logs', 'icon' => 'bi-journal-text'],
                ['label' => 'Operaciones', 'route' => 'operations.index', 'icon' => 'bi-activity'],
            ],
            'DIRECTOR_GENERAL' => [
                ['label' => 'Dashboard ejecutivo', 'route' => 'dashboard', 'icon' => 'bi-speedometer2'],
                ['label' => 'Centro de Inteligencia', 'route' => 'intelligence', 'icon' => 'bi-speedometer2'],
                ['label' => 'Indicadores', 'route' => 'indicators.index', 'icon' => 'bi-bar-chart'],
                ['label' => 'Tablero institucional', 'route' => 'loads.board', 'icon' => 'bi-kanban'],
                ['label' => 'Repositorio institucional', 'route' => 'repository.index', 'icon' => 'bi-folder2-open'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'bi-file-bar-graph'],
            ],
            'DIRECTOR',
            'DIRECTOR_TRANSMISION',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD' => [
                ['label' => self::directorDashboardLabel($roleCode), 'route' => 'dashboard', 'icon' => 'bi-grid'],
                ['label' => 'Calendario', 'route' => 'calendar.index', 'icon' => 'bi-calendar3'],
                ['label' => 'Tablero de dirección', 'route' => 'loads.board', 'icon' => 'bi-kanban'],
                ['label' => 'Repositorio de dirección', 'route' => 'repository.index', 'icon' => 'bi-folder2-open'],
                ['label' => 'Indicadores', 'route' => 'indicators.index', 'icon' => 'bi-bar-chart'],
                ['label' => 'Notificaciones', 'route' => 'alerts.index', 'icon' => 'bi-bell'],
                ['label' => 'Historial', 'route' => 'history.index', 'icon' => 'bi-clock-history'],
            ],
            'ENLACE_INSTITUCIONAL' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-grid'],
                ['label' => 'Calendario y pautas', 'route' => 'calendar.index', 'icon' => 'bi-calendar3'],
                ['label' => 'Bandeja de revisión', 'route' => 'review.inbox', 'icon' => 'bi-clipboard-check'],
                ['label' => 'Tablero de cargas', 'route' => 'loads.board', 'icon' => 'bi-kanban'],
                ['label' => 'Plantillas', 'route' => 'admin.templates', 'icon' => 'bi-file-earmark-text'],
                ['label' => 'Repositorio de revisión', 'route' => 'repository.index', 'icon' => 'bi-files'],
                ['label' => 'Notificaciones', 'route' => 'alerts.index', 'icon' => 'bi-bell'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'bi-file-bar-graph'],
                ['label' => 'Indicadores', 'route' => 'indicators.index', 'icon' => 'bi-bar-chart'],
                ['label' => 'Historial', 'route' => 'history.index', 'icon' => 'bi-clock-history'],
                ['label' => 'Operaciones', 'route' => 'operations.index', 'icon' => 'bi-activity'],
            ],
            'OPERADOR',
            'OPERADOR_TRANSMISION',
            'OPERADOR_PROGRAMACION_CONTINUIDAD' => [
                ['label' => 'Inicio', 'route' => 'dashboard', 'icon' => 'bi-house'],
                ['label' => 'Calendario', 'route' => 'calendar.index', 'icon' => 'bi-calendar3'],
                ['label' => 'Tablero de cargas', 'route' => 'loads.board', 'icon' => 'bi-kanban'],
                ['label' => 'Mis cargas', 'route' => 'loads.mine', 'icon' => 'bi-check2-square'],
                ['label' => 'Repositorio', 'route' => 'repository.index', 'icon' => 'bi-folder2-open'],
                ['label' => 'Historial', 'route' => 'history.index', 'icon' => 'bi-clock-history'],
                ['label' => 'Notificaciones', 'route' => 'alerts.index', 'icon' => 'bi-bell'],
            ],
            'FISCALIZADOR' => [
                ['label' => 'Asignaciones', 'route' => 'repository.index', 'icon' => 'bi-search'],
                ['label' => 'Historial', 'route' => 'history.index', 'icon' => 'bi-clock-history'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'bi-file-bar-graph'],
            ],
            default => [],
        };
    }

    private static function directorDashboardLabel(string $roleCode): string
    {
        return match ($roleCode) {
            'DIRECTOR_TRANSMISION' => 'Dashboard de Transmisión',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'Dashboard de Programación y Continuidad',
            default => 'Dashboard de dirección',
        };
    }
}
