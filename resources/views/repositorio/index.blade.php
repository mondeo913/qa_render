@extends('layouts.app')
@section('title', $repositoryTitle)
@section('page-title', $repositoryTitle)
@section('page-subtitle', 'Dependencias autorizadas, con sus cargas agrupadas por mes y pauta.')
@section('content')
@php
    $selectedAgency=$agencies->firstWhere('id',(int)($filters['agency_id'] ?? 0));
    $hasContentFilters=collect($filters)->except('agency_id')->filter(fn($v)=>$v!==null && $v!=='')->isNotEmpty();
    $agencyUrl=fn($id)=>route('repository.index',array_merge(\Illuminate\Support\Arr::except($filters,['agency_id','pauta_id']),['agency_id'=>$id]));
@endphp
<div class="siget-file-manager" data-file-manager>
    <aside class="siget-file-sidebar">
        <div class="siget-storage-card">
            <div class="d-flex justify-content-between"><strong>Dependencias</strong><i class="bi bi-buildings"></i></div>
            <small class="text-secondary d-block mt-2">{{ $agencies->count() }} autorizadas · cifras según los filtros</small>
            <small class="text-secondary d-block">{{ number_format($usedBytes/1024/1024,1) }} MB en archivos del contenido seleccionado</small>
            <div class="siget-folder-tree mt-3">
                @foreach($agencies as $agency)
                    <a href="{{ $agencyUrl($agency->id) }}" data-repository-agency-id="{{ $agency->id }}" class="{{ $selectedAgency?->id===$agency->id ? 'active' : '' }}"><i class="bi bi-building"></i>{{ $agency->name }}<span>{{ $agencyCounts[$agency->id] ?? 0 }}</span></a>
                @endforeach
            </div>
        </div>
    </aside>
    <section class="card siget-card">
        <div class="siget-file-toolbar">
            <div><div class="small text-secondary">SIGET / Repositorio / Dependencias @if($selectedAgency) / {{ $selectedAgency->name }} @endif</div><strong>{{ $selectedAgency ? 'Contenido de '.$selectedAgency->name : 'Carpetas por dependencia' }}</strong></div>
            <form method="GET" class="d-flex gap-2" action="{{ route('repository.index') }}">
                @foreach(\Illuminate\Support\Arr::except($filters,'q') as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <label for="repository-search" class="visually-hidden">Buscar dependencia, pauta o mes</label>
                <input id="repository-search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar dependencia, pauta o mes">
                <button class="btn btn-primary" type="submit" aria-label="Buscar en el repositorio"><i class="bi bi-search"></i></button>
                <div class="btn-group" role="group" aria-label="Vista del repositorio"><button class="btn btn-outline-secondary" type="button" data-file-view="grid" aria-label="Vista de tarjetas"><i class="bi bi-grid-3x3-gap"></i></button><button class="btn btn-outline-secondary" type="button" data-file-view="list" aria-label="Vista de lista"><i class="bi bi-list-ul"></i></button></div>
            </form>
        </div>
        <div class="siget-file-section">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div><h2>Repositorio por dependencia</h2><p class="text-secondary mb-1">Las dependencias autorizadas tienen una carpeta, incluso cuando aún no hay pautas cargadas.</p><p class="small text-secondary">{{ $directionScoped ? 'Se muestran únicamente los entregables y archivos autorizados de tu Dirección.' : 'El contenido se limita a las dependencias y expedientes que puedes consultar.' }}</p></div>
                @if($selectedAgency || $hasContentFilters)<a class="btn btn-sm btn-outline-secondary" href="{{ route('repository.index') }}">Ver todas las dependencias</a>@endif
                @if($hasContentFilters)<a class="btn btn-sm btn-outline-secondary" href="{{ route('repository.index',array_filter(['agency_id'=>$selectedAgency?->id])) }}">Limpiar filtros de contenido</a>@endif
            </div>
            <div class="siget-folder-grid">
                @forelse($dependencySummaries as $summary)
                    <a href="{{ $agencyUrl($summary->agency->id) }}" class="siget-folder-card {{ $selectedAgency ? 'border border-primary' : '' }}" data-repository-folder-id="{{ $summary->agency->id }}" data-load-count="{{ $summary->load_count }}">
                        <i class="bi bi-folder-fill"></i><strong>{{ $summary->agency->name }}</strong>
                        <small>{{ $summary->load_count }} carga(s) · {{ $summary->monthlyPautas->count() }} carpeta(s) mes/pauta</small>
                        @if($summary->load_count)<small>{{ $summary->directions->count() }} dirección(es) en tu alcance</small>@else<small>{{ $hasContentFilters ? 'Sin cargas para estos filtros' : 'Sin cargas programadas en tu alcance' }}</small>@endif
                        <small>{{ $summary->evidence_count }} evidencia(s) · {{ $summary->file_count }} archivo(s)</small>
                    </a>
                @empty<div class="text-secondary">{{ $agencies->isEmpty() ? 'No existen dependencias dentro del alcance del usuario.' : 'No hay dependencias o pautas que coincidan con la búsqueda.' }}</div>@endforelse
            </div>
        </div>
        @if($selectedAgency)
            <div class="siget-file-section border-top" data-repository-detail-agency-id="{{ $selectedAgency->id }}">
                <h2>Expediente de la dependencia por mes y pauta</h2>
                <p class="text-secondary">{{ $selectedAgency->name }} · cada pauta conserva sus propias fechas programadas.</p>
                @php $selectedSummary=$dependencySummaries->firstWhere('agency.id',$selectedAgency->id); @endphp
                @forelse($selectedSummary->monthlyPautas as $group)
                    <article class="card border mb-4" data-repository-pauta-id="{{ $group->pauta_id }}">
                        <header class="card-header bg-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                            <div><div class="small text-secondary"><i class="bi bi-calendar3 me-1"></i>{{ $group->month_label }}</div><h3 class="h5 mb-1"><i class="bi bi-folder2-open me-1"></i>{{ $group->pauta_name }}</h3><div class="small text-secondary">{{ $group->load_count }} carga(s) programada(s) · {{ $group->directions->count() }} dirección(es) en tu alcance</div></div>
                            @if(auth()->user()->hasPermission('reports.export'))<a href="{{ route('reports.pdf',array_filter(['agency_id'=>$selectedAgency->id,'pauta_id'=>$group->pauta_id,'from'=>$group->month,'to'=>$group->month])) }}" class="btn btn-sm btn-outline-secondary" data-repository-report><i class="bi bi-file-earmark-pdf me-1"></i>Reporte de la pauta y mes</a>@endif
                        </header>
                        <div class="card-body">
                            <p class="text-secondary">{{ $group->required_count }} entregable(s) obligatorio(s) · {{ $group->evidence_count }} evidencia(s) · {{ $group->file_count }} archivo(s)</p>
                            <h4 class="h6 mb-3">Direcciones y evidencias integradas en esta pauta</h4>
                            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Dirección</th><th>Entregables</th><th>Evidencias</th><th>Archivos</th><th>Validados</th></tr></thead><tbody>
                                @foreach($group->directions as $direction)
                                    @php $items=$group->deliverables->where('organizational_unit_id',$direction->id); $evidences=$items->flatMap->evidences; $validated=$items->filter(fn($d)=>in_array($d->status->value,['VALIDADO','CERRADO'],true))->count(); @endphp
                                    <tr><td>{{ $direction->name }}</td><td>{{ $items->count() }}</td><td>{{ $evidences->count() }}</td><td>{{ $evidences->sum(fn($e)=>$e->files->count()) }}</td><td>{{ $validated }}/{{ $items->count() }}</td></tr>
                                @endforeach
                            </tbody></table></div>
                            <h4 class="h6 mb-3">Fechas programadas de esta pauta</h4>
                            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha programada</th><th>Carga</th><th>Estado</th><th>Expediente</th></tr></thead><tbody>
                                @foreach($group->loads as $load)<tr data-repository-load-id="{{ $load->id }}"><td>{{ $load->effective_open_at?->format('d/m/Y H:i') }}</td><td>{{ $load->title }}</td><td>{{ str_replace('_',' ',$load->status->value) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('loads.show',$load) }}">Abrir carga</a></td></tr>@endforeach
                            </tbody></table></div>
                        </div>
                    </article>
                @empty
                    <p class="text-secondary" data-repository-empty>{{ $hasContentFilters ? 'Sin cargas que coincidan con estos filtros.' : 'Esta dependencia aún no tiene cargas programadas en tu alcance. Su carpeta está disponible y mostrará las pautas y evidencias cuando se incorporen al flujo.' }}</p>
                @endforelse
            </div>
        @endif
        <div class="siget-file-section border-top">
            <h2>Archivos recientes{{ $selectedAgency ? ' de '.$selectedAgency->name : '' }}</h2>
            <div class="siget-file-grid">
                @forelse($recentFiles as $file)
                    <a href="{{ route('evidence-files.download',$file) }}" class="siget-file-card" data-repository-file-id="{{ $file->id }}" data-agency-id="{{ $file->evidence->scheduledLoad->contracting_agency_id }}"><i class="bi bi-file-earmark"></i><span><strong>{{ $file->original_name }}</strong><small>{{ $file->evidence->scheduledLoad->agency?->name }} · {{ $file->evidence->deliverable->organizationalUnit?->name }}</small></span><small>{{ number_format($file->size_bytes/1024,1) }} KB</small></a>
                @empty<div class="text-secondary">No hay archivos recientes{{ $selectedAgency ? ' para esta dependencia' : '' }}{{ $hasContentFilters ? ' con estos filtros' : '' }}.</div>@endforelse
            </div>
        </div>
    </section>
</div>
@endsection
