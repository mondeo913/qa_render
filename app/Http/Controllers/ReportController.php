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

        $query = $access->scopeLoads(
            ScheduledLoad::query()->with('agency'),
            $request->user()
        )->orderBy('effective_open_at');

        ReportExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => 'LOADS',
            'format' => 'CSV',
            'filters' => $request->query(),
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
            ]);

            $query->chunk(200, function ($loads) use ($handle) {
                foreach ($loads as $load) {
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
