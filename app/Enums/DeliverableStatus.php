<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_CAPTURA = 'EN_CAPTURA';
    case ENVIADO = 'ENVIADO';
    case EN_REVISION = 'EN_REVISION';
    case OBSERVADO = 'OBSERVADO';
    case RECHAZADO = 'RECHAZADO';
    case CORREGIDO = 'CORREGIDO';
    case VALIDADO = 'VALIDADO';
    case CERRADO = 'CERRADO';
}
