<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLoad;
use App\Services\EvidenceCorrectionService;
use Illuminate\Http\Request;

final class EvidenceCorrectionController extends Controller
{
    public function authorizeWindow(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service)
    {
        $this->authorize('verify', $load);
        return $this->save($request, $load, $service, false);
    }

    public function reopen(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service)
    {
        $this->authorize('reopen', $load);
        return $this->save($request, $load, $service, true);
    }

    public function reschedule(Request $request, ScheduledLoad $load, \App\Services\ReschedulingService $service)
    {
        abort_unless($request->user()->hasPermission('calendar.reschedule') && app(\App\Services\AccessScopeService::class)->canManageAgency($request->user(), $load->contracting_agency_id), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:4000'],
            'opens_at' => ['required', 'date'], 'closes_at' => ['required', 'date', 'after:opens_at', 'after:now']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $load, $service, $data) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $record = $load->reschedules()->whereIn('status', ['PENDING', 'SCHEDULED'])->latest('id')->first();
            if (!$record) {
                $record = $load->reschedules()->create(['old_open_at' => $load->effective_open_at, 'old_close_at' => $load->effective_close_at,
                    'reason' => $data['reason'], 'status' => 'PENDING']);
            } else { $record->update(['reason' => $data['reason']]); }
            $service->assignNewWindow($record, $request->date('opens_at'), $request->date('closes_at'), $request->user()->id);
        });
        return back()->with('success', 'Carga reprogramada con fechas y motivo registrados.');
    }

    private function save(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service, bool $reopen)
    {
        $data = $request->validate([
            'deliverable_ids' => ['required', 'array', 'min:1'],
            'deliverable_ids.*' => ['required', 'integer', 'distinct'],
            'reason' => ['required', 'string', 'min:10', 'max:4000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at', 'after:now'],
        ]);
        $service->authorize($load, $request->user(), $data['deliverable_ids'],
            $request->date('opens_at'), $request->date('closes_at'), $data['reason'], $reopen);
        return back()->with('success', $reopen ? 'Expediente reabierto con historial conservado.' : 'Ventana de corrección autorizada.');
    }
}
