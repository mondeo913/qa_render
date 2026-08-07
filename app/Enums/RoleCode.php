<?php

namespace App\Enums;

enum RoleCode: string
{
    case ADMINISTRADOR = 'ADMINISTRADOR';
    case DIRECTOR_GENERAL = 'DIRECTOR_GENERAL';
    case DIRECTOR = 'DIRECTOR'; // Rol heredado durante la migración.
    case DIRECTOR_TRANSMISION = 'DIRECTOR_TRANSMISION';
    case DIRECTOR_PROGRAMACION_CONTINUIDAD = 'DIRECTOR_PROGRAMACION_CONTINUIDAD';
    case ENLACE_INSTITUCIONAL = 'ENLACE_INSTITUCIONAL';
    case OPERADOR = 'OPERADOR'; // Rol heredado durante la migración.
    case OPERADOR_TRANSMISION = 'OPERADOR_TRANSMISION';
    case OPERADOR_PROGRAMACION_CONTINUIDAD = 'OPERADOR_PROGRAMACION_CONTINUIDAD';
    case FISCALIZADOR = 'FISCALIZADOR';

    /** @return list<string> */
    public static function directionDirectorValues(): array
    {
        return [
            self::DIRECTOR->value,
            self::DIRECTOR_TRANSMISION->value,
            self::DIRECTOR_PROGRAMACION_CONTINUIDAD->value,
        ];
    }

    /** @return list<string> */
    public static function operatorValues(): array
    {
        return [
            self::OPERADOR->value,
            self::OPERADOR_TRANSMISION->value,
            self::OPERADOR_PROGRAMACION_CONTINUIDAD->value,
        ];
    }

    public static function isDirectionDirector(?string $roleCode): bool
    {
        return in_array($roleCode, self::directionDirectorValues(), true);
    }

    public static function isOperator(?string $roleCode): bool
    {
        return in_array($roleCode, self::operatorValues(), true);
    }
}
