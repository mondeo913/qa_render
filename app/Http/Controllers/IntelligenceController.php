<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IntelligenceAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntelligenceController extends Controller
{
    public function __invoke(Request $request, IntelligenceAnalyticsService $analytics): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('intelligence.view'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'pauta_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(array_keys(IntelligenceAnalyticsService::executiveStatusOptions()))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $options = $analytics->filterOptions($user);
        $code = $user->role?->code;

        $rolePayload = match ($code) {
            'ADMINISTRADOR' => ['code'=>'ADMINISTRADOR','title'=>'Inteligencia de administración','subtitle'=>'Salud del sistema, riesgos de operación y calidad de datos.'],
            'DIRECTOR_GENERAL' => ['code'=>'DIRECTOR_GENERAL','title'=>'Radar institucional','subtitle'=>'Riesgos, prioridades y comportamiento de las cargas a nivel institucional.'],
            'ENLACE_INSTITUCIONAL' => ['code'=>'ENLACE_INSTITUCIONAL','title'=>'Inteligencia de revisión institucional','subtitle'=>'Pendientes de revisión, inconsistencias y expedientes que requieren atención.'],
            'DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD' => ['code'=>'DIRECTOR','title'=>'Inteligencia de dirección','subtitle'=>'Riesgos y pendientes de las cargas visibles para su Dirección.'],
            'OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD' => ['code'=>'OPERADOR','title'=>'Inteligencia operativa','subtitle'=>'Prioridades de sus cargas, vencimientos y evidencias que requieren acción.'],
            'FISCALIZADOR' => ['code'=>'FISCALIZADOR','title'=>'Inteligencia de fiscalización','subtitle'=>'Asignaciones, observaciones y cargas que requieren revisión.'],
            default => ['code'=>'GENERAL','title'=>'Centro de Inteligencia','subtitle'=>'Lectura operativa de la información disponible para su perfil.'],
        };

        $systemHealth = null;
        if ($code === 'ADMINISTRADOR') {
            $systemHealth = [
                'active_users' => User::query()->where('status','ACTIVE')->count(),
                'active_agencies' => $options['agencies']->count(),
                'active_units' => $options['units']->count(),
                'users_without_scope' => User::query()
                    ->where('status','ACTIVE')
                    ->whereNotIn('role_id', function ($q) {
                        $q->select('id')->from('roles')->whereIn('code',['ADMINISTRADOR','DIRECTOR_GENERAL']);
                    })
                    ->whereNull('contracting_agency_id')
                    ->whereNull('organizational_unit_id')
                    ->count(),
            ];
        }

        $payload = $analytics->forUser($user, $filters);
        $payload['role'] = $rolePayload;
        $payload['system_health'] = $systemHealth;

        return view('intelligence.index', [
            'analytics' => $payload,
            'agencies' => $options['agencies'],
            'units' => $options['units'],
            'pautas' => $options['pautas'],
            'statusOptions' => IntelligenceAnalyticsService::executiveStatusOptions(),
            'filters' => $filters,
        ]);
    }
}
