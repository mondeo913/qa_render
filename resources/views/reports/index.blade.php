@extends('layouts.app')

@section('title', 'Reportes SIGET')
@section('page-title', 'Reportes SIGET')
@section('page-subtitle', 'Información operativa, institucional y de cumplimiento')

@section('content')

@php
    $k = $analytics['kpis'] ?? [];
    $status = collect($analytics['status_distribution'] ?? []);
    $monthly = collect($analytics['monthly_trend'] ?? []);
    $unitsPerformance = collect($analytics['unit_performance'] ?? []);
    $agenciesPerformance = collect($analytics['agency_performance'] ?? []);

    $total = (int) ($k['total'] ?? 0);
    $active = (int) ($k['active'] ?? 0);
    $closed = (int) ($k['closed'] ?? 0);
    $overdue = (int) ($k['overdue'] ?? 0);
    $reprogrammed = (int) ($k['reprogrammed'] ?? 0);
    $completion = (float) ($k['completion_average'] ?? 0);
    $compliance = (float) ($k['compliance'] ?? 0);

    $executiveRows = $agenciesPerformance;
    $riskLoads = $loads->filter(function ($load) {
        $status = $load->status instanceof \BackedEnum
            ? $load->status->value
            : (string) $load->status;

        return in_array($status, [
            'VENCIDA',
            'OBSERVADA',
            'REPROGRAMADA',
            'PENDIENTE_DOCUMENTO_FIRMADO',
        ], true);
    });

    $evidenceRows = $loads->flatMap(function ($load) {
        return $load->deliverables->map(function ($deliverable) use ($load) {
            return [
                'load' => $load,
                'deliverable' => $deliverable,
                'unit' => $deliverable->organizationalUnit,
                'user' => $deliverable->responsibleUser,
                'evidences' => $deliverable->evidences,
            ];
        });
    });
@endphp

