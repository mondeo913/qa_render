<?php

namespace App\Http\Controllers;

use App\Services\LoadBoardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class LoadBoardController extends Controller
{
    public function __invoke(Request $request, LoadBoardService $board): View
    {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer', 'min:1'],
            'unit_id' => ['nullable', 'string', 'max:500', 'regex:/^\d+(,\d+)*$/'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m'],
            'q' => ['nullable', 'string', 'max:180'],
            'mine' => ['nullable', 'boolean'],
        ]);

        if (!empty($filters['from']) && !empty($filters['to']) && $filters['from'] > $filters['to']) {
            $filters['from'] = $filters['to'];
        }

        return view('cargas.tablero', $board->forUser($request->user(), $filters));
    }
}
