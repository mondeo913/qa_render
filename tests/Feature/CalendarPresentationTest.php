<?php
namespace Tests\Feature;

use App\Models\ScheduledLoad;
use App\Services\CalendarPresentationService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CalendarPresentationTest extends TestCase
{
    public function test_deadline_colors_do_not_change_workflow_or_completed_delivery(): void
    {
        $now = CarbonImmutable::parse('2026-07-15 12:00:00', config('app.timezone'));
        $service = app(CalendarPresentationService::class);
        $load = new ScheduledLoad(['status' => 'PROGRAMADA', 'traffic_light' => 'GRAY',
            'effective_open_at' => $now->addDays(2), 'effective_close_at' => $now->addDays(3)]);
        $this->assertSame('#2563eb', $service->forLoad($load, $now)['color']);
        $load->effective_close_at = $now->addHours(24);
        $this->assertSame('due', $service->forLoad($load, $now)['key']);
        $load->effective_close_at = $now->subSecond();
        $before = $load->getAttributes();
        $this->assertSame('overdue', $service->forLoad($load, $now)['key']);
        $this->assertStringContainsString('Fecha límite vencida', $service->forLoad($load, $now)['label']);
        $this->assertSame($before, $load->getAttributes());
        $load->status = 'ENTREGADA';
        $this->assertSame('review', $service->forLoad($load, $now)['key']);
        $load->status = 'VALIDADO_Y_CERRADO';
        $this->assertSame('#15803d', $service->forLoad($load, $now)['color']);
    }

    public function test_calendar_colors_follow_capture_review_signature_and_closure(): void
    {
        $now = now();
        $load = new ScheduledLoad(['effective_close_at' => $now->copy()->addDays(3)]);
        $service = app(CalendarPresentationService::class);
        foreach (['PROGRAMADA' => 'scheduled', 'ABIERTA' => 'scheduled', 'EN_CAPTURA' => 'capture',
            'PARCIALMENTE_ENTREGADA' => 'capture', 'ENTREGADA' => 'review', 'EN_REVISION_INSTITUCIONAL' => 'review',
            'LISTA_PARA_FIRMA' => 'signature', 'PENDIENTE_DOCUMENTO_FIRMADO' => 'signature',
            'VALIDADA' => 'validated', 'VALIDADO_Y_CERRADO' => 'validated', 'SUSPENDIDA' => 'blocked',
            'REPROGRAMADA' => 'blocked', 'CANCELADA' => 'blocked', 'OBSERVADA' => 'overdue', 'VENCIDA' => 'overdue',
            'REPROGRAMADA_ABIERTA' => 'scheduled', 'REPROGRAMADA_ENTREGADA' => 'review', 'REABIERTA' => 'scheduled'] as $status => $key) {
            $load->status = $status;
            $appearance = $service->forLoad($load, $now);
            $this->assertSame($key, $appearance['key'], $status);
            $this->assertNotEmpty($appearance['label']);
        }
    }
}
