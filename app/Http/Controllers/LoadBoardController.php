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
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'period' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:180'],
            'mine' => ['nullable', 'boolean'],
        ]);

        return view('cargas.tablero', $board->forUser($request->user(), $filters));
    }
}
