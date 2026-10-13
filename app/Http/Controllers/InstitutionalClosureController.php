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
use RuntimeException;

class InstitutionalClosureController extends Controller
{
    public function checklist(
        ClosureChecklistRequest $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('verify',$load);
        $service->updateChecklist(
            $load,
            $request->user()->id,
            $request->boolean('evidences_correct'),
            $request->boolean('package_prepared_for_signature'),
            $request->input('observations')
        );
        return back()->with('success','Verificaciones institucionales del expediente completo guardadas.');
    }

    public function signaturePackage(
        Request $request,
        ScheduledLoad $load,
        ReportService $reports,
        InstitutionalClosureService $service
    ) {
        $this->authorize('signaturePackage',$load);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($load, $reports, $service) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $validation = $service->validateExpediente($load);
            if ($load->is_blocked || !$validation['ready'] || !$load->institutionalReview?->verified_at) {
                throw new \App\Exceptions\WorkflowException('Complete la validación institucional del expediente antes de generar el paquete para firma.');
            }
            $path = $reports->generateSignaturePackage($load);
            return Storage::disk(config('siget.repository_disk','local'))->download($path,'expediente-firma-'.$load->id.'.pdf');
        });
    }

    public function signedDocument(
        SignedDocumentRequest $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('uploadSigned',$load);
        $service->uploadSignedDocument(
            $load,
            $request->file('file'),
            $request->user()->id,
            $request->safe()->except('file')
        );
        return back()->with('success','Documento firmado incorporado al expediente de la dependencia.');
    }

    public function close(
        Request $request,
        ScheduledLoad $load,
        InstitutionalClosureService $service
    ): RedirectResponse {
        $this->authorize('close',$load);
        $service->close($load,$request->user()->id,$request->string('closing_comment')->toString());
        return back()->with('success','El expediente completo de la dependencia fue validado y cerrado.');
    }
}
