<?php
namespace App\Services;
use App\Models\LoadClosure;
use App\Models\ScheduledLoad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class ReportService
{
    public function generateSignaturePackage(ScheduledLoad $load): string
    {
        $load->load(['deliverables.evidences.files','institutionalReview']);
        $pdf = Pdf::loadView('reportes.expediente-firma',compact('load'))
            ->setPaper('letter');
        $path = 'siget/reports/signature-package-'.$load->id.'.pdf';
        Storage::disk(config('siget.repository_disk','local'))->put($path,$pdf->output());
        return $path;
    }

    public function generateClosureCertificate(
        ScheduledLoad $load,
        LoadClosure $closure
    ): string {
        $load->load(['deliverables','signedDocuments.files']);
        $pdf = Pdf::loadView('reportes.constancia-cierre',compact('load','closure'))
            ->setPaper('letter');
        $path = 'siget/reports/closure-certificate-'.$load->id.'.pdf';
        Storage::disk(config('siget.repository_disk','local'))->put($path,$pdf->output());
        return $path;
    }
}
