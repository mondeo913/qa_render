<?php
namespace App\Http\Controllers;
use App\Http\Requests\ClosureChecklistRequest;
use App\Http\Requests\SignedDocumentRequest;
use App\Models\ScheduledLoad;
use App\Services\InstitutionalClosureService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstitutionalClosureController extends Controller
{
    public function checklist(
        ClosureChecklistRequest $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('close',$load);
        $service->updateChecklist(
            $load,
            $request->user()->id,
            $request->boolean('evidences_correct'),
            $request->boolean('package_prepared_for_signature'),
            $request->input('observations')
        );
        return back()->with('success','Verificaciones institucionales guardadas.');
    }

    public function signaturePackage(
        Request $request,
        ScheduledLoad $load,
        ReportService $reports
    ) {
        $this->authorize('close',$load);
        $path = $reports->generateSignaturePackage($load);
        return Storage::disk(config('siget.repository_disk','local'))
            ->download($path,'expediente-firma-'.$load->id.'.pdf');
    }

    public function signedDocument(
        SignedDocumentRequest $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('close',$load);
        $service->uploadSignedDocument(
            $load,
            $request->file('file'),
            $request->user()->id,
            $request->safe()->except('file')
        );
        return back()->with('success','Documento firmado incorporado al repositorio.');
    }

    public function close(
        Request $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('close',$load);
        $service->close($load,$request->user()->id,$request->string('closing_comment')->toString());
        return back()->with('success','La carga fue validada y cerrada.');
    }
}
