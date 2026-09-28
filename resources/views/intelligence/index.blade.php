@extends('layouts.app')
@section('title','Centro de Inteligencia')
@section('page-title',$analytics['role']['title'] ?? 'Centro de Inteligencia')
@section('page-subtitle',$analytics['role']['subtitle'] ?? 'Lectura operativa y de riesgo con el mismo universo autorizado para su perfil.')
@section('content')
@php
    $k = $analytics['kpis'] ?? [];
    $filters = $filters ?? [];
    $attention = collect($analytics['attention_items'] ?? []);
    $quality = collect($analytics['quality_items'] ?? []);
    $agencyRows = collect($analytics['agency_performance'] ?? []);
    $directions = collect($analytics['direction_performance'] ?? []);
    $pautaRows = collect($analytics['pauta_performance'] ?? []);
    $pautas = collect($pautas ?? []);
    $statusOptions = $statusOptions ?? [];
    $radar = $analytics['radar_summary'] ?? ['CRÍTICO'=>0,'ATENCIÓN'=>0,'NORMAL'=>0];
    $health = $analytics['system_health'] ?? null;
    $directionScope = $analytics['direction_scope'] ?? null;
    $isDirectionDirector = in_array(auth()->user()?->role?->code, ['DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'], true);
    $responsibles = collect($analytics['evidence_by_responsible'] ?? []);
    $monthly = collect($analytics['monthly_trend'] ?? []);
@endphp

