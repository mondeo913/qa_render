<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\ScheduledLoadStatus;
use App\Models\ContractingAgency;
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
        return $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function applyFilters(
        $query,
        array $filters
    ) {
        if (!empty($filters['agency_id'])) {
            $query->where(
                'scheduled_loads.contracting_agency_id',
                (int) $filters['agency_id']
            );
        }

        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(
                explode(',', (string) $filters['organizational_unit_id'])
            )
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($unitIds) {
                $query->whereHas(
                    'deliverables',
                    fn ($deliverables) => $deliverables->whereIn(
                        'organizational_unit_id',
                        $unitIds
                    )
                );
            }
        }

        if (!empty($filters['status'])) {
            $query->where(
                'scheduled_loads.status',
                $filters['status']
            );
        }

        if (!empty($filters['from'])) {
            $query->whereDate(
                'scheduled_loads.effective_open_at',
                '>=',
                $filters['from']
            );
        }

        if (!empty($filters['to'])) {
            $query->whereDate(
                'scheduled_loads.effective_open_at',
                '<=',
                $filters['to']
            );
        }

        return $query;
    }

    private function uniqueOrganizationalUnits($units)
    {
        return collect($units)
            ->filter()
            ->groupBy(function ($unit) {
                return mb_strtolower(
                    preg_replace('/\\s+/', ' ', trim($unit->name))
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
        $query = $access->scopeLoads(
            ScheduledLoad::query()->with([
                'agency',
                'deliverables.organizationalUnit',
                'deliverables.responsibleUser',
                'deliverables.templateRequirement',
                'deliverables.evidences.files',
            ]),
            $request->user()
        );

        return $this->applyFilters($query, $filters)
            ->orderBy('scheduled_loads.effective_open_at')
            ->get();
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

        $analyticsData = $analytics->forUser(
            $request->user(),
            $filters
        );

        $role = $request->user()->role?->code;

        /*
         * Los filtros se construyen desde los catálogos institucionales
         * y no desde las cargas ya filtradas. De esta forma permanecen
         * disponibles aunque el universo actual de cargas sea 0.
         */
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
        } elseif (
            RoleCode::isDirectionDirector($role) ||
            RoleCode::isOperator($role)
        ) {
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

            $unitIds = $access->accessibleUnitIds($request->user());

            $agencies = ContractingAgency::query()
                ->where('active', true)
                ->whereIn('id', $agencyIds)
                ->orderBy('name')
                ->get();

            $units = OrganizationalUnit::query()
                ->with('agency')
                ->where('active', true)
                ->whereIn('id', $unitIds)
                ->whereIn('contracting_agency_id', $agencyIds)
                ->orderBy('name')
                ->get();
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

        /*
         * Normalización institucional:
         *
         * Una Dirección puede tener varios registros de
         * organizational_units, uno por dependencia/agencia.
         *
         * En Reportes se muestra una sola vez y se conservan
         * internamente todos sus IDs equivalentes.
         */
        $units = $this->uniqueOrganizationalUnits($units);

        /*
         * Los estados son un catálogo del dominio SIGET.
         * No dependen de que actualmente existan cargas.
         */
        $statuses = collect(ScheduledLoadStatus::cases())
            ->mapWithKeys(fn (ScheduledLoadStatus $status) => [
                $status->value => $status->value,
            ]);

        /*
         * Catálogo único de estados visibles en Reportes.
         *
         * La restricción aplica a TODOS los roles.
         * El alcance de información de cada usuario continúa
         * siendo responsabilidad de AccessScopeService.
         *
         * VALIDADA queda deliberadamente fuera.
         */
        $allowedStatuses = [
            'PROGRAMADA',
            'REPROGRAMADA',
            'VALIDADO_Y_CERRADO',
            'VENCIDA',
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

        return view('reports.index', [
            'analytics' => $analyticsData,
            'filters' => $filters,
            'loads' => $loads,
            'agencies' => $agencies,
            'units' => $units,
            'statuses' => $statuses,
            'users' => $users,
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

        $query = $access->scopeLoads(
            ScheduledLoad::query()->with([
                'agency',
                'deliverables.organizationalUnit',
                'deliverables.responsibleUser',
                'deliverables.evidences.files',
            ]),
            $request->user()
        );

        $query = $this->applyFilters($query, $filters)
            ->orderBy('effective_open_at');

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
                'Dirección/Unidad',
                'Responsable',
                'Título',
                'Apertura',
                'Cierre',
                'Estado',
                'Riesgo',
                'Avance %',
                'Entregables',
                'Evidencias',
                'Evidencias validadas',
                'Archivos',
            ]);

            $query->chunk(200, function ($loads) use ($handle) {
                foreach ($loads as $load) {
                    $deliverables = $load->deliverables;
                    $evidences = $deliverables->flatMap->evidences;

                    $validated = $evidences
                        ->filter(function ($evidence) {
                            $status = $evidence->status instanceof \BackedEnum
                                ? $evidence->status->value
                                : (string) $evidence->status;

                            return $status === 'VALIDADO';
                        })
                        ->count();

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
            'Dirección/Unidad',
            'Responsable',
            'Título',
            'Apertura',
            'Cierre',
            'Estado',
            'Riesgo',
            'Avance %',
            'Entregables',
            'Evidencias',
            'Validadas',
            'Archivos',
        ];

        foreach ($headers as $column => $header) {
            $sheet
                ->setCellValueByColumnAndRow(
                    $column + 1,
                    1,
                    $header
                );
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

            $validated = $evidences
                ->filter(function ($evidence) {
                    $status = $evidence->status instanceof \BackedEnum
                        ? $evidence->status->value
                        : (string) $evidence->status;

                    return $status === 'VALIDADO';
                })
                ->count();

            $files = $evidences
                ->sum(fn ($evidence) => $evidence->files->count());

            $values = [
                $load->id,
                $load->agency?->name,
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
                $evidences->count(),
                $validated,
                $files,
            ];

            foreach ($values as $column => $value) {
                $sheet->setCellValueByColumnAndRow(
                    $column + 1,
                    $row,
                    $value
                );
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
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function pdf(
        Request $request,
        DashboardAnalyticsService $analytics
    ) {
        $this->authorizeReports($request, 'reports.export');

        $filters = $this->filters($request);

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