<style>
.siget-reports{
    background:linear-gradient(180deg,#09131f 0%,#0b1119 100%);
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    padding:18px;
    color:#eef5fa;
}
.siget-reports .hero{
    background:linear-gradient(100deg,#0e2533,#10283b 55%,#0d1a27);
    border:1px solid rgba(33,198,216,.22);
    border-radius:16px;
    padding:20px;
    margin-bottom:16px;
}
.siget-reports .hero h2{color:#fff;font-weight:700}
.siget-reports .muted{color:#93a7b9;font-size:.76rem}
.siget-reports .filters{
    background:#0f1c29;
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;
    padding:15px;
    margin-bottom:16px;
}
.siget-reports .filter-label{
    color:#8499aa;
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.05em;
    margin-bottom:5px;
}
.siget-reports .nav-reportes{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:16px;
}
.siget-reports .report-tab{
    border:1px solid rgba(255,255,255,.1);
    background:#101c28;
    color:#aebdca;
    border-radius:10px;
    padding:10px 13px;
    font-size:.72rem;
    cursor:pointer;
}
.siget-reports .report-tab.active{
    background:#17465a;
    color:#fff;
    border-color:#2db8d2;
}
.siget-reports .report-panel{display:none}
.siget-reports .report-panel.active{display:block}
.siget-reports .report-card{
    background:#101d2a;
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;
    overflow:hidden;
}
.siget-reports .report-head{
    padding:15px 17px;
    border-bottom:1px solid rgba(255,255,255,.08);
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
}
.siget-reports .report-head h3{
    font-size:.98rem;
    margin:0;
    color:#fff;
}
.siget-reports .report-head p{
    margin:4px 0 0;
    color:#8ea4b7;
    font-size:.68rem;
}
.siget-reports .kpis{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:10px;
    margin-bottom:16px;
}
.siget-reports .kpi{
    background:#111f2d;
    border:1px solid rgba(255,255,255,.08);
    border-radius:12px;
    padding:13px;
}
.siget-reports .kpi small{color:#8fa5b7;font-size:.64rem}
.siget-reports .kpi strong{
    display:block;
    color:#fff;
    font-size:1.45rem;
    margin-top:4px;
}
.siget-reports table{
    --bs-table-bg:transparent;
    --bs-table-color:#edf5f9;
    --bs-table-border-color:rgba(255,255,255,.07);
    font-size:.72rem;
    margin-bottom:0;
}
.siget-reports th{
    color:#8297aa!important;
    font-size:.6rem;
    text-transform:uppercase;
}
.siget-reports .empty{
    padding:45px 20px;
    text-align:center;
    color:#8fa5b7;
}
.siget-reports .builder-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.siget-reports .builder-box{
    background:#0d1925;
    border:1px solid rgba(255,255,255,.08);
    border-radius:12px;
    padding:13px;
}
.siget-reports .builder-box h4{
    color:#fff;
    font-size:.74rem;
    margin-bottom:10px;
}
.siget-reports .builder-options{
    display:grid;
    gap:7px;
}
.siget-reports .builder-options label{
    color:#b7c5d0;
    font-size:.68rem;
}
.siget-reports .builder-preview{
    min-height:220px;
    margin-top:15px;
    background:#0d1722;
    border:1px dashed rgba(255,255,255,.12);
    border-radius:12px;
    padding:15px;
}
@media(max-width:1100px){
    .siget-reports .kpis{grid-template-columns:repeat(3,minmax(0,1fr))}
    .siget-reports .builder-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:700px){
    .siget-reports .kpis{grid-template-columns:1fr 1fr}
    .siget-reports .builder-grid{grid-template-columns:1fr}
}
</style>

<div class="siget-reports">

    <div class="hero d-flex justify-content-between align-items-start gap-3">
        <div>
            <div class="text-uppercase small text-info fw-bold">
                Módulo de información y decisión
            </div>

            <h2 class="mb-1">
                Reportes SIGET
            </h2>

            <div class="muted">
                Reportes construidos sobre el mismo universo de datos y reglas
                de acceso del SIGET.
            </div>
        </div>

        <div class="text-end">
            <div class="muted">UNIVERSO ACTUAL</div>
            <strong class="fs-3">{{ number_format($total) }}</strong>
            <div class="muted">cargas</div>
        </div>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('reports.index') }}" class="filters">

        <div class="row g-3">

            <div class="col-xl-2 col-md-4">
                <div class="filter-label">Dependencia</div>

                <select name="agency_id" class="form-select form-select-sm">
                    <option value="">Todas</option>

                    @foreach($agencies as $agency)
                        <option
                            value="{{ $agency->id }}"
                            {{ (string)($filters['agency_id'] ?? '') === (string)$agency->id ? 'selected' : '' }}
                        >
                            {{ $agency->code }} · {{ $agency->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-xl-2 col-md-4">
                <div class="filter-label">Dirección / Unidad</div>

                <select name="organizational_unit_id"
                        class="form-select form-select-sm">

                    <option value="">Todas</option>

                    @foreach($units as $unit)
                        <option
                            value="{{ $unit->id }}"
                            {{ (string)($filters['organizational_unit_id'] ?? '') === (string)$unit->id ? 'selected' : '' }}
                        >
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-xl-2 col-md-4">
                <div class="filter-label">Estado</div>

                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>

                    @foreach($status as $statusCode => $statusTotal)
                        <option
                            value="{{ $statusCode }}"
                            {{ ($filters['status'] ?? '') === $statusCode ? 'selected' : '' }}
                        >
                            {{ $statusCode }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-xl-2 col-md-4">
                <div class="filter-label">Desde</div>

                <input
                    type="date"
                    name="from"
                    value="{{ $filters['from'] ?? '' }}"
                    class="form-control form-control-sm"
                >
            </div>

            <div class="col-xl-2 col-md-4">
                <div class="filter-label">Hasta</div>

                <input
                    type="date"
                    name="to"
                    value="{{ $filters['to'] ?? '' }}"
                    class="form-control form-control-sm"
                >
            </div>

            <div class="col-xl-2 col-md-4 d-flex align-items-end gap-2">

                <button class="btn btn-primary btn-sm flex-fill">
                    Aplicar
                </button>

                <a href="{{ route('reports.index') }}"
                   class="btn btn-outline-secondary btn-sm">
                    Limpiar
                </a>

            </div>

        </div>
    </form>

    {{-- NAVEGACIÓN --}}
    <div class="nav-reportes">

        <button class="report-tab active"
                type="button"
                data-report-tab="executive">
            1 · Ejecutivo Institucional
        </button>

        <button class="report-tab"
                type="button"
                data-report-tab="compliance">
            2 · Cumplimiento y Desempeño
        </button>

        <button class="report-tab"
                type="button"
                data-report-tab="evidence">
            3 · Cargas y Evidencias
        </button>

        <button class="report-tab"
                type="button"
                data-report-tab="risk">
            4 · Riesgo y Vencimientos
        </button>

        @if($canBuildReports)
            <button class="report-tab"
                    type="button"
                    data-report-tab="builder">
                5 · Constructor de Reportes
            </button>
        @endif

        @if($canExport)
            <a
                href="{{ route('reports.xlsx', request()->query()) }}"
                class="btn btn-outline-success btn-sm ms-auto"
            >
                Excel
            </a>

            <a
                href="{{ route('reports.csv', request()->query()) }}"
                class="btn btn-outline-info btn-sm"
            >
                CSV
            </a>

            <a
                href="{{ route('reports.pdf', request()->query()) }}"
                class="btn btn-outline-danger btn-sm"
            >
                PDF
            </a>
        @endif

    </div>

    {{-- =========================================================
         1. EJECUTIVO
         ========================================================= --}}
    <section class="report-panel active"
             data-report-panel="executive">

        <div class="kpis">

            @foreach([
                ['Total', $total],
                ['Activas', $active],
                ['Cerradas', $closed],
                ['Vencidas', $overdue],
                ['Reprogramadas', $reprogrammed],
                ['Cumplimiento', $compliance.'%']
            ] as [$label,$value])

                <div class="kpi">
                    <small>{{ $label }}</small>
                    <strong>{{ $value }}</strong>
                </div>

            @endforeach

        </div>

        <div class="report-card mb-3">

            <div class="report-head">
                <div>
                    <h3>Reporte Ejecutivo Institucional</h3>
                    <p>
                        Vista global para Dirección General y Administrador.
                    </p>
                </div>

                <span class="badge text-bg-primary">
                    {{ $total }} cargas
                </span>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Dependencia</th>
                            <th>Cargas</th>
                            <th>Cerradas</th>
                            <th>Vencidas</th>
                            <th>Cumplimiento</th>
                            <th>Lectura</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($executiveRows as $row)

                            @php
                                $pct = (float)($row['percentage'] ?? 0);
                                $late = (int)($row['overdue'] ?? 0);
                            @endphp

                            <tr>

                                <td>
                                    <strong>{{ $row['agency'] ?? 'Sin dependencia' }}</strong>
                                </td>

                                <td>{{ $row['total'] ?? 0 }}</td>

                                <td>{{ $row['closed'] ?? 0 }}</td>

                                <td>{{ $late }}</td>

                                <td>{{ $pct }}%</td>

                                <td>
                                    <span class="badge {{
                                        $late > 0
                                            ? 'text-bg-danger'
                                            : ($pct >= 90
                                                ? 'text-bg-success'
                                                : 'text-bg-warning')
                                    }}">
                                        {{ $late > 0 ? 'ATENCIÓN' : ($pct >= 90 ? 'FAVORABLE' : 'SEGUIMIENTO') }}
                                    </span>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="empty">
                                    No existen datos para los filtros seleccionados.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

        <div class="report-card">

            <div class="report-head">
                <div>
                    <h3>Tendencia mensual</h3>
                    <p>Entradas y cierres del periodo.</p>
                </div>
            </div>

            <div class="p-3">
                <canvas id="sigetExecutiveTrend" height="120"></canvas>
            </div>

        </div>

    </section>

    {{-- =========================================================
         2. CUMPLIMIENTO
         ========================================================= --}}
    <section class="report-panel"
             data-report-panel="compliance">

        <div class="report-card">

            <div class="report-head">
                <div>
                    <h3>Reporte de Cumplimiento y Desempeño</h3>
                    <p>
                        Comparativo por dependencia, dirección y unidad.
                    </p>
                </div>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Dirección / Unidad</th>
                            <th>Total</th>
                            <th>Validados</th>
                            <th>Cumplimiento</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($unitsPerformance as $row)

                            <tr>
                                <td>
                                    <strong>{{ $row['unit'] ?? 'Sin unidad' }}</strong>
                                </td>
                                <td>{{ $row['total'] ?? 0 }}</td>
                                <td>{{ $row['validated'] ?? 0 }}</td>
                                <td>
                                    <span class="badge text-bg-success">
                                        {{ $row['percentage'] ?? 0 }}%
                                    </span>
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="4" class="empty">
                                    Sin información de desempeño.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    {{-- =========================================================
         3. CARGAS / EVIDENCIAS
         ========================================================= --}}
    <section class="report-panel"
             data-report-panel="evidence">

        <div class="report-card">

            <div class="report-head">
                <div>
                    <h3>Reporte de Cargas y Evidencias</h3>
                    <p>
                        Seguimiento de entrega, responsables, revisión y validación.
                    </p>
                </div>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Carga</th>
                            <th>Dependencia</th>
                            <th>Dirección / Unidad</th>
                            <th>Responsable</th>
                            <th>Evidencias</th>
                            <th>Validadas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($evidenceRows as $row)

                            @php
                                $evidences = $row['evidences'];

                                $validated = $evidences->filter(function ($evidence) {
                                    $state = $evidence->status instanceof \BackedEnum
                                        ? $evidence->status->value
                                        : (string) $evidence->status;

                                    return $state === 'VALIDADO';
                                })->count();

                                $loadStatus = $row['load']->status instanceof \BackedEnum
                                    ? $row['load']->status->value
                                    : (string) $row['load']->status;
                            @endphp

                            <tr>

                                <td>
                                    <a href="{{ route('loads.show', $row['load']) }}"
                                       class="text-decoration-none">
                                        #{{ $row['load']->id }}
                                    </a>
                                </td>

                                <td>{{ $row['load']->agency?->name }}</td>

                                <td>{{ $row['unit']?->name ?? 'Sin unidad' }}</td>

                                <td>{{ $row['user']?->name ?? 'Sin responsable' }}</td>

                                <td>{{ $evidences->count() }}</td>

                                <td>{{ $validated }}</td>

                                <td>
                                    <span class="badge text-bg-light">
                                        {{ $loadStatus }}
                                    </span>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="empty">
                                    No existen cargas o evidencias para los filtros seleccionados.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    {{-- =========================================================
         4. RIESGO
         ========================================================= --}}
    <section class="report-panel"
             data-report-panel="risk">

        <div class="report-card">

            <div class="report-head">
                <div>
                    <h3>Reporte de Riesgo, Vencimientos y Reprogramaciones</h3>
                    <p>
                        Identificación de cargas que requieren atención.
                    </p>
                </div>

                <span class="badge text-bg-danger">
                    {{ $riskLoads->count() }} en seguimiento
                </span>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Carga</th>
                            <th>Dependencia</th>
                            <th>Fecha límite</th>
                            <th>Estado</th>
                            <th>Riesgo</th>
                            <th>Avance</th>
                            <th>Riesgo</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($riskLoads as $load)

                            @php
                                $state = $load->status instanceof \BackedEnum
                                    ? $load->status->value
                                    : (string) $load->status;

                                $risk = match($state) {
                                    'VENCIDA' => 'ALTO',
                                    'OBSERVADA' => 'MEDIO',
                                    'REPROGRAMADA' => 'MEDIO',
                                    default => 'ATENCIÓN',
                                };
                            @endphp

                            <tr>

                                <td>
                                    <a href="{{ route('loads.show', $load) }}">
                                        #{{ $load->id }}
                                    </a>
                                </td>

                                <td>{{ $load->agency?->name }}</td>

                                <td>
                                    {{ $load->effective_close_at?->format('d/m/Y H:i') }}
                                </td>

                                <td>{{ $state }}</td>

                                @php
                                    $status = $load->status instanceof \BackedEnum
                                        ? $load->status->value
                                        : (string) $load->status;

                                    $risk = match($status) {
                                        'VENCIDA' => 'ALTO',
                                        'OBSERVADA', 'REPROGRAMADA' => 'MEDIO',
                                        default => (
                                            $load->effective_close_at &&
                                            $load->effective_close_at->isFuture() &&
                                            now()->diffInHours($load->effective_close_at, false) <= 72
                                                ? 'ATENCIÓN'
                                                : 'NORMAL'
                                        ),
                                    };
                                @endphp
                                <td>
                                    <span class="badge {{
                                        $risk === 'ALTO'
                                            ? 'text-bg-danger'
                                            : ($risk === 'MEDIO'
                                                ? 'text-bg-warning'
                                                : ($risk === 'ATENCIÓN'
                                                    ? 'text-bg-info'
                                                    : 'text-bg-success'))
                                    }}">
                                        {{ $risk }}
                                    </span>
                                </td>

                                <td>{{ $load->completion_percentage }}%</td>

                                <td>
                                    <span class="badge {{
                                        $risk === 'ALTO'
                                            ? 'text-bg-danger'
                                            : ($risk === 'MEDIO'
                                                ? 'text-bg-warning'
                                                : 'text-bg-secondary')
                                    }}">
                                        {{ $risk }}
                                    </span>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="empty">
                                    No hay cargas en situación de riesgo.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    {{-- =========================================================
         5. CONSTRUCTOR
         ========================================================= --}}
    @if($canBuildReports)

        <section class="report-panel"
                 data-report-panel="builder">

            <div class="report-card">

                <div class="report-head">

                    <div>
                        <h3>Constructor de Reportes SIGET</h3>

                        <p>
                            Herramienta exclusiva del Administrador para
                            construir reportes especiales.
                        </p>
                    </div>

                    <span class="badge text-bg-primary">
                        ADMINISTRADOR
                    </span>

                </div>

                <div class="p-3">

                    <div class="builder-grid">

                        <div class="builder-box">

                            <h4>Dimensiones</h4>

                            <div class="builder-options">

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-field="agency"
                                        checked>
                                    Dependencia
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-field="unit"
                                        checked>
                                    Dirección / Unidad
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-field="responsible"
                                        checked>
                                    Responsable
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-field="period">
                                    Periodo
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-field="status"
                                        checked>
                                    Estado
                                </label>

                            </div>

                        </div>

                        <div class="builder-box">

                            <h4>Métricas</h4>

                            <div class="builder-options">

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-metric="total"
                                        checked>
                                    Total de cargas
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-metric="closed"
                                        checked>
                                    Cargas cerradas
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-metric="overdue">
                                    Cargas vencidas
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-metric="evidence">
                                    Evidencias
                                </label>

                                <label>
                                    <input
                                        type="checkbox"
                                        data-builder-metric="compliance">
                                    Cumplimiento
                                </label>

                            </div>

                        </div>

                        <div class="builder-box">

                            <h4>Visualización</h4>

                            <div class="builder-options">

                                <label>
                                    <input
                                        type="radio"
                                        name="builder-view"
                                        value="table"
                                        checked>
                                    Tabla
                                </label>

                                <label>
                                    <input
                                        type="radio"
                                        name="builder-view"
                                        value="summary">
                                    Resumen
                                </label>

                                <label>
                                    <input
                                        type="radio"
                                        name="builder-view"
                                        value="ranking">
                                    Ranking
                                </label>

                            </div>

                            <button
                                id="buildCustomReport"
                                type="button"
                                class="btn btn-primary btn-sm w-100 mt-3">
                                Generar reporte
                            </button>

                        </div>

                    </div>

                    <div
                        id="builderPreview"
                        class="builder-preview">

                        <div class="empty">
                            Selecciona dimensiones, métricas y visualización;
                            después presiona <strong>Generar reporte</strong>.
                        </div>

                    </div>

                </div>

            </div>

        </section>

    @endif

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('[data-report-tab]').forEach(function (button) {

        button.addEventListener('click', function () {

            const key = button.dataset.reportTab;

            document.querySelectorAll('[data-report-tab]')
                .forEach(function (item) {
                    item.classList.toggle(
                        'active',
                        item === button
                    );
                });

            document.querySelectorAll('[data-report-panel]')
                .forEach(function (panel) {
                    panel.classList.toggle(
                        'active',
                        panel.dataset.reportPanel === key
                    );
                });

        });

    });

    const builder = document.getElementById('buildCustomReport');
    const preview = document.getElementById('builderPreview');

    if (builder && preview) {

        builder.addEventListener('click', function () {

            const dimensions = Array
                .from(document.querySelectorAll('[data-builder-field]:checked'))
                .map(input => input.parentElement.textContent.trim());

            const metrics = Array
                .from(document.querySelectorAll('[data-builder-metric]:checked'))
                .map(input => input.parentElement.textContent.trim());

            const view = document.querySelector(
                'input[name="builder-view"]:checked'
            )?.value || 'table';

            preview.innerHTML = `
                <div class="mb-3">
                    <strong style="color:#fff">
                        Reporte personalizado generado
                    </strong>
                    <div class="muted mt-1">
                        Visualización: ${view}
                    </div>
                </div>

                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="builder-box">
                            <h4>Dimensiones seleccionadas</h4>
                            ${
                                dimensions.length
                                    ? dimensions.map(
                                        item => `<div class="small text-light mb-1">• ${item}</div>`
                                      ).join('')
                                    : '<div class="muted">Sin dimensiones.</div>'
                            }
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="builder-box">
                            <h4>Métricas seleccionadas</h4>
                            ${
                                metrics.length
                                    ? metrics.map(
                                        item => `<div class="small text-light mb-1">• ${item}</div>`
                                      ).join('')
                                    : '<div class="muted">Sin métricas.</div>'
                            }
                        </div>
                    </div>

                </div>

                <div class="mt-3 muted">
                    El resultado respeta los filtros y permisos del usuario
                    y utiliza exclusivamente el universo disponible en SIGET.
                </div>
            `;
        });
    }

    const canvas = document.getElementById('sigetExecutiveTrend');

    if (canvas && typeof Chart !== 'undefined') {

        const labels = @json($monthly->pluck('period')->values());
        const totals = @json($monthly->pluck('total')->values());
        const closes = @json($monthly->pluck('closed')->values());

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Entradas',
                        data: totals,
                        borderWidth: 2,
                        tension: .3,
                    },
                    {
                        label: 'Cierres',
                        data: closes,
                        borderWidth: 2,
                        tension: .3,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

@endsection