<style>
.direction-intelligence{background:linear-gradient(180deg,#0b1118,#101820);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px}
.direction-intelligence .di-filters{background:#121b24;border:1px solid rgba(255,255,255,.08);border-radius:13px;padding:10px 12px;margin-bottom:12px}
.direction-intelligence .di-filters label{font-size:.62rem;line-height:1;color:#91a5b7;margin-bottom:4px}
.direction-intelligence .di-filters select,.direction-intelligence .di-filters input{height:30px;min-height:30px;padding:.25rem .5rem;font-size:.68rem;background:#0a1118;border-color:rgba(255,255,255,.12);color:#eaf2f7}
.direction-intelligence .di-filters .btn{height:30px;min-height:30px;padding:.25rem .45rem;font-size:.66rem}
.direction-intelligence .compact-role-kpis{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:8px;margin:0 0 12px}
.direction-intelligence .compact-role-kpis>[class*="col-"]{width:auto!important;max-width:none!important;padding:0}
.direction-intelligence .role-kpi-card{min-height:72px;height:72px;padding:8px 9px;display:grid;grid-template-columns:28px 1fr;grid-template-rows:auto 1fr;column-gap:8px;align-items:center;background:#121b24;border:1px solid rgba(255,255,255,.08);border-radius:12px}
.direction-intelligence .role-kpi-icon{grid-row:1 / span 2;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:.82rem}
.direction-intelligence .role-kpi-card small{font-size:.62rem;line-height:1.05;white-space:normal;overflow-wrap:anywhere;color:#91a5b7}
.direction-intelligence .role-kpi-card strong{font-size:1.05rem;line-height:1;color:#fff}
.direction-intelligence .di-panel{background:#121b24;border:1px solid rgba(255,255,255,.08);border-radius:13px;overflow:hidden;height:100%}
.direction-intelligence .di-head{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08)}
.direction-intelligence .di-head h3{margin:0;color:#fff;font-size:.86rem}
.direction-intelligence .di-head p{margin:3px 0 0;color:#91a5b7;font-size:.63rem}
.direction-intelligence .di-chart{height:210px;padding:8px 10px 10px}
.direction-intelligence .di-chart canvas{width:100%!important;height:100%!important}
.direction-intelligence .di-table{--bs-table-bg:transparent;--bs-table-color:#eaf2f7;--bs-table-border-color:rgba(255,255,255,.08);font-size:.68rem;margin:0}
.direction-intelligence .di-table th{color:#7890a3;font-size:.58rem;text-transform:uppercase;letter-spacing:.04em}
.direction-intelligence .di-table td,.direction-intelligence .di-table th{padding:7px 9px;vertical-align:middle}
.direction-intelligence .di-badge{font-size:.56rem;padding:3px 6px;border-radius:6px}
.direction-intelligence .di-empty{padding:22px;text-align:center;color:#8195a8;font-size:.7rem}
.direction-intelligence .di-quality{max-height:260px;overflow:auto}
@media(max-width:1399px){.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:700px){.direction-intelligence{padding:10px}.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
/* Normalización ejecutiva: misma geometría de KPIs y módulos que Dirección General. */
.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:0 0 16px}
.direction-intelligence .role-kpi-card{min-height:108px;height:108px;padding:16px;grid-template-columns:34px 1fr;column-gap:10px;border-radius:14px}
.direction-intelligence .role-kpi-icon{width:34px;height:34px;border-radius:9px;font-size:.9rem}
.direction-intelligence .role-kpi-card small{font-size:.72rem;line-height:1.15}
.direction-intelligence .role-kpi-card strong{font-size:1.55rem;line-height:1.1}
.direction-intelligence .di-filters{padding:12px 14px;margin-bottom:16px}
.direction-intelligence .di-filters label{font-size:.68rem;line-height:1.1;margin-bottom:3px}
.direction-intelligence .di-filters select,.direction-intelligence .di-filters input{height:32px;min-height:32px;padding:.34rem .55rem;font-size:.76rem}
.direction-intelligence .di-filters .btn{height:32px;min-height:32px;padding:.34rem .55rem;font-size:.76rem}
.direction-intelligence .di-chart-grid{display:grid;gap:12px;margin:0 0 12px}
.direction-intelligence .di-chart-grid>[class*="col-"]{width:auto!important;max-width:none!important;padding:0;min-width:0}
.direction-intelligence .di-chart-grid-primary{grid-template-columns:5fr 3fr 4fr}
.direction-intelligence .di-chart-grid-secondary{grid-template-columns:1fr 1fr}
.direction-intelligence .di-chart-grid-tertiary{grid-template-columns:7fr 5fr}
.direction-intelligence .di-panel{border-radius:15px;display:flex;flex-direction:column;min-height:300px}
.direction-intelligence .di-head{padding:15px 17px}
.direction-intelligence .di-head h3{font-size:.95rem}
.direction-intelligence .di-head p{font-size:.67rem;margin-top:4px}
.direction-intelligence .di-chart{height:300px;min-height:300px;padding:10px 14px 12px;flex:1}
.direction-intelligence .di-table{font-size:.72rem}
.direction-intelligence .di-table th{font-size:.61rem}
.direction-intelligence .di-table td,.direction-intelligence .di-table th{padding:9px 10px}
.direction-intelligence .di-badge{font-size:.58rem;padding:4px 6px;border-radius:7px}
.direction-intelligence .di-empty{padding:24px;font-size:.72rem}
.direction-intelligence .di-quality{max-height:none;overflow:auto;flex:1}
@media(max-width:1200px){.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.direction-intelligence .di-chart-grid-primary{grid-template-columns:1fr 1fr}.direction-intelligence .di-chart-grid-primary>[class*="col-"]:last-child{grid-column:1/-1}.direction-intelligence .di-chart-grid-secondary,.direction-intelligence .di-chart-grid-tertiary{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.direction-intelligence .di-chart-grid,.direction-intelligence .di-chart-grid-primary,.direction-intelligence .di-chart-grid-secondary,.direction-intelligence .di-chart-grid-tertiary{grid-template-columns:1fr}.direction-intelligence .di-chart-grid-primary>[class*="col-"]:last-child{grid-column:auto}.direction-intelligence .di-chart{height:250px;min-height:250px}.direction-intelligence .role-kpi-card{height:104px;min-height:104px;padding:10px}}

/* Escala estándar de Inteligencia para Director: comparable con Director General, Administrador y Enlace. */
.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px}
.direction-intelligence .role-kpi-card{height:94px;min-height:94px;padding:12px 13px;grid-template-columns:36px minmax(0,1fr);grid-template-rows:auto 1fr;column-gap:10px;border-radius:13px}
.direction-intelligence .role-kpi-icon{width:36px;height:36px;border-radius:10px;font-size:.9rem}
.direction-intelligence .role-kpi-card small{font-size:.7rem;line-height:1.12}
.direction-intelligence .role-kpi-card strong{font-size:1.35rem;line-height:1.05}
.direction-intelligence .di-panel{min-height:270px;border-radius:14px}
.direction-intelligence .di-head{padding:11px 14px}
.direction-intelligence .di-head h3{font-size:.9rem}
.direction-intelligence .di-head p{font-size:.65rem;margin-top:3px}
.direction-intelligence .di-chart{height:230px;min-height:230px;padding:8px 12px 10px}
.direction-intelligence .di-table{font-size:.7rem}
.direction-intelligence .di-table th{font-size:.59rem}
.direction-intelligence .di-table td,.direction-intelligence .di-table th{padding:8px 9px}
.direction-intelligence .di-badge{font-size:.56rem;padding:3px 6px}
.direction-intelligence .di-empty{padding:20px;font-size:.7rem}
.direction-intelligence .di-quality{max-height:235px;overflow:auto}
@media(max-width:1200px){.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}.direction-intelligence .di-chart-grid-primary{grid-template-columns:1fr 1fr}.direction-intelligence .di-chart-grid-primary>[class*="col-"]:last-child{grid-column:1/-1}.direction-intelligence .di-chart-grid-secondary,.direction-intelligence .di-chart-grid-tertiary{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.direction-intelligence .compact-role-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.direction-intelligence .role-kpi-card{height:92px;min-height:92px;padding:10px}.direction-intelligence .di-chart-grid,.direction-intelligence .di-chart-grid-primary,.direction-intelligence .di-chart-grid-secondary,.direction-intelligence .di-chart-grid-tertiary{grid-template-columns:1fr}.direction-intelligence .di-chart-grid-primary>[class*="col-"]:last-child{grid-column:auto}.direction-intelligence .di-chart{height:235px;min-height:235px}}


</style>

@if($isDirectionDirector)
<div class="direction-intelligence">
    <form method="GET" class="di-filters">
        <div class="row g-2 align-items-end">
            <div class="col-xxl-3 col-lg-4 col-md-6">
                <label>Pauta</label>
                <select name="pauta_id" class="form-select">
                    <option value="">Todas las pautas visibles</option>
                    @foreach($pautas as $pauta)
                        <option value="{{ $pauta['id'] }}" @selected((string)($filters['pauta_id']??'') === (string)$pauta['id'])>{{ $pauta['name'] }} · {{ $pauta['agency'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xxl-2 col-lg-4 col-md-6">
                <label>Dependencia</label>
                <select name="agency_id" class="form-select">
                    <option value="">Todas las visibles</option>
                    @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected((string)($filters['agency_id']??'') === (string)$agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xxl-2 col-lg-4 col-md-6">
                <label>Dirección</label>
                <select name="organizational_unit_id" class="form-select">
                    <option value="">Toda la Dirección</option>
                    @foreach($units as $unit)
                        @php $unitIdList=implode(',',array_map('strval',(array)($unit->filter_unit_ids??[$unit->id]))); @endphp
                        <option value="{{ $unitIdList }}" @selected((string)($filters['organizational_unit_id']??'') === $unitIdList)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xxl-2 col-lg-4 col-md-6">
                <label>Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $status=>$definition)
                        <option value="{{ $status }}" @selected(($filters['status']??null)===$status)>{{ $definition['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xxl-1 col-lg-4 col-md-6">
                <label>Desde</label>
                <input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control">
            </div>
            <div class="col-xxl-1 col-lg-4 col-md-6">
                <label>Hasta</label>
                <input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control">
            </div>
            <div class="col-xxl-1 col-lg-4 col-md-6 d-grid gap-1">
                <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar</button>
                <a href="{{ route('intelligence') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="row g-2 compact-role-kpis">
        @foreach([
            ['Spots asignados',$k['total']??0,'bi-broadcast-pin','info'],
            ['Evidencias esperadas',$k['evidence_expected']??0,'bi-files','primary'],
            ['Evidencias enviadas',$k['evidence_received']??0,'bi-cloud-arrow-up','success'],
            ['Pendientes',$k['pending']??0,'bi-hourglass-split','warning'],
            ['Observadas o rechazadas',$k['observed']??0,'bi-exclamation-octagon','danger'],
            ['Vencidas',$k['overdue']??0,'bi-calendar-x','danger'],
            ['Pendientes de validación',$k['review_pending']??0,'bi-clipboard-check','warning'],
            ['Cumplimiento',($k['compliance']??0).'%','bi-bullseye','success'],
        ] as [$label,$value,$icon,$type])
            <div><div class="role-kpi-card">
                <div class="role-kpi-icon text-bg-{{ $type }}"><i class="bi {{ $icon }}"></i></div>
                <small>{{ $label }}</small>
                <strong>{{ $value }}</strong>
            </div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3 di-chart-grid di-chart-grid-primary">
        <div class="col-xl-5">
            <div class="di-panel">
                <div class="di-head"><h3>Ritmo de operación</h3><p>Entradas, cierres y cumplimiento de la Dirección.</p></div>
                <div class="di-chart"><canvas id="directionIntelligenceTrend"></canvas></div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="di-panel">
                <div class="di-head"><h3>Estado operativo</h3><p>Distribución del universo visible.</p></div>
                <div class="di-chart"><canvas id="directionIntelligenceStatus"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="di-panel">
                <div class="di-head"><h3>Avance de la Dirección</h3><p>Consolidado de la Dirección y sus unidades subordinadas.</p></div>
                <div class="di-chart"><canvas id="directionIntelligenceUnit"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3 di-chart-grid di-chart-grid-secondary">
        <div class="col-xl-6">
            <div class="di-panel">
                <div class="di-head"><h3>Por responsable operativo</h3><p>Evidencias esperadas frente a evidencias enviadas.</p></div>
                <div class="di-chart"><canvas id="directionIntelligenceResponsible"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="di-panel">
                <div class="di-head"><h3>Clasificación por pauta</h3><p>Programadas, reprogramadas, validadas, cerradas y vencidas.</p></div>
                <div class="table-responsive">
                    <table class="table di-table">
                        <thead><tr><th>Pauta</th><th>Program.</th><th>Reprog.</th><th>Val.</th><th>Cerr.</th><th>Venc.</th></tr></thead>
                        <tbody>
                        @forelse($pautaRows as $row)
                            <tr>
                                <td><strong>{{ $row['pauta_name'] }}</strong><small class="d-block text-muted">{{ number_format($row['total']) }} cargas</small></td>
                                <td>{{ $row['programmed'] }}</td>
                                <td>{{ $row['reprogrammed'] }}</td>
                                <td>{{ $row['validated'] }}</td>
                                <td>{{ $row['closed'] }}</td>
                                <td><span class="di-badge {{ $row['overdue']>0?'bg-danger':'bg-secondary' }} text-white">{{ $row['overdue'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="di-empty">No hay pautas dentro del alcance.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 di-chart-grid di-chart-grid-tertiary">
        <div class="col-xl-7">
            <div class="di-panel">
                <div class="di-head"><h3>Atención prioritaria</h3><p>Vencimientos, observaciones y pendientes dentro de la Dirección.</p></div>
                <div class="table-responsive">
                    <table class="table di-table">
                        <thead><tr><th>Nivel</th><th>Situación</th><th>Alcance</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                        @forelse($attention->take(8) as $item)
                            <tr>
                                <td><span class="di-badge {{ $item['level']==='CRÍTICO'?'bg-danger':'bg-warning text-dark' }}">{{ $item['level'] }}</span></td>
                                <td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['detail'] }}</small></td>
                                <td>{{ $item['description'] }}</td>
                                <td>{{ !empty($item['date']) ? \Illuminate\Support\Carbon::parse($item['date'])->format('d/m/Y') : '—' }}</td>
                                <td class="text-end">@if(!empty($item['load_id']))<a href="{{ route('loads.show',$item['load_id']) }}" class="btn btn-sm btn-outline-primary py-0">Ver</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="di-empty">Sin situaciones prioritarias.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="di-panel">
                <div class="di-head"><h3>Calidad de evidencias</h3><p>Excepciones detectadas en el alcance autorizado.</p></div>
                <div class="di-quality">
                    <table class="table di-table">
                        <thead><tr><th>Nivel</th><th>Hallazgo</th><th>Detalle</th></tr></thead>
                        <tbody>
                        @forelse($quality->take(8) as $item)
                            <tr>
                                <td><span class="di-badge {{ $item['level']==='CRÍTICO'?'bg-danger':'bg-warning text-dark' }}">{{ $item['level'] }}</span></td>
                                <td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['description'] }}</small></td>
                                <td>{{ $item['detail'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="di-empty">Sin inconsistencias detectadas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const monthly=@json($monthly);
    const rawStatus=@json($analytics['status_distribution'] ?? []);
    const direction=@json($directions->take(10)->values());
    const responsibles=@json($responsibles->take(10)->values());
    const text='#eaf2f7', muted='#8195a8', grid='rgba(255,255,255,.08)', cyan='#21c6d8', blue='#4f7cff', green='#35c77a', red='#ef4655', yellow='#e9b949';

    function chart(id,type,data,options={}){
        const el=document.getElementById(id);
        if(!el || typeof Chart==='undefined') return;
        new Chart(el,{type,data,options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:text,boxWidth:9,font:{size:9}}}},scales:{x:{ticks:{color:muted,font:{size:9}},grid:{color:grid}},y:{ticks:{color:muted,font:{size:9}},grid:{color:grid},beginAtZero:true}},...options}});
    }

    chart('directionIntelligenceTrend','line',{
        labels:monthly.map(x=>x.period),
        datasets:[
            {label:'Entradas',data:monthly.map(x=>Number(x.total||0)),borderColor:cyan,tension:.3,borderWidth:2,fill:false},
            {label:'Cierres',data:monthly.map(x=>Number(x.closed||0)),borderColor:blue,tension:.3,borderWidth:2,fill:false},
            {label:'Cumplimiento %',data:monthly.map(x=>Number(x.compliance||0)),borderColor:green,tension:.3,borderWidth:2,fill:false,yAxisID:'y1'}
        ]
    },{scales:{x:{ticks:{color:muted},grid:{color:grid}},y:{beginAtZero:true,ticks:{color:muted},grid:{color:grid}},y1:{position:'right',min:0,max:100,ticks:{color:muted},grid:{drawOnChartArea:false}}}});

    const status={};
    Object.entries(rawStatus).forEach(([key,value])=>{
        let label=['VALIDADO_Y_CERRADO'].includes(key)?'Cerradas':
            ['VENCIDA'].includes(key)?'Vencidas':
            ['REPROGRAMADA','REPROGRAMADA_ABIERTA','REPROGRAMADA_ENTREGADA','SUSPENDIDA'].includes(key)?'Reprogramadas':
            ['OBSERVADA','EN_REVISION_INSTITUCIONAL'].includes(key)?'En revisión':'En operación';
        status[label]=(status[label]||0)+Number(value||0);
    });
    chart('directionIntelligenceStatus','doughnut',{labels:Object.keys(status),datasets:[{data:Object.values(status),backgroundColor:[green,red,blue,yellow,cyan],borderWidth:0}]},{cutout:'68%',plugins:{legend:{position:'bottom',labels:{color:text,boxWidth:9,font:{size:9}}}}});

    chart('directionIntelligenceUnit','bar',{
        labels:direction.map(x=>x.name||x.unit||'Dirección'),
        datasets:[
            {label:'Cargas',data:direction.map(x=>Number(x.total||0)),backgroundColor:cyan,borderRadius:5},
            {label:'Cumplimiento %',data:direction.map(x=>Number(x.percentage||0)),backgroundColor:yellow,borderRadius:5}
        ]
    },{plugins:{legend:{position:'bottom',labels:{color:text,boxWidth:9,font:{size:9}}}}});

    chart('directionIntelligenceResponsible','bar',{
        labels:responsibles.map(x=>x.responsible||'Sin asignar'),
        datasets:[
            {label:'Esperadas',data:responsibles.map(x=>Number(x.expected||0)),backgroundColor:cyan,borderRadius:4},
            {label:'Enviadas',data:responsibles.map(x=>Number(x.received||0)),backgroundColor:green,borderRadius:4}
        ]
    },{indexAxis:'y',plugins:{legend:{position:'bottom',labels:{color:text,boxWidth:9,font:{size:9}}}}});
})();
</script>
@else
<section class="siget-hero mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <span class="badge text-bg-primary mb-2">{{ $analytics['role']['code'] ?? 'SIGET' }}</span>
            <h2 class="mb-1">Radar y alertas</h2>
            <p class="mb-0">Esta vista no modifica cargas, evidencias ni estados. En una Dirección, toda la lectura queda restringida exclusivamente a la Dirección autorizada y a la información de sus unidades subordinadas.</p>
        </div>
        <div class="text-end"><small class="d-block text-muted">Actualizado</small><strong>{{ optional($analytics['generated_at'] ?? null)->format('d/m/Y H:i') }}</strong></div>
    </div>
</section>

<form method="GET" class="card siget-card mb-4">
    <div class="card-header"><div><h2>Filtros de lectura ejecutiva</h2><p>La lectura se clasifica primero por <strong>pauta</strong> y <strong>dependencia</strong>; el estado conserva únicamente las categorías ejecutivas principales.</p></div></div>
    <div class="card-body row g-3 align-items-end">
        <div class="col-xl-4 col-md-6"><label class="form-label">Pauta</label><select name="pauta_id" class="form-select"><option value="">Todas las pautas visibles</option>@foreach($pautas as $pauta)<option value="{{ $pauta['id'] }}" @selected((string)($filters['pauta_id']??'') === (string)$pauta['id'])>{{ $pauta['name'] }} · {{ $pauta['agency'] }}</option>@endforeach</select></div>
        <div class="col-xl-3 col-md-6"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select"><option value="">Todas las visibles</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency_id']??'') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
        <div class="col-xl-3 col-md-6"><label class="form-label">Dirección</label><select name="organizational_unit_id" class="form-select"><option value="">Todas las visibles</option>@foreach($units as $unit)@php $unitIdList=implode(',',array_map('strval',(array)($unit->filter_unit_ids??[$unit->id]))); @endphp<option value="{{ $unitIdList }}" @selected((string)($filters['organizational_unit_id']??'') === $unitIdList)>{{ $unit->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-6"><label class="form-label">Estado ejecutivo</label><select name="status" class="form-select"><option value="">Todos los principales</option>@foreach($statusOptions as $status=>$definition)<option value="{{ $status }}" @selected(($filters['status']??null)===$status)>{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Desde</label><input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Actualizar lectura</button><a href="{{ route('intelligence') }}" class="btn btn-outline-secondary">Restablecer</a></div>
    </div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Atención prioritaria',$k['attention_total']??0,'Casos que requieren actuar','danger','bi-bullseye'],
        ['Vencidas',$k['overdue']??0,'Fuera de fecha','danger','bi-calendar-x'],
        ['Próximas 72 h',$k['due_soon']??0,'Con fecha crítica cercana','warning','bi-alarm'],
        ['En revisión',$k['review_queue']??0,'Entregables pendientes de revisión','info','bi-clipboard-check'],
        ['Evidencias faltantes',$k['missing_evidence']??0,'Requisitos sin evidencia','secondary','bi-file-earmark-x'],
        ['Reprogramadas',$k['reprogrammed']??0,'Cargas reprogramadas','primary','bi-arrow-repeat'],
    ] as [$label,$value,$hint,$type,$icon])
        <div class="col-6 col-xl-2"><div class="siget-kpi h-100"><div class="siget-kpi-icon text-bg-{{ $type }}"><i class="bi {{ $icon }}"></i></div><div><small>{{ $label }}</small><strong>{{ number_format((int)$value) }}</strong><span class="d-block small text-muted mt-1">{{ $hint }}</span></div></div></div>
    @endforeach
</div>

@if($health)
<div class="card siget-card mb-4"><div class="card-header"><div><h2>Salud de configuración</h2><p>Lectura administrativa sin intervenir la operación.</p></div></div><div class="card-body"><div class="row g-3">
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Usuarios activos</small><strong class="fs-4 d-block">{{ number_format($health['active_users']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Dependencias visibles</small><strong class="fs-4 d-block">{{ number_format($health['active_agencies']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Direcciones / unidades visibles</small><strong class="fs-4 d-block">{{ number_format($health['active_units']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Usuarios sin alcance</small><strong class="fs-4 d-block {{ $health['users_without_scope'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($health['users_without_scope']) }}</strong></div></div>
</div></div></div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-8"><div class="card siget-card h-100"><div class="card-header"><div><h2>Atención prioritaria</h2><p>Ordenada por vencimiento, observaciones y pendientes de revisión.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nivel</th><th>Situación</th><th>Alcance</th><th>Fecha</th><th></th></tr></thead><tbody>
        @forelse($attention as $item)
            <tr><td><span class="badge {{ $item['level']==='CRÍTICO'?'text-bg-danger':'text-bg-warning' }}">{{ $item['level'] }}</span></td><td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['detail'] }}</small></td><td>{{ $item['description'] }}</td><td>{{ !empty($item['date']) ? \Illuminate\Support\Carbon::parse($item['date'])->format('d/m/Y') : '—' }}</td><td class="text-end">@if(!empty($item['load_id']))<a href="{{ route('loads.show',$item['load_id']) }}" class="btn btn-sm btn-outline-primary">Ver carga</a>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center py-4">No hay situaciones prioritarias dentro del universo seleccionado.</td></tr>@endforelse
    </tbody></table></div></div></div>
    <div class="col-xl-4"><div class="card siget-card h-100"><div class="card-header"><div><h2>{{ $isDirectionDirector ? 'Radar de '.$directionScope['name'] : 'Radar' }}</h2><p>{{ $isDirectionDirector ? 'Riesgo consolidado exclusivamente de la Dirección autorizada.' : 'Distribución de dependencias visibles por nivel de atención.' }}</p></div></div><div class="card-body">
        @php $radarBase=max(1,$isDirectionDirector ? $directions->count() : (int)$agencies->count()); @endphp
        @foreach([['CRÍTICO',$radar['CRÍTICO']??0,'danger'],['ATENCIÓN',$radar['ATENCIÓN']??0,'warning'],['NORMAL',$radar['NORMAL']??0,'success']] as [$label,$value,$type])
            @php $width=min(100,round(100*$value/$radarBase)); @endphp
            <div class="mb-4"><div class="d-flex justify-content-between mb-1"><strong>{{ $label }}</strong><span>{{ $value }}</span></div><div class="progress" style="height:10px"><div class="progress-bar bg-{{ $type }}" style="width:{{ $width }}%"></div></div></div>
        @endforeach
        <div class="small text-muted">La clasificación se calcula con cargas vencidas, vencimientos en 72 horas y porcentaje de cierre del universo visible.</div>
    </div></div></div>
</div>

<div class="card siget-card mb-4">
    <div class="card-header"><div><h2>{{ $isDirectionDirector ? "Clasificación ejecutiva por Pauta y Dirección" : "Clasificación ejecutiva por Pauta y Dependencia" }}</h2><p>Consolidado institucional: <strong>programadas, reprogramadas, en revisión, validadas, cerradas y vencidas</strong>. La vista evita exponer estados internos de operación que no aportan a la lectura ejecutiva.</p></div></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pauta</th><th>Dependencia</th><th>Programadas</th><th>Reprogramadas</th><th>En revisión</th><th>Validadas</th><th>Cerradas</th><th>Vencidas</th></tr></thead><tbody>
    @forelse($pautaRows as $row)
        <tr>
            <td><strong>{{ $row['pauta_name'] }}</strong><small class="d-block text-muted">{{ number_format($row['total']) }} cargas</small></td>
            <td>{{ $row['agency'] }}</td>
            <td>{{ number_format($row['programmed']) }}</td>
            <td>{{ number_format($row['reprogrammed']) }}</td>
            <td><span class="badge {{ $row['in_review'] > 0 ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ number_format($row['in_review']) }}</span></td>
            <td><span class="badge text-bg-info">{{ number_format($row['validated']) }}</span></td>
            <td><span class="badge text-bg-success">{{ number_format($row['closed']) }}</span></td>
            <td><span class="badge {{ $row['overdue'] > 0 ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ number_format($row['overdue']) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center py-4">No hay pautas dentro del alcance seleccionado.</td></tr>
    @endforelse
    </tbody></table></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Riesgo por Dirección</h2><p>{{ $isDirectionDirector ? "Solo ".$directionScope["name"].". Se consolidan únicamente sus unidades y entregables autorizados." : "El riesgo se limita a las Direcciones que el usuario tiene autorizadas." }}</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Dirección</th><th>Cargas</th><th>Cierre</th><th>Venc.</th><th>72 h</th><th>Estado</th></tr></thead><tbody>@forelse($directions->take(10) as $row)<tr><td><strong>{{ $row['name'] ?? 'Sin unidad' }}</strong></td><td>{{ $row['total'] }}</td><td>{{ $row['percentage'] }}%</td><td>{{ $row['overdue'] }}</td><td>{{ $row['due_soon'] ?? 0 }}</td><td><span class="badge {{ ($row['overdue']??0)>0?'text-bg-danger':(($row['percentage']??0)<80?'text-bg-warning':'text-bg-success') }}">{{ ($row['overdue']??0)>0?'CRÍTICO':(($row['percentage']??0)<80?'ATENCIÓN':'NORMAL') }}</span></td></tr>@empty<tr><td colspan="6" class="text-center py-4">Sin información.</td></tr>@endforelse</tbody></table></div></div></div>
@unless($isDirectionDirector)
    <div class="col-xl-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Riesgo por Dependencia</h2><p>Concentración de vencimientos y avance.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Dependencia</th><th>Cierre</th><th>Venc.</th><th>72 h</th><th></th></tr></thead><tbody>@forelse($agencyRows->take(8) as $row)<tr><td><strong>{{ $row['name'] ?? 'Sin dependencia' }}</strong></td><td>{{ $row['percentage'] }}%</td><td>{{ $row['overdue'] }}</td><td>{{ $row['due_soon'] ?? 0 }}</td><td><span class="badge {{ ($row['overdue']??0)>0?'text-bg-danger':(($row['percentage']??0)<80?'text-bg-warning':'text-bg-success') }}">{{ ($row['overdue']??0)>0?'CRÍTICO':(($row['percentage']??0)<80?'ATENCIÓN':'NORMAL') }}</span></td></tr>@empty<tr><td colspan="5" class="text-center py-4">Sin información.</td></tr>@endforelse</tbody></table></div></div></div>
@endunless
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Inconsistencias y calidad</h2><p>Excepciones que pueden impedir una revisión o cierre limpio.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nivel</th><th>Hallazgo</th><th>Detalle</th><th></th></tr></thead><tbody>@forelse($quality as $item)<tr><td><span class="badge {{ $item['level']==='CRÍTICO'?'text-bg-danger':'text-bg-warning' }}">{{ $item['level'] }}</span></td><td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['description'] }}</small></td><td>{{ $item['detail'] }}</td><td class="text-end">@if(!empty($item['evidence_id']))<a href="{{ route('evidences.show',$item['evidence_id']) }}" class="btn btn-sm btn-outline-primary">Ver evidencia</a>@elseif(!empty($item['load_id']))<a href="{{ route('loads.show',$item['load_id']) }}" class="btn btn-sm btn-outline-primary">Ver carga</a>@endif</td></tr>@empty<tr><td colspan="4" class="text-center py-4">No se detectaron inconsistencias dentro del universo seleccionado.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-xl-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Lectura rápida</h2><p>Qué significa la situación actual de este usuario.</p></div></div><div class="card-body"><div class="vstack gap-3">
        <div><small class="text-muted">Cumplimiento</small><strong class="d-block fs-5">{{ $k['compliance']??0 }}%</strong><span class="small text-muted">Cargas cerradas sobre el universo visible.</span></div>
        <div><small class="text-muted">Capacidad de respuesta</small><strong class="d-block fs-5">{{ $k['active']??0 }} en operación</strong><span class="small text-muted">Cargas aún dentro del flujo activo.</span></div>
        <div><small class="text-muted">Riesgo inmediato</small><strong class="d-block fs-5">{{ $k['overdue']??0 }} vencidas · {{ $k['due_soon']??0 }} en 72 h</strong><span class="small text-muted">Priorizar por fecha y estado.</span></div>
        <div><small class="text-muted">Calidad</small><strong class="d-block fs-5">{{ $k['missing_evidence']??0 }} faltantes · {{ $k['stale_reviews']??0 }} revisiones atrasadas</strong><span class="small text-muted">Excepciones detectadas sin modificar registros.</span></div>
    </div></div></div></div>
</div>

<div class="card siget-card"><div class="card-header"><div><h2>Tendencia institucional</h2><p>Comparación mensual del volumen contra cierres dentro del universo seleccionado.</p></div></div><div class="card-body"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Periodo</th><th>Cargas</th><th>Cierres</th><th>Cumplimiento</th><th>Lectura</th></tr></thead><tbody>@forelse($analytics['monthly_trend'] ?? [] as $row)<tr><td>{{ $row['period'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['closed'] }}</td><td>{{ $row['compliance'] }}%</td><td><div class="progress" style="height:7px;min-width:120px"><div class="progress-bar" style="width:{{ min(100,(float)$row['compliance']) }}%"></div></div></td></tr>@empty<tr><td colspan="5" class="text-center py-4">Sin histórico suficiente con los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div></div>
@endif

@endsection
