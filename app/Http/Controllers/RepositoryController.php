<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Services\RepositoryBrowserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index(Request $request, RepositoryBrowserService $repository): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:60'],
            'agency_id' => ['nullable', 'integer', 'min:1'],
            'pauta_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $role = $request->user()->role?->code;
        $directionScoped = RoleCode::isOperator($role) || RoleCode::isDirectionDirector($role);
        $repositoryTitle = $directionScoped ? 'Repositorio de mi Dirección'
            : ($role === 'FISCALIZADOR' ? 'Repositorio de asignaciones' : 'Repositorio institucional');

        return view('repositorio.index', $repository->forUser($request->user(), $filters)
            + compact('directionScoped', 'repositoryTitle'));
    }
}
