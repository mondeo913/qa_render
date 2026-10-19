<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\ScheduledLoadStatus;
use App\Models\ContractingAgency;
use App\Models\AuditLog;
use App\Models\LoadStatusHistory;
use App\Models\OrganizationalUnit;
use App\Models\ReportExport;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function authorizeReports(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'report' => ['nullable', 'in:executive,compliance,pending,evidence,audit,builder'],
            'agency_id' => ['nullable', 'integer'],
            'pauta_id' => ['nullable', 'integer', 'min:1'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'responsible_id' => ['nullable', 'integer', 'min:1'],
            'deliverable_group' => ['nullable', 'in:pending,review,observed,validated'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);

        return $filters;
    }

    private function evidenceMetrics($loads): array
    {
        $deliverables = $loads->flatMap->deliverables;
        $status = fn ($item) => $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
        $expected = $deliverables->count();
        $received = $deliverables->filter(fn ($d) => $d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null))->count();
        $validated = $deliverables->filter(fn ($d) => in_array($status($d), ['VALIDADO', 'CERRADO'], true))->count();
        $observed = $deliverables->filter(fn ($d) => in_array($status($d), ['OBSERVADO', 'RECHAZADO'], true))->count();
        $review = $deliverables->filter(fn ($d) => in_array($status($d), ['ENVIADO', 'EN_REVISION'], true))->count();

        return [
            'expected' => $expected,
            'received' => $received,
            'validated' => $validated,
            'pending' => max(0, $expected - $received),
            'observed' => $observed,
            'review' => $review,
            'delivery_percentage' => $expected === 0 ? 0 : round($received * 100 / $expected, 1),
            'validation_percentage' => $expected === 0 ? 0 : round($validated * 100 / $expected, 1),
        ];
    }

    private function evidenceRow($load): array
    {
        $deliverables = $load->deliverables;
        $metrics = $this->evidenceMetrics(collect([$load]));

        return [
            'id' => $load->id,
            'agency' => $load->agency?->name ?: 'Sin dependencia',
            'pauta' => $load->calendarImport?->original_filename ?: 'Pauta sin nombre',
            'unit' => $deliverables->map(fn ($d) => $d->organizationalUnit?->name)->filter()->unique()->implode(' / ') ?: 'Sin unidad',
            'responsible' => $deliverables->map(fn ($d) => $d->responsibleUser?->name)->filter()->unique()->implode(' / ') ?: 'Sin responsable',
            'title' => $load->title,
            'period' => $load->period_label ?: '—',
            'status' => $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status,
            'open' => $load->effective_open_at?->format('d/m/Y H:i') ?: '—',
            'close' => $load->effective_close_at?->format('d/m/Y H:i') ?: '—',
            'expected' => $metrics['expected'],
            'received' => $metrics['received'],
            'validated' => $metrics['validated'],
            'pending' => $metrics['pending'],
            'observed' => $metrics['observed'],
            'progress' => (float) $load->completion_percentage,
            'risk' => $this->riskLevel($load),
        ];
    }

    private function reportQuery(Request $request, array $filters)
    {
        $analytics = app(DashboardAnalyticsService::class);
        $visibleDeliverables = $analytics->deliverablesForUser($request->user(), $filters)->select('scheduled_load_deliverables.id');
        return $analytics->loadsForUser($request->user(), $filters)->with([
            'agency', 'calendarImport',
            'deliverables' => fn ($q) => $q->whereIn('scheduled_load_deliverables.id', $visibleDeliverables)
                ->with(['organizationalUnit', 'responsibleUser', 'templateRequirement', 'evidences.files']),
        ]);
    }

    private function uniqueOrganizationalUnits($units)
    {
        return collect($units)
            ->filter()
            ->groupBy(function ($unit) {
                return mb_strtolower(
                    preg_replace('/\s+/', ' ', trim($unit->name))
                );
            })
            ->map(function ($group) {
                $representative = clone $group->first();

                $representative->id = $group
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->implode(',');

                return $representative;
            })
            ->sortBy('name')
            ->values();
    }

    private function reportLoads(
        Request $request,
        array $filters,
        AccessScopeService $access
    ) {
        return $this->reportQuery($request, $filters)->orderBy('scheduled_loads.effective_open_at')->get();
    }

    public function index(
        Request $request,
        DashboardAnalyticsService $analytics,
        AccessScopeService $access
    ) {
        $this->authorizeReports($request, 'reports.view');

        $filters = $this->filters($request);

        $loads = $this->reportLoads(
            $request,
            $filters,
            $access
        );

        $evidenceSummary = $this->evidenceMetrics($loads);
        $reportRows = $loads->map(fn ($load) => $this->evidenceRow($load))->values();
        $loadIds = $loads->pluck('id');
        $auditRows = AuditLog::query()
            ->with('user')
            ->where('entity_type', ScheduledLoad::class)
            ->whereIn('entity_id', $loadIds)
            ->latest()
            ->limit(100)
            ->get();
        $statusHistory = LoadStatusHistory::query()
            ->with(['user', 'scheduledLoad.agency'])
            ->whereIn('scheduled_load_id', $loadIds)
            ->latest()
            ->limit(100)
            ->get();

        $analyticsData = $analytics->forUser(
            $request->user(),
            $filters
        );

        $role = $request->user()->role?->code;

        $isGlobalRole = in_array($role, [
            RoleCode::ADMINISTRADOR->value,
            RoleCode::DIRECTOR_GENERAL->value,
        ], true);

        if ($isGlobalRole) {
            $agencies = ContractingAgency::query()
                ->where('active', true)
                ->orderBy('name')
                ->get();

            $units = OrganizationalUnit::query()
                ->with('agency')
                ->where('active', true)
                ->orderBy('name')
                ->get();
        } elseif ($role === RoleCode::ENLACE_INSTITUCIONAL->value) {
            $agencyIds = $request->user()
                ->scopes()
                ->where('can_read', true)
                ->whereNotNull('contracting_agency_id')
                ->pluck('contracting_agency_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($request->user()->contracting_agency_id) {
                $agencyIds[] = (int) $request->user()->contracting_agency_id;
            }

            $agencyIds = array_values(array_unique($agencyIds));

            $agencies = ContractingAgency::query()
                ->where('active', true)
                ->whereIn('id', $agencyIds)
                ->orderBy('name')
                ->get();

            $units = OrganizationalUnit::query()
                ->with('agency')
                ->where('active', true)
                ->whereIn('contracting_agency_id', $agencyIds)
                ->orderBy('name')
                ->get();
        } elseif (RoleCode::isDirectionDirector($role) || RoleCode::isOperator($role)) {
            // El universo ya está filtrado por AccessScopeService. Para Directores
            // esto significa todas las dependencias que tengan su misma Dirección.
            $agencies = app(\App\Services\AccessScopeService::class)->availableAgencies($request->user());

            $units = $loads
                ->flatMap(fn ($load) => $load->deliverables)
                ->map(fn ($deliverable) => $deliverable->organizationalUnit)
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();
        } else {
            $agencies = $loads
                ->map(fn ($load) => $load->agency)
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();

            $units = $loads
                ->flatMap(fn ($load) => $load->deliverables)
                ->map(fn ($deliverable) => $deliverable->organizationalUnit)
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $units = $this->uniqueOrganizationalUnits($units);

        $statuses = collect(ScheduledLoadStatus::cases())
            ->mapWithKeys(fn (ScheduledLoadStatus $status) => [
                $status->value => $status->value,
            ]);

        $allowedStatuses = [
            'PROGRAMADA',
            'REPROGRAMADA',
            'VALIDADO_Y_CERRADO',
            'VENCIDA',
            'CANCELADA',
        ];

        $statuses = $statuses->filter(
            fn ($label, $code) => in_array($code, $allowedStatuses, true)
        );

        $users = $loads
            ->flatMap(fn ($load) => $load->deliverables)
            ->map(fn ($deliverable) => $deliverable->responsibleUser)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $pautas = $loads
            ->loadMissing('calendarImport')
            ->groupBy('calendar_import_id')
            ->map(fn ($group) => [
                'id' => (int) $group->first()->calendar_import_id,
                'name' => (string) ($group->first()->calendarImport?->original_filename ?: 'Pauta sin nombre'),
                'agency_id' => (int) $group->first()->contracting_agency_id,
                'agency' => (string) ($group->first()->agency?->name ?: 'Sin dependencia'),
            ])
            ->values();

        return view('reports.index', [
            'analytics' => $analyticsData,
            'evidenceSummary' => $evidenceSummary,
            'reportRows' => $reportRows,
            'auditRows' => $auditRows,
            'statusHistory' => $statusHistory,
            'filters' => $filters,
            'loads' => $loads,
            'agencies' => $agencies,
            'units' => $units,
            'statuses' => $statuses,
            'users' => $users,
            'pautas' => $pautas,
            'role' => $role,
            'canBuildReports' => $role === RoleCode::ADMINISTRADOR->value,
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }

    public function csv(
        Request $request,
        AccessScopeService $access
    ): StreamedResponse {
        $this->authorizeReports($request, 'reports.export');
        $filters = $this->filters($request);

        $query = $this->reportQuery($request, $filters)->orderBy('scheduled_loads.effective_open_at');

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
                'Pauta (Excel)',
                'Dirección/Unidad',
                'Responsable',
                'Título',
                'Apertura',
                'Cierre',
                'Estado',
                'Riesgo',
                'Avance %',
                'Entregables',
                'Evidencias esperadas',
                'Evidencias',
                'Evidencias validadas',
                'Evidencias pendientes',
                'Evidencias observadas',
                'Archivos',
            ]);

            $query->chunk(200, function ($loads) use ($handle) {
                foreach ($loads as $load) {
                    $deliverables = $load->deliverables;
                    $evidences = $deliverables->flatMap->evidences;

                    $metrics = $this->evidenceMetrics(collect([$load]));

                    $files = $evidences
                        ->sum(fn ($evidence) => $evidence->files->count());

                    $units = $deliverables
                        ->map(fn ($d) => $d->organizationalUnit?->name)
                        ->filter()
                        ->unique()
                        ->implode(' / ');

                    $users = $deliverables
                        ->map(fn ($d) => $d->responsibleUser?->name)
                        ->filter()
                        ->unique()
                        ->implode(' / ');

                    fputcsv($handle, [
                        $load->id,
                        $load->agency?->name,
                        $load->calendarImport?->original_filename ?: 'Pauta sin nombre',
                        $units,
                        $users,
                        $load->title,
                        $load->effective_open_at?->format('Y-m-d H:i'),
                        $load->effective_close_at?->format('Y-m-d H:i'),
                        $load->status instanceof \BackedEnum
                            ? $load->status->value
                            : $load->status,
                        $this->riskLevel($load),
                        $load->completion_percentage,
                        $deliverables->count(),
                        $deliverables->count(),
                        $metrics['received'],
                        $metrics['validated'],
                        $metrics['pending'],
                        $metrics['observed'],
                        $files,
                    ]);
                }
            });

            fclose($handle);
        }, 'SIGET_reporte_cargas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function xlsx(
        Request $request,
        AccessScopeService $access
    ) {
        $this->authorizeReports($request, 'reports.export');
        $filters = $this->filters($request);
        $loads = $this->reportLoads($request, $filters, $access);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte SIGET');

        $headers = [
            'ID',
            'Dependencia',
            'Pauta (Excel)',
            'Dirección/Unidad',
            'Responsable',
            'Título',
            'Apertura',
            'Cierre',
            'Estado',
            'Riesgo',
            'Avance %',
            'Entregables',
            'Evidencias esperadas',
            'Evidencias',
            'Validadas',
            'Pendientes',
            'Observadas',
            'Archivos',
        ];

        foreach ($headers as $column => $header) {
            $sheet->setCellValue([$column + 1, 1], $header);
        }

        $row = 2;

        foreach ($loads as $load) {
            $deliverables = $load->deliverables;
            $evidences = $deliverables->flatMap->evidences;

            $units = $deliverables
                ->map(fn ($d) => $d->organizationalUnit?->name)
                ->filter()
                ->unique()
                ->implode(' / ');

            $users = $deliverables
                ->map(fn ($d) => $d->responsibleUser?->name)
                ->filter()
                ->unique()
                ->implode(' / ');

            $metrics = $this->evidenceMetrics(collect([$load]));

            $files = $evidences
                ->sum(fn ($evidence) => $evidence->files->count());

            $values = [
                $load->id,
                $load->agency?->name,
                $load->calendarImport?->original_filename ?: 'Pauta sin nombre',
                $units,
                $users,
                $load->title,
                $load->effective_open_at?->format('Y-m-d H:i'),
                $load->effective_close_at?->format('Y-m-d H:i'),
                $load->status instanceof \BackedEnum
                    ? $load->status->value
                    : $load->status,
                $this->riskLevel($load),
                $load->completion_percentage,
                $deliverables->count(),
                $deliverables->count(),
                $metrics['received'],
                $metrics['validated'],
                $metrics['pending'],
                $metrics['observed'],
                $files,
            ];

            foreach ($values as $column => $value) {
                $sheet->setCellValue([$column + 1, $row], $value);
            }

            $row++;
        }

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        ReportExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => 'LOADS',
            'format' => 'XLSX',
            'filters' => $filters,
            'status' => 'GENERATED',
        ]);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            'SIGET_reporte.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function pdf(
        Request $request,
        DashboardAnalyticsService $analytics,
        AccessScopeService $access
    ) {
        $this->authorizeReports($request, 'reports.export');
        $filters = $this->filters($request);
        $loads = $this->reportLoads($request, $filters, $access);
        $evidenceSummary = $this->evidenceMetrics($loads);
        $reportRows = $loads->map(fn ($load) => $this->evidenceRow($load))->values();

        $data = $analytics->forUser(
            $request->user(),
            $filters
        );

        ReportExport::query()->create([
            'requested_by' => $request->user()->id,
            'report_type' => 'EXECUTIVE',
            'format' => 'PDF',
            'filters' => $filters,
            'status' => 'GENERATED',
        ]);

        return Pdf::loadView(
            'reports.executive-pdf',
            [
                'analytics' => $data,
                'evidenceSummary' => $evidenceSummary,
                'reportRows' => $reportRows,
                'filters' => $filters,
                'generatedBy' => $request->user(),
            ]
        )->download('SIGET_reporte_ejecutivo.pdf');
    }

    private function riskLevel(ScheduledLoad $load): string
    {
        $status = $load->status instanceof \BackedEnum
            ? $load->status->value
            : (string) $load->status;

        if ($status === 'VENCIDA') {
            return 'ALTO';
        }

        if (in_array($status, ['OBSERVADA', 'REPROGRAMADA'], true)) {
            return 'MEDIO';
        }

        if (
            $load->effective_close_at &&
            $load->effective_close_at->isFuture() &&
            now()->diffInHours($load->effective_close_at, false) <= 72
        ) {
            return 'ATENCIÓN';
        }

        if ($status === 'PENDIENTE_DOCUMENTO_FIRMADO') {
            return 'ATENCIÓN';
        }

        return 'NORMAL';
    }
}
