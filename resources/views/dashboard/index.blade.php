@extends('layouts.app')
@section('title','Dashboard operativo SIGET')
@section('page-title','Dashboard operativo')
@section('page-subtitle', auth()->user()?->organizationalUnit?->name ?: ($presentation['scope'] ?? 'Operación'))

@section('content')
@php
    $role = auth()->user()?->role?->code;
    $k = $analytics['kpis'] ?? [];
    $filters = $filters ?? [];
    $evidenceSummary = $analytics['evidence_summary'] ?? [];
    $unitEvidence = collect($analytics['evidence_by_unit'] ?? []);
    $trend = collect($analytics['evidence_trend'] ?? []);
    $responsibles = collect($analytics['evidence_by_responsible'] ?? []);
    $riskItems = collect($analytics['risk_items'] ?? []);
    $upcoming = collect($analytics['upcoming'] ?? []);
    $tableRows = $riskItems->merge($upcoming)->filter(fn($row) => $row?->id)->unique('id')->sortBy(fn($row) => $row->effective_close_at?->timestamp ?? PHP_INT_MAX)->take(10)->values();

    $total = (int)($k['total'] ?? 0);
    $expected = (int)($k['evidence_expected'] ?? $evidenceSummary['expected'] ?? 0);
    $received = (int)($k['evidence_received'] ?? $evidenceSummary['received'] ?? 0);
    $pending = (int)($k['pending'] ?? max(0, $expected - $received));
    $observed = (int)($k['observed'] ?? $evidenceSummary['observed'] ?? 0);
    $overdue = (int)($k['overdue'] ?? 0);
    $review = (int)($k['review_pending'] ?? $evidenceSummary['review'] ?? 0);
    $submissionRate = $expected ? round(100 * $received / $expected) : 0;

    $statusMap = collect([
        'PROGRAMADA' => 'Programado',
        'ABIERTA' => 'Abierto',
        'EN_CAPTURA' => 'En captura',
        'PARCIALMENTE_ENTREGADA' => 'Entrega parcial',
        'ENTREGADA' => 'Enviado',
        'EN_REVISION_INSTITUCIONAL' => 'En revisión',
        'OBSERVADA' => 'Observada',
        'VALIDADA' => 'Validado',
        'VALIDADO_Y_CERRADO' => 'Cerrado',
        'REPROGRAMADA' => 'Reprogramado',
        'REPROGRAMADA_ABIERTA' => 'Reprogramado',
        'REPROGRAMADA_ENTREGADA' => 'Enviado',
        'VENCIDA' => 'Vencida',
    ]);

    $funnel = [
        ['label' => 'Programado', 'value' => $expected, 'class' => 'navy'],
        ['label' => 'Enviado', 'value' => $received, 'class' => 'teal'],
        ['label' => 'En revisión', 'value' => (int)($evidenceSummary['review'] ?? $review), 'class' => 'amber'],
        ['label' => 'Validado', 'value' => (int)($evidenceSummary['validated'] ?? 0), 'class' => 'purple'],
        ['label' => 'Cerrado', 'value' => (int)($analytics['evidence_funnel']['CERRADO'] ?? 0), 'class' => 'green'],
    ];

    $calendarYm = $filters['from'] ?? $filters['to'] ?? ($trend->last()['period'] ?? now()->format('Y-m'));
    $calendarBase = date_create($calendarYm . '-01') ?: date_create('first day of this month');
    $calendarDays = (int)$calendarBase->format('t');
    $calendarStart = (int)$calendarBase->format('N') - 1;
    $calendarSignals = [];
    foreach ($tableRows as $row) {
        $dateKey = $row->effective_close_at?->format('Y-m-d');
        if (!$dateKey) continue;
        $rawStatus = data_get($row, 'status.value', $row->status);
        $calendarSignals[$dateKey][] = (string)$rawStatus;
    }
    $calendarCells = [];
    for ($i = 0; $i < $calendarStart; $i++) $calendarCells[] = ['day' => null, 'tone' => ''];
    for ($day = 1; $day <= $calendarDays; $day++) {
        $dateKey = $calendarBase->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        $states = $calendarSignals[$dateKey] ?? [];
        $tone = '';
        if (collect($states)->contains(fn($s) => $s === 'VENCIDA')) $tone = 'danger';
        elseif (collect($states)->contains(fn($s) => in_array($s, ['ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','VALIDADA'], true))) $tone = 'active';
        elseif ($states) $tone = 'next';
        $calendarCells[] = ['day' => $day, 'tone' => $tone, 'today' => now()->format('Y-m-d') === $dateKey];
    }

    $scopeLabel = match ($role) {
        'DIRECTOR_TRANSMISION' => 'Dirección de Transmisión',
        'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'Dirección de Programación y Continuidad',
        'OPERADOR_TRANSMISION' => 'Dirección de Transmisión',
        'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'Dirección de Programación y Continuidad',
        'FISCALIZADOR' => 'Fiscalización',
        default => $presentation['scope'] ?? 'Cargas visibles',
    };
