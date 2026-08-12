<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        Request $request,
        DashboardAnalyticsService $analytics
    ) {
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('reports.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    public function csv(
        Request $request,
        AccessScopeService $access
    ): StreamedResponse {
        abort_unless($request->user()->hasPermission('reports.export'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = $access->scopeLoads(
            ScheduledLoad::query()->with([
                'agency',
                'deliverables.templateRequirement',
                'deliverables.evidences.files',
            ]),
            $request->user()
        );

        if (!empty($filters['agency_id'])) {
            $query->where('contracting_agency_id', (int) $filters['agency_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('effective_open_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('effective_open_at', '<=', $filters['to']);
        }

        $query->orderBy('effective_open_at');

        ReportExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => 'LOADS',
            'format' => 'CSV',
            'filters' => $filters,
            'status' => 'GENERATED',
        ]);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Dependencia',
                'Título',
                'Apertura',
                'Cierre',
                'Estado',
                'Semáforo',
                'Avance %',
                'Servicios de pauta',
                'Entregables',
                'Evidencias',
                'Evidencias validadas',
                'Archivos',
            ]);

            $query->chunk(200, function ($loads) use ($handle) {
                foreach ($loads as $load) {
                    $deliverables = $load->deliverables;
                    $evidences = $deliverables->flatMap->evidences;
                    $validated = $evidences->filter(function ($evidence) {
                        $status = $evidence->status instanceof \BackedEnum
                            ? $evidence->status->value
                            : (string) $evidence->status;
                        return $status === 'VALIDADO';
                    })->count();
                    $files = $evidences->sum(fn ($evidence) => $evidence->files->count());

                    fputcsv($handle, [
                        $load->id,
                        $load->agency?->name,
                        $load->title,
                        $load->effective_open_at?->format('Y-m-d H:i'),
                        $load->effective_close_at?->format('Y-m-d H:i'),
                        $load->status instanceof \BackedEnum
                            ? $load->status->value
                            : $load->status,
                        $load->traffic_light instanceof \BackedEnum
                            ? $load->traffic_light->value
                            : $load->traffic_light,
                        $load->completion_percentage,
                        data_get($load->metadata, 'service_count', 0),
                        $deliverables->count(),
                        $evidences->count(),
                        $validated,
                        $files,
                    ]);
                }
            });

            fclose($handle);
        }, 'SIGET_reporte_cargas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(
        Request $request,
        DashboardAnalyticsService $analytics
    ) {
        abort_unless($request->user()->hasPermission('reports.export'), 403);

        $data = $analytics->forUser($request->user(), $request->query());

        ReportExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => 'EXECUTIVE',
            'format' => 'PDF',
            'filters' => $request->query(),
            'status' => 'GENERATED',
        ]);

        return Pdf::loadView('reports.executive-pdf', [
            'analytics' => $data,
            'generatedBy' => $request->user(),
        ])->download('SIGET_reporte_ejecutivo.pdf');
    }
}
