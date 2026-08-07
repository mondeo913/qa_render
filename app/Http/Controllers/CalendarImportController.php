<?php
namespace App\Http\Controllers;
use App\Http\Requests\CalendarImportRequest;
use App\Models\CalendarImport;
use App\Services\CalendarImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class CalendarImportController extends Controller
{
    public function store(
        CalendarImportRequest $request,
        CalendarImportService $service
    ): RedirectResponse {
        $import = $service->uploadAndValidate(
            $request->file('calendar_file'),
            $request->integer('contracting_agency_id'),
            $request->user()->id,
            $request->integer('schedule_year')
        );
        return redirect()->route('calendar.import.preview',$import);
    }

    public function preview(CalendarImport $import): View
    {
        $this->authorize('confirm',$import);
        return view('calendario.import-preview',[
            'import'=>$import->load('rows'),
        ]);
    }

    public function confirm(
        Request $request,
        CalendarImport $import,
        CalendarImportService $service
    ): RedirectResponse {
        $this->authorize('confirm',$import);
        $created = $service->confirm($import,$request->user()->id);
        return redirect()->route('calendar.index')
            ->with('success',"Se crearon {$created} cargas programadas.");
    }
}
