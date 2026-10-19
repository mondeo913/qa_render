<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarImportRequest;
use App\Models\CalendarImport;
use App\Services\{AccessScopeService, CalendarImportService};
use Illuminate\Http\Request;

final class ImportController extends Controller
{
    public function store(CalendarImportRequest $request, CalendarImportService $service, AccessScopeService $access)
    {
        abort_unless($access->canManageAgency($request->user(), $request->integer('contracting_agency_id')), 403);
        $import = $service->uploadAndValidate($request->file('calendar_file'), $request->integer('contracting_agency_id'),
            $request->user()->id, $request->integer('schedule_year'));
        return response()->json(['data' => ApiData::import($import)], 201);
    }

    public function show(Request $request, CalendarImport $import)
    {
        $this->authorize('confirm', $import);
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $rows = $import->rows()->orderBy('id')->paginate($request->integer('per_page', 25));
        return response()->json(['data' => ApiData::import($import), 'rows' => $rows->getCollection()->map(fn ($row) =>
            $row->only(['id', 'sheet_name', 'row_number', 'source_column', 'delivery_name', 'original_open_at', 'original_close_at', 'is_valid', 'validation_messages'])),
            'meta' => ['total' => $rows->total(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()]]);
    }

    public function confirm(Request $request, CalendarImport $import, CalendarImportService $service)
    {
        $this->authorize('confirm', $import);
        $created = $service->confirm($import, $request->user()->id);
        return response()->json(['data' => ApiData::import($import->fresh()), 'loads_created' => $created]);
    }
}
