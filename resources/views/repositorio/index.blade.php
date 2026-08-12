@extends('layouts.app')
@section('title', request()->routeIs('loads.mine') ? 'Mis cargas' : 'Repositorio')
@section('page-title', request()->routeIs('loads.mine') ? 'Mis cargas' : 'Repositorio institucional por dependencia')
@section('page-subtitle', request()->routeIs('loads.mine') ? 'Cargas de su alcance con captura directa de evidencias por dirección.' : 'Cada dependencia contiene el expediente completo de sus direcciones y todas sus evidencias.')
@section('content')
<div class="siget-file-manager" data-file-manager>
    <aside class="siget-file-sidebar">
        <div class="siget-storage-card">
            <div class="d-flex justify-content-between"><strong>Dependencias</strong><i class="bi bi-buildings"></i></div>
            <div class="progress mt-3"><div class="progress-bar" style="width:{{ min(100,round($usedBytes/max(1,10*1024*1024*1024)*100,1)) }}%"></div></div>
            <small class="text-secondary">{{ number_format($usedBytes/1024/1024,1) }} MB en evidencias de su alcance</small>
            <div class="siget-folder-tree mt-3">
                @foreach($agencies as $a)
                    <a href="{{ route('repository.index',['agency_id'=>$a->id]) }}" class="{{ (int)($filters['agency_id']??0)===$a->id?'active':'' }}">
                        <i class="bi bi-building"></i>{{ $a->name }}<span>{{ $agencyCounts[$a->id] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </aside>

    <section class="card siget-card">
        <div class="siget-file-toolbar">
            <div>
                <div class="small text-secondary">SIGET / Repositorio / Dependencias @if(!empty($filters['agency_id'])) / {{ $agencies->firstWhere('id',(int)$filters['agency_id'])?->name }}@endif</div>
                <strong>{{ !empty($filters['agency_id']) ? 'Expediente completo de la dependencia' : 'Dependencias y expedientes institucionales' }}</strong>
            </div>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="agency_id" value="{{ $filters['agency_id']??'' }}">
                    <input class="form-control" name="q" value="{{ $filters['q']??'' }}" placeholder="Buscar dependencia, pauta o periodo">
                    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                </form>
                <div class="btn-group"><button class="btn btn-outline-secondary" data-file-view="grid"><i class="bi bi-grid"></i></button><button class="btn btn-outline-secondary" data-file-view="list"><i class="bi bi-list"></i></button></div>
            </div>
        </div>

        <div class="siget-file-section">
            <h2>Repositorio clasificado por dependencia</h2>
            <p class="text-secondary">No se valida ni se cierra una evidencia aislada. Al abrir una dependencia se revisa el conjunto de expedientes, entregables y evidencias de todas sus direcciones.</p>
            <div class="siget-folder-grid">
                @forelse($dependencySummaries as $summary)
                    @php($selected = (int)($filters['agency_id']??0) === (int)$summary->agency->id)
                    <a href="{{ route('repository.index',['agency_id'=>$summary->agency->id]) }}" class="siget-folder-card {{ $selected ? 'border border-primary' : '' }}">
                        <i class="bi bi-folder-fill"></i>
                        <strong>{{ $summary->agency->name }}</strong>
                        <small>{{ $summary->load_count }} expediente(s) programado(s)</small>
                        <small>{{ $summary->directions->count() }} dirección(es) · {{ $summary->required_count }} requisito(s) obligatorio(s)</small>
                        <small>{{ $summary->evidence_count }} archivo(s) de evidencia</small>
                    </a>
                @empty
                    <div class="text-secondary">No existen dependencias dentro del alcance del usuario.</div>
                @endforelse
            </div>
        </div>

        <div class="siget-file-section border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2>{{ !empty($filters['agency_id']) ? 'Expedientes de la dependencia' : 'Expedientes programados' }}</h2>
                    <p class="text-secondary mb-0">Cada expediente reúne las dos direcciones y sus evidencias conforme a la pauta y sus fechas programadas.</p>
                </div>
                @if(!empty($filters['agency_id']))
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('repository.index') }}">Ver dependencias</a>
                @endif
            </div>

            <div class="siget-folder-grid">
                @forelse($loads as $load)
                    @php($directions = $load->deliverables->pluck('organizationalUnit')->filter()->unique('id')->sortBy('name')->values())
                    <a href="{{ route('loads.show',$load) }}" class="siget-folder-card">
                        <i class="bi bi-folder2-open"></i>
                        <strong>{{ $load->title }}</strong>
                        <small>{{ $load->agency?->name }} · {{ $load->period_label }}</small>
                        <small>{{ $directions->count() }} dirección(es): {{ $directions->pluck('name')->join(' · ') }}</small>
                        <small>{{ $load->deliverables->sum(fn($d)=>$d->evidences->sum(fn($e)=>$e->files->count())) }} archivos · {{ $load->completion_percentage }}%</small>
                        <span class="badge text-bg-light mt-1">Abrir expediente completo</span>
                    </a>
                @empty
                    <div class="text-secondary">No existen expedientes dentro del filtro seleccionado.</div>
                @endforelse
            </div>
            <div class="mt-3">{{ $loads->links() }}</div>
        </div>

        @if(auth()->user()->hasPermission('evidence.upload'))
        <div class="siget-file-section border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h2>Captura de evidencias por dirección</h2><p class="text-secondary mb-0">La captura sigue siendo individual por entregable; la revisión institucional y el cierre son siempre del expediente completo.</p></div>
                <span class="badge text-bg-light"><i class="bi bi-shield-lock me-1"></i>Alcance por dirección</span>
            </div>
            @forelse($loads as $load)
                <div class="card border mb-3">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div><strong>{{ $load->title }}</strong><div class="small text-secondary">{{ $load->agency?->name }} · {{ $load->period_label }}</div></div>
                        <a href="{{ route('loads.show',$load) }}" class="btn btn-sm btn-outline-primary">Abrir expediente completo</a>
                    </div>
                    <div class="card-body"><div class="row g-3">
                        @forelse($load->deliverables as $deliverable)
                            @php($evidence = $deliverable->evidences->sortByDesc('id')->first())
                            <div class="col-xl-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <div><span class="badge text-bg-light"><i class="bi bi-diagram-3 me-1"></i>{{ $deliverable->organizationalUnit?->name }}</span><h3 class="h6 mt-2 mb-1">{{ $deliverable->templateRequirement?->name }}</h3></div>
                                        <span class="badge siget-status">{{ $deliverable->status instanceof \BackedEnum ? $deliverable->status->value : $deliverable->status }}</span>
                                    </div>
                                    @if($evidence)<div class="small mb-2"><strong>{{ $evidence->title }}</strong> · V{{ $evidence->current_version }} <span class="text-secondary">({{ $evidence->files->count() }} archivo(s))</span></div><a href="{{ route('evidences.show',$evidence) }}" class="btn btn-sm btn-outline-secondary mb-3">Ver evidencia y versiones</a>@endif
                                    <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}"><div class="mb-2"><input name="title" class="form-control" placeholder="Título de la evidencia" value="{{ $evidence?->title }}"></div><div class="input-group"><input name="file" type="file" class="form-control" required><button class="btn btn-primary"><i class="bi bi-paperclip me-1"></i>Adjuntar evidencia</button></div></form>
                                    <div class="small text-secondary mt-2"><i class="bi bi-folder2-open me-1"></i>{{ $deliverable->organizationalUnit?->name }} · queda integrada al expediente de la dependencia</div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-secondary">No hay entregables de su dirección en esta carga.</div>
                        @endforelse
                    </div></div>
                </div>
            @empty
                <div class="text-secondary">No hay cargas disponibles para adjuntar evidencias.</div>
            @endforelse
        </div>
        @endif

        <div class="siget-file-section border-top">
            <h2>Archivos recientes</h2>
            <div class="siget-file-grid">
                @forelse($recentFiles as $f)
                    <a href="{{ route('evidence-files.download',$f) }}" class="siget-file-card"><i class="bi {{ in_array(strtolower($f->extension),['pdf'])?'bi-file-earmark-pdf':(in_array(strtolower($f->extension),['xlsx','xls','csv'])?'bi-file-earmark-spreadsheet':'bi-file-earmark') }}"></i><span><strong>{{ $f->original_name }}</strong><small>{{ $f->evidence?->scheduledLoad?->agency?->name }} · {{ $f->evidence?->deliverable?->organizationalUnit?->name }}</small></span><small>{{ number_format($f->size_bytes/1024,1) }} KB</small><small>{{ $f->created_at?->format('d/m/Y H:i') }}</small></a>
                @empty<div class="text-secondary">No hay archivos recientes.</div>@endforelse
            </div>
        </div>
    </section>
</div>
@endsection