@endphp

<div class="siget-op-dashboard">
    <div class="op-heading">
        <div class="op-heading-copy">
            <h2>Dashboard operativo</h2>
            <p>{{ $scopeLabel }}</p>
        </div>
        <div class="op-heading-meta">
            <span class="op-scope-badge"><i class="bi bi-shield-check"></i> Alcance autorizado</span>
            <div class="mt-1">Los indicadores y gráficas se calculan únicamente con el universo visible para este usuario.</div>
        </div>
    </div>

    @include('dashboard.partials.filters')

    <div class="op-kpis">
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#118fa0"><i class="bi bi-broadcast-pin"></i></div>
            <div class="op-kpi-label">Spots asignados</div>
            <div>
                <div class="op-kpi-value">{{ number_format($total) }}</div>
            </div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#13a2b1"><i class="bi bi-clipboard-check"></i></div>
            <div class="op-kpi-label">Evidencias esperadas</div>
            <div><div class="op-kpi-value">{{ number_format($expected) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#4f9051"><i class="bi bi-cloud-arrow-up"></i></div>
            <div class="op-kpi-label">Evidencias enviadas</div>
            <div><div class="op-kpi-value">{{ number_format($received) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#113a68"><i class="bi bi-pie-chart-fill"></i></div>
            <div class="op-kpi-label">Cumplimiento de envío</div>
            <div>
                <div class="op-kpi-value">{{ $submissionRate }}%</div>
                <div class="op-kpi-progress"><span style="width:{{ min(100,$submissionRate) }}%"></span></div>
            </div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#ec9f00"><i class="bi bi-clock-fill"></i></div>
            <div class="op-kpi-label">Pendientes</div>
            <div><div class="op-kpi-value" style="color:var(--op-amber)">{{ number_format($pending) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#c63131"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="op-kpi-label">Observadas o rechazadas</div>
            <div><div class="op-kpi-value" style="color:var(--op-red)">{{ number_format($observed) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#c63131"><i class="bi bi-calendar-x-fill"></i></div>
            <div class="op-kpi-label">Vencidas</div>
            <div><div class="op-kpi-value" style="color:var(--op-red)">{{ number_format($overdue) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#153f71"><i class="bi bi-search"></i></div>
            <div class="op-kpi-label">Pendientes de validación</div>
            <div><div class="op-kpi-value">{{ number_format($review) }}</div></div>
        </div>
    </div>

    <div class="op-grid-top">
        <section class="op-card">
            <div class="op-card-head">
                <h3>Avance por unidad interna</h3>
                <div class="op-unit-legend">
                    <span><i class="op-legend-dot op-legend-navy"></i>Esperadas</span>
                    <span><i class="op-legend-dot op-legend-teal"></i>Recibidas</span>
                    <span><i class="op-legend-dot op-legend-gray"></i>Pendientes</span>
                </div>
            </div>
            <div class="op-card-body">
                <div class="op-bar-list">
                @forelse($unitEvidence->take(6) as $row)
                    @php
                        $uExpected = (int)($row['expected'] ?? 0);
                        $uReceived = (int)($row['received'] ?? 0);
                        $uPending = (int)($row['pending'] ?? 0);
                        $uMax = max(1,$uExpected);
                    @endphp
                    <div class="op-bar-row">
                        <span>{{ Str::limit($row['unit'] ?? 'Sin unidad',18) }}</span>
                        <div class="op-bar-track">
                            <div class="op-bar-stack">
                                <span style="width:100%;background:var(--op-navy)"></span>
                                <span style="width:0;background:var(--op-teal)"></span>
                            </div>
                            <div style="margin-top:-11px;height:11px;position:relative;overflow:hidden;border-radius:3px">
                                <span style="display:block;height:100%;width:{{ min(100,round(100*$uReceived/$uMax)) }}%;background:var(--op-teal)"></span>
                            </div>
                            <div style="margin-top:-11px;height:11px;position:relative;overflow:hidden;border-radius:3px;pointer-events:none">
                                <span style="display:block;height:100%;width:{{ min(100,round(100*$uPending/$uMax)) }}%;background:#cbd1d8"></span>
                            </div>
                        </div>
                        <span class="op-bar-value">{{ $uExpected }}</span>
                    </div>
                @empty
                    <div class="text-secondary text-center py-5">No hay unidades en el alcance actual.</div>
                @endforelse
                </div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Calendario de fechas programadas</h3>
            </div>
            <div class="op-card-body">
                <div class="op-calendar">
                    <div class="op-calendar-head">
                        <button type="button" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></button>
                        <div class="title">{{ strftime('%B %Y', $calendarBase->getTimestamp()) }}</div>
                        <button type="button" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <div class="op-calendar-week">
                        <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
                    </div>
                    <div class="op-calendar-grid">
                    @foreach($calendarCells as $cell)
                        <div class="op-day {{ !$cell['day'] ? 'muted' : '' }} {{ ($cell['today'] ?? false) ? 'today' : '' }}">
                            {{ $cell['day'] ?: '' }}
                            @if(!empty($cell['tone']))<span class="signal {{ $cell['tone'] }}"></span>@endif
                        </div>
                    @endforeach
                    </div>
                    <div class="op-calendar-legend">
                        <span><i class="op-legend-dot op-legend-amber"></i>Próximos</span>
                        <span><i class="op-legend-dot op-legend-teal"></i>Activos</span>
                        <span><i class="op-legend-dot op-legend-red"></i>Vencidos</span>
                        <span><i class="op-legend-dot op-legend-outline"></i>Cerrados</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Estado del expediente</h3>
            </div>
            <div class="op-card-body">
                <div class="op-funnel-wrap">
                    <div class="op-funnel">
                        @foreach($funnel as $stage)
                            <div class="op-funnel-stage {{ $stage['class'] }}">
                                <span>{{ $stage['label'] }}</span><span>{{ number_format($stage['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="op-funnel-table">
                        @php $funnelBase=max(1,$expected); @endphp
                        @foreach($funnel as $stage)
                            <div class="rowline">
                                <span>{{ $stage['label'] }}</span>
                                <span class="qty">{{ number_format($stage['value']) }}</span>
                                <span class="pct" style="grid-column:2"> {{ round(100*$stage['value']/$funnelBase) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="op-grid-bottom">
        <section class="op-card">
            <div class="op-card-head">
                <h3>Tendencia de entregas</h3>
                <p>Evidencias esperadas, enviadas y validadas.</p>
            </div>
            <div class="op-card-body">
                <div class="op-trend"><canvas id="sigetOperationalTrend"></canvas></div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Carga por responsable</h3>
                <p>Responsables dentro del alcance autorizado.</p>
            </div>
            <div class="op-card-body">
                <div class="op-responsible-list">
                    @forelse($responsibles->take(7) as $row)
                        @php $respExpected=(int)($row['expected']??0); $maxResp=max(1,(int)$responsibles->max('expected')); @endphp
                        <div class="op-resp-row">
                            <span>{{ Str::limit($row['responsible'] ?? 'Sin asignar',16) }}</span>
                            <div class="op-resp-track"><span style="width:{{ min(100,round(100*$respExpected/$maxResp)) }}%"></span></div>
                            <strong>{{ $respExpected }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary text-center py-4">Sin responsables visibles.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="op-card op-table-card">
            <div class="op-card-head">
                <h3>Bandeja de evidencias</h3>
                <p>Acciones informativas; el flujo de entrega y validación permanece en sus rutas existentes.</p>
            </div>
            <div class="table-responsive">
                <table class="op-evidence-table">
                    <thead><tr><th>Evidencia</th><th>Responsable</th><th>Fecha límite</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    @forelse($tableRows as $load)
                        @php
                            $loadStatus = data_get($load,'status.value',$load->status);
                            $statusLabel = $statusMap[$loadStatus] ?? str_replace('_',' ',$loadStatus);
                            $statusClass = match($loadStatus) {
                                'VENCIDA' => 'state-red',
                                'OBSERVADA','RECHAZADO' => 'state-red',
                                'EN_REVISION_INSTITUCIONAL','ENTREGADA','EN_CAPTURA' => 'state-teal',
                                'VALIDADA','VALIDADO_Y_CERRADO' => 'state-purple',
                                default => 'state-amber',
                            };
                            $responsibleNames = $load->deliverables?->pluck('responsibleUser.name')->filter()->unique()->join(', ');
                            $deadline = $load->effective_close_at?->format('d/m/Y');
                        @endphp
                        <tr>
                            <td class="evidence-name" title="{{ $load->title }}">{{ $load->title ?: 'Carga sin nombre' }}</td>
                            <td>{{ Str::limit($responsibleNames ?: 'Sin asignar',24) }}</td>
                            <td class="{{ $loadStatus === 'VENCIDA' ? 'text-danger fw-bold' : '' }}">{{ $deadline ?: '—' }}</td>
                            <td><span class="op-state {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>
                                <div class="op-table-actions">
                                    <a href="{{ route('loads.show',$load) }}" class="btn btn-outline-primary btn-sm">Ver expediente</a>
                                    <a href="{{ route('loads.show',$load) }}" class="btn btn-outline-danger btn-sm">Atender</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay evidencias para mostrar en el alcance actual.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="op-footer">
        Horario del sistema: {{ now()->format('d/m/Y H:i') }} h (Hora del Centro)
    </div>
</div>

<script>
(function(){
    const el = document.getElementById('sigetOperationalTrend');
    if(!el || typeof Chart === 'undefined') return;

    const labels = @json($trend->pluck('period')->values());
    const expected = @json($trend->pluck('expected')->values());
    const received = @json($trend->pluck('received')->values());
    const validated = @json($trend->pluck('validated')->values());

    new Chart(el,{
        type:'line',
        data:{
            labels,
            datasets:[
                {label:'Esperadas',data:expected,borderColor:'#173e70',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5},
                {label:'Enviadas',data:received,borderColor:'#148d96',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5},
                {label:'Validadas',data:validated,borderColor:'#4d904e',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5}
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{position:'top',align:'start',labels:{color:'#5e7187',boxWidth:9,font:{size:10}}}},
            scales:{
                x:{ticks:{color:'#738297',font:{size:9}},grid:{color:'#eef2f6'}},
                y:{beginAtZero:true,ticks:{color:'#738297',font:{size:9}},grid:{color:'#eef2f6'}}
            }
        }
    });
})();
</script>
@endsection