@extends('layouts.app')
@section('title','Indicadores')
@section('page-title','Indicadores de cumplimiento')
@section('content')
<div class="row g-3 mb-4">@foreach($analytics['kpis'] as $label=>$value)<div class="col-6 col-lg-3"><div class="siget-intelligence-kpi"><span>{{ str_replace('_',' ',ucfirst($label)) }}</span><strong>{{ in_array($label,['compliance','completion_average']) ? $value.'%' : $value }}</strong></div></div>@endforeach</div>
<div class="row g-4"><div class="col-lg-7"><div class="card siget-card"><div class="card-body"><canvas id="indicatorTrend" height="130"></canvas></div></div></div><div class="col-lg-5"><div class="card siget-card"><div class="card-body"><canvas id="indicatorUnits" height="180"></canvas></div></div></div></div>
<script type="application/json" data-siget-chart="indicatorTrend">{!! json_encode(['type'=>'line','labels'=>collect($analytics['monthly_trend'])->pluck('period'),'datasets'=>[['label'=>'Cumplimiento %','data'=>collect($analytics['monthly_trend'])->pluck('compliance')]]]) !!}</script>
<script type="application/json" data-siget-chart="indicatorUnits">{!! json_encode(['type'=>'bar','labels'=>collect($analytics['unit_performance'])->pluck('unit'),'datasets'=>[['label'=>'Cumplimiento %','data'=>collect($analytics['unit_performance'])->pluck('percentage')]]]) !!}</script>
@endsection
