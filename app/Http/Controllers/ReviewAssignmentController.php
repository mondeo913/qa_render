<?php

namespace App\Http\Controllers;

use App\Models\ReviewAssignment;
use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewAssignmentController extends Controller
{
    public function store(
        Request $request,
        ScheduledLoad $load,
        AccessScopeService $access
    ): \Symfony\Component\HttpFoundation\Response {
        abort_unless(
            in_array(
                $request->user()->role?->code,
                ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'],
                true
            ),
            403
        );

        abort_unless($access->canAccessLoad($request->user(), $load), 403);

        $data = $request->validate([
            'fiscalizador_id' => [
                'required',
                'exists:users,id',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $fiscalizador = User::query()
            ->whereKey($data['fiscalizador_id'])->where('status', 'ACTIVE')
            ->whereHas('role', fn ($role) => $role->where('code', 'FISCALIZADOR'))
            ->firstOrFail();

        ReviewAssignment::query()->updateOrCreate(
            [
                'scheduled_load_id' => $load->id,
                'fiscalizador_id' => $fiscalizador->id,
            ],
            [
                'assigned_by' => $request->user()->id,
                'active' => true,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return $request->is('api/*')
            ? response()->json(['message' => 'Fiscalizador asignado.'])
            : back()->with('success', 'Fiscalizador asignado.');
    }
}
