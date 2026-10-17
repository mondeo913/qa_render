<?php
namespace App\Services;

use App\Models\ScheduledLoad;
use Carbon\CarbonInterface;

final class CalendarPresentationService
{
    public const LEGEND = [
        'scheduled' => ['label' => 'Programada / abierta', 'color' => '#2563eb'],
        'capture' => ['label' => 'En captura', 'color' => '#0369a1'],
        'review' => ['label' => 'Entregada / en revisión', 'color' => '#7c3aed'],
        'signature' => ['label' => 'Pendiente de firma', 'color' => '#c2410c'],
        'validated' => ['label' => 'Validada / cerrada', 'color' => '#15803d'],
        'due' => ['label' => 'Próxima a vencer', 'color' => '#b45309'],
        'overdue' => ['label' => 'Vencida / observada', 'color' => '#b91c1c'],
        'blocked' => ['label' => 'Suspendida / reprogramada / cancelada', 'color' => '#64748b'],
    ];

    public function forLoad(ScheduledLoad $load, CarbonInterface $moment): array
    {
        $status = $load->status instanceof \BackedEnum ? $load->status->value : $load->status;
        $key = match ($status) {
            'VALIDADA', 'VALIDADO_Y_CERRADO' => 'validated',
            'SUSPENDIDA', 'REPROGRAMADA', 'CANCELADA' => 'blocked',
            'OBSERVADA', 'VENCIDA' => 'overdue',
            'LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO' => 'signature',
            'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'REPROGRAMADA_ENTREGADA' => 'review',
            'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA' => 'capture',
            default => 'scheduled',
        };
        // Display deadline warnings without mutating workflow state on a GET.
        if (in_array($key, ['capture', 'scheduled'], true)) {
            if ($load->is_blocked) $key = 'blocked';
            elseif ($load->effective_close_at?->lt($moment)) $key = 'overdue';
            elseif ($load->effective_close_at?->lte($moment->copy()->addHours(24))) $key = 'due';
        }
        $statusLabel = match ($status) {
            'PROGRAMADA' => 'Programada', 'ABIERTA' => 'Abierta', 'EN_CAPTURA' => 'En captura',
            'PARCIALMENTE_ENTREGADA' => 'Parcialmente entregada', 'ENTREGADA' => 'Entregada',
            'EN_REVISION_INSTITUCIONAL' => 'En revisión institucional', 'OBSERVADA' => 'Observada',
            'LISTA_PARA_FIRMA' => 'Lista para firma', 'PENDIENTE_DOCUMENTO_FIRMADO' => 'Pendiente de documento firmado',
            'VALIDADA' => 'Validada', 'VALIDADO_Y_CERRADO' => 'Validada y cerrada',
            'SUSPENDIDA' => 'Suspendida', 'REPROGRAMADA' => 'Reprogramada',
            'REPROGRAMADA_ABIERTA' => 'Reprogramada abierta', 'REPROGRAMADA_ENTREGADA' => 'Reprogramada entregada',
            'VENCIDA' => 'Vencida', 'CANCELADA' => 'Cancelada', 'REABIERTA' => 'Reabierta',
            default => (string) $status,
        };
        $deadlineLabel = $key === 'overdue' && !in_array($status, ['OBSERVADA', 'VENCIDA'], true)
            ? 'Fecha límite vencida' : ($key === 'due' ? 'Próxima a vencer' : null);
        return ['key' => $key, 'color' => self::LEGEND[$key]['color'], 'statusLabel' => $statusLabel,
            'label' => $statusLabel.($deadlineLabel ? ' · '.$deadlineLabel : '')];
    }
}
