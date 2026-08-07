@extends('layouts.app')
@section('title','Reportes')
@section('page-title','Reportes y exportaciones')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-4">
    @if(auth()->user()->hasPermission('reports.export'))
    <a href="{{ route('reports.csv',request()->query()) }}" class="btn btn-success"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
    <a href="{{ route('reports.pdf',request()->query()) }}" class="btn btn-danger"><i class="bi bi-file-pdf"></i> Reporte ejecutivo PDF</a>
    @endif
</div>
<div class="row g-4">
    <div class="col-lg-8"><div class="card siget-card"><div class="card-header"><div><h2>Serie mensual</h2><p>Volumen y cierres</p></div></div><div class="card-body"><canvas id="reportsTrend" height="120"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card siget-card"><div class="card-header"><div><h2>Estados</h2><p>Distribución</p></div></div><div class="card-body"><canvas id="reportsStatus" height="210"></canvas></div></div></div>
</div>
<script type="application/json" data-siget-chart="reportsTrend">{!! json_encode(['type'=>'line','labels'=>collect($analytics['monthly_trend'])->pluck('period'),'datasets'=>[['label'=>'Total','data'=>collect($analytics['monthly_trend'])->pluck('total')],['label'=>'Cerradas','data'=>collect($analytics['monthly_trend'])->pluck('closed')]]]) !!}</script>
<script type="application/json" data-siget-chart="reportsStatus">{!! json_encode(['type'=>'doughnut','labels'=>array_keys($analytics['status_distribution']),'datasets'=>[['label'=>'Cargas','data'=>array_values($analytics['status_distribution'])]]]) !!}</script>
@endsection
