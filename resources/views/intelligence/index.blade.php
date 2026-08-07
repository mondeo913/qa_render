@extends('layouts.app')
@section('title','Centro de Inteligencia')
@section('page-title','Centro de Inteligencia Institucional')
@section('content')
<form method="GET" class="card siget-card mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Dependencia</label>
            <select name="agency_id" class="form-select">
                <option value="">Todas</option>
                @foreach($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? null)==$agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Aplicar</button></div>
        <div class="col-md-2"><a href="{{ route('intelligence') }}" class="btn btn-outline-secondary w-100">Limpiar</a></div>
    </div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Cumplimiento global',$analytics['kpis']['compliance'].'%'],
        ['Avance promedio',$analytics['kpis']['completion_average'].'%'],
        ['Cargas cerradas',$analytics['kpis']['closed']],
        ['Cargas vencidas',$analytics['kpis']['overdue']],
    ] as [$label,$value])
        <div class="col-md-3"><div class="siget-intelligence-kpi"><span>{{ $label }}</span><strong>{{ $value }}</strong></div></div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-8"><div class="card siget-card"><div class="card-header"><div><h2>Evolución del cumplimiento</h2><p>Comparativo de volumen y cierres</p></div></div><div class="card-body"><canvas id="intelligenceTrend" height="120"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card siget-card"><div class="card-header"><div><h2>Estados</h2><p>Composición operativa</p></div></div><div class="card-body"><canvas id="intelligenceStatus" height="220"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card siget-card"><div class="card-header"><div><h2>Desempeño por dirección</h2><p>Porcentaje de entregables concluidos</p></div></div><div class="card-body"><canvas id="intelligenceUnits" height="150"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card siget-card"><div class="card-header"><div><h2>Madurez de evidencias</h2><p>Embudo del proceso documental</p></div></div><div class="card-body"><canvas id="intelligenceEvidence" height="150"></canvas></div></div></div>
</div>

<script type="application/json" data-siget-chart="intelligenceTrend">{!! json_encode(['type'=>'line','labels'=>collect($analytics['monthly_trend'])->pluck('period'),'datasets'=>[['label'=>'Cargas','data'=>collect($analytics['monthly_trend'])->pluck('total')],['label'=>'Cerradas','data'=>collect($analytics['monthly_trend'])->pluck('closed')],['label'=>'Cumplimiento %','data'=>collect($analytics['monthly_trend'])->pluck('compliance'),'yAxisID'=>'y1']]]) !!}</script>
<script type="application/json" data-siget-chart="intelligenceStatus">{!! json_encode(['type'=>'doughnut','labels'=>array_keys($analytics['status_distribution']),'datasets'=>[['label'=>'Cargas','data'=>array_values($analytics['status_distribution'])]]]) !!}</script>
<script type="application/json" data-siget-chart="intelligenceUnits">{!! json_encode(['type'=>'bar','labels'=>collect($analytics['unit_performance'])->pluck('unit'),'datasets'=>[['label'=>'Cumplimiento %','data'=>collect($analytics['unit_performance'])->pluck('percentage')]]]) !!}</script>
<script type="application/json" data-siget-chart="intelligenceEvidence">{!! json_encode(['type'=>'bar','labels'=>array_keys($analytics['evidence_funnel']),'datasets'=>[['label'=>'Evidencias','data'=>array_values($analytics['evidence_funnel'])]]]) !!}</script>
@endsection
