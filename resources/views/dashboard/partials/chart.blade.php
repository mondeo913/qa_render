<article class="siget-dashboard-card {{ $class ?? '' }}">
    <header><h3>{{ $title }}</h3><p>{{ $description }}</p></header>
    @if(count($chart['labels']) && collect($chart['datasets'])->sum(fn($d) => array_sum($d['data'])) > 0)
        <div class="siget-dashboard-chart" style="height: {{ $height ?? 300 }}px"><canvas id="{{ $id }}" role="img" aria-label="{{ $title }}. Consulte los valores en la tabla de datos."></canvas></div>
        <script type="application/json" data-siget-chart="{{ $id }}">{!! json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
    @else
        <div class="siget-dashboard-empty"><i class="bi bi-bar-chart"></i><p>Sin datos para los filtros y el alcance seleccionados.</p></div>
    @endif
    <details class="siget-dashboard-data"><summary>Ver valores y abrir detalle</summary><div class="table-responsive"><table class="table align-middle"><caption class="visually-hidden">{{ $title }}</caption><thead><tr><th scope="col">Concepto</th>@foreach($chart['datasets'] as $dataset)<th scope="col">{{ $dataset['label'] }}</th>@endforeach</tr></thead><tbody>
        @forelse($chart['labels'] as $i=>$label)<tr><th scope="row">@if(!empty($chart['links'][$i]))<a href="{{ $chart['links'][$i] }}">{{ $label }}</a>@else{{ $label }}@endif</th>@foreach($chart['datasets'] as $dataset)<td>{{ $dataset['data'][$i] ?? 0 }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($chart['datasets'])+1 }}">Sin registros.</td></tr>@endforelse
    </tbody></table></div></details>
</article>
