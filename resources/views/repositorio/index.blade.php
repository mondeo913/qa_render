@extends('layouts.app')
@section('title', request()->routeIs('loads.mine') ? 'Mis cargas' : 'Repositorio de revisión')
@section('page-title', request()->routeIs('loads.mine') ? 'Mis cargas' : 'Repositorio de revisión institucional')
@section('page-subtitle', request()->routeIs('loads.mine') ? 'Captura de evidencias por dirección.' : 'Expedientes agrupados por dependencia, mes programado y pauta contratada.')

@section('content')
<div class="siget-file-manager" data-file-manager>
    <aside class="siget-file-sidebar">
        <div class="siget-storage-card">
            <div class="d-flex justify-content-between"><strong>Dependencias</strong><i class="bi bi-buildings"></i></div>
            <div class="progress mt-3">
                <div class="progress-bar" style="width: {{ min(100, round($usedBytes / max(1, 10 * 1024 * 1024 * 1024) * 100, 1)) }}%"></div>
            </div>
            <small class="text-secondary">{{ number_format($usedBytes / 1024 / 1024, 1) }} MB en evidencias</small>
            <div class="siget-folder-tree mt-3">
                @foreach($agencies as $agency)
                    <a href="{{ route('repository.index', ['agency_id' => $agency->id]) }}" class="{{ (int)($filters['agency_id'] ?? 0) === (int)$agency->id ? 'active' : '' }}">
                        <i class="bi bi-building"></i>{{ $agency->name }}<span>{{ $agencyCounts[$agency->id] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </aside>

    <section class="card siget-card">
        <div class="siget-file-toolbar">
            <div>
                <div class="small text-secondary">
                    SIGET / Repositorio / Dependencias
                    @if(!empty($filters['agency_id']))
                        / {{ optional($agencies->firstWhere('id', (int)$filters['agency_id']))->name }}
                    @endif
                </div>
                <strong>{{ !empty($filters['agency_id']) ? 'Expedientes institucionales de la dependencia' : 'Carpetas institucionales' }}</strong>
            </div>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="agency_id" value="{{ $filters['agency_id'] ?? '' }}">
                <input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar dependencia, pauta o mes">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="siget-file-section">
            <h2>Repositorio por dependencia</h2>
            <p class="text-secondary mb-1">La carpeta institucional es la unidad de revisión. Dentro se agrupan las cargas programadas por <strong>mes y pauta contratada</strong>.</p>
            <p class="small text-secondary">Cada expediente mensual contiene las evidencias de todas las direcciones de la dependencia. La validación, revisión y reporte se realizan sobre el conjunto.</p>

            <div class="siget-folder-grid">
                @forelse($dependencySummaries as $summary)
                    @php
                        $selected = (int)($filters['agency_id'] ?? 0) === (int)$summary->agency->id;
                    @endphp
                    <a href="{{ route('repository.index', ['agency_id' => $summary->agency->id]) }}" class="siget-folder-card {{ $selected ? 'border border-primary' : '' }}">
                        <i class="bi bi-folder-fill"></i>
                        <strong>{{ $summary->agency->name }}</strong>
                        <small>{{ $summary->monthlyPautas->count() }} carpeta(s) mes/pauta</small>
                        <small>{{ $summary->directions->count() }} dirección(es)</small>
                        <small>{{ $summary->evidence_count }} evidencia(s) · {{ $summary->file_count }} archivo(s)</small>
                    </a>
                @empty
                    <div class="text-secondary">No existen dependencias dentro del alcance del usuario.</div>
                @endforelse
            </div>
        </div>

        @if(!empty($filters['agency_id']))
            <div class="siget-file-section border-top">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
                    <div>
                        <h2>Expediente de la dependencia por mes y pauta</h2>
                        <p class="text-secondary mb-0">No se muestran evidencias como carpetas independientes.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('repository.index') }}">Ver todas las dependencias</a>
                </div>

                @php
                    $selectedSummary = $dependencySummaries->firstWhere('agency.id', (int)$filters['agency_id']);
                @endphp

                @if($selectedSummary)
                    @forelse($selectedSummary->monthlyPautas as $group)
                        @php
                            $firstLoad = $group->first_load;
                            $from = $group->loads->min('effective_open_at');
                            $to = $group->loads->max('effective_close_at');
                        @endphp

                        <div class="card border mb-4">
                            <div class="card-header bg-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <div class="small text-secondary"><i class="bi bi-calendar3 me-1"></i>{{ $group->month_label }}</div>
                                    <h3 class="h5 mb-1"><i class="bi bi-folder2-open me-1"></i>{{ optional($group->template)->name ?? ($firstLoad->title ?? 'Pauta contratada') }}</h3>
                                    <div class="small text-secondary">{{ $group->load_count }} carga(s) programada(s) · {{ $group->directions->count() }} dirección(es)</div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @if($firstLoad)
                                        <a href="{{ route('loads.show', $firstLoad) }}" class="btn btn-sm btn-primary"><i class="bi bi-shield-check me-1"></i>Revisión general del expediente</a>
                                    @endif
                                    @if($from)
                                        <a href="{{ route('reports.pdf', ['agency_id' => $selectedSummary->agency->id, 'from' => $from->format('Y-m-d'), 'to' => $to ? $to->format('Y-m-d') : $from->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i>Reporte general</a>
                                    @endif
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">Direcciones</small><strong class="fs-4">{{ $group->directions->count() }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">Requisitos obligatorios</small><strong class="fs-4">{{ $group->required_count }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">Evidencias</small><strong class="fs-4">{{ $group->evidence_count }}</strong></div></div>
                                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary d-block">Archivos</small><strong class="fs-4">{{ $group->file_count }}</strong></div></div>
                                </div>

                                <h4 class="h6 mb-3">Direcciones y evidencias integradas en esta pauta</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Dirección</th><th>Evidencias programadas</th><th>Archivos</th><th>Estado de entregable</th></tr></thead>
                                        <tbody>
                                        @foreach($group->directions as $direction)
                                            @php
                                                $directionDeliverables = $group->deliverables->where('organizational_unit_id', $direction->id);
                                                $directionEvidences = $directionDeliverables->flatMap->evidences;
                                                $validated = $directionDeliverables->filter(function ($deliverable) {
                                                    $status = $deliverable->status instanceof \BackedEnum ? $deliverable->status->value : $deliverable->status;
                                                    return in_array($status, ['VALIDADO', 'CERRADO'], true);
                                                })->count();
                                            @endphp
                                            <tr>
                                                <td><i class="bi bi-diagram-3 me-1"></i>{{ $direction->name }}</td>
                                                <td>{{ $directionEvidences->count() }}</td>
                                                <td>{{ $directionEvidences->sum(fn($evidence) => $evidence->files->count()) }}</td>
                                                <td>{{ $validated }}/{{ $directionDeliverables->count() }} validados</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="alert alert-info mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><strong>Regla institucional:</strong> las evidencias se capturan por dirección, pero Enlace Institucional revisa este conjunto mensual/pauta como un solo expediente y emite un reporte general.</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-secondary">La dependencia no tiene cargas programadas.</div>
                    @endforelse
                @else
                    <div class="text-secondary">No hay información para la dependencia seleccionada.</div>
                @endif
            </div>
        @endif

        @if(request()->routeIs('loads.mine') && auth()->user()->hasPermission('evidence.upload'))
            <div class="siget-file-section border-top">
                <h2>Captura de evidencias por dirección</h2>
                <p class="text-secondary">La captura permanece ligada al entregable y a su dirección para conservar trazabilidad. El expediente institucional se agrupa después por dependencia, mes y pauta.</p>
                @forelse($loads as $load)
                    <div class="card border mb-3">
                        <div class="card-header"><strong>{{ $load->title }}</strong> · {{ optional($load->agency)->name }} · {{ $load->period_label }}</div>
                        <div class="card-body"><div class="row g-3">
                            @foreach($load->deliverables as $deliverable)
                                @php
                                    $evidence = $deliverable->evidences->sortByDesc('id')->first();
                                @endphp
                                <div class="col-xl-6">
                                    <div class="border rounded p-3 h-100">
                                        <span class="badge text-bg-light">{{ optional($deliverable->organizationalUnit)->name }}</span>
                                        <h3 class="h6 mt-2">{{ optional($deliverable->templateRequirement)->name }}</h3>
                                        @if($evidence)
                                            <div class="small mb-2"><strong>{{ $evidence->title }}</strong> · {{ $evidence->files->count() }} archivo(s)</div>
                                        @endif
                                        <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}">
                                            <div class="input-group">
                                                <input name="title" class="form-control" placeholder="Título de la evidencia" value="{{ $evidence?->title }}">
                                                <input name="file" type="file" class="form-control" required>
                                                <button class="btn btn-primary" type="submit">Adjuntar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div></div>
                    </div>
                @empty
                    <div class="text-secondary">No hay cargas disponibles.</div>
                @endforelse
            </div>
        @endif

        <div class="siget-file-section border-top">
            <h2>Archivos recientes</h2>
            <div class="siget-file-grid">
                @forelse($recentFiles as $file)
                    <a href="{{ route('evidence-files.download', $file) }}" class="siget-file-card">
                        <i class="bi bi-file-earmark"></i>
                        <span><strong>{{ $file->original_name }}</strong><small>{{ optional(optional($file->evidence)->scheduledLoad)->agency?->name }} · {{ optional(optional($file->evidence)->deliverable)->organizationalUnit?->name }}</small></span>
                        <small>{{ number_format($file->size_bytes / 1024, 1) }} KB</small>
                    </a>
                @empty
                    <div class="text-secondary">No hay archivos recientes.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
