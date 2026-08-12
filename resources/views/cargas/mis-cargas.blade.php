@extends('layouts.app')
@section('title', 'Mis cargas')
@section('page-title', 'Mis cargas')
@section('page-subtitle', 'Repositorio operativo de evidencias programadas asignadas a tu alcance.')

@section('content')
<div class="siget-file-manager">
    <section class="card siget-card">
        <div class="siget-file-toolbar">
            <div>
                <div class="small text-secondary">SIGET / Mis cargas</div>
                <strong>Captura y cierre de evidencias programadas</strong>
            </div>
        </div>

        <div class="siget-file-section">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i>
                Este repositorio es exclusivo del usuario operativo. Las evidencias se clasifican por dependencia, mes, pauta y dirección. Al cerrar y enviar una evidencia, queda disponible para el Repositorio de Revisión institucional y para el repositorio de su dirección, sin duplicar físicamente el archivo.
            </div>

            <form method="GET" action="{{ route('loads.mine') }}" class="card border mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold" for="agency_id">Dependencia</label>
                            <select id="agency_id" name="agency_id" class="form-select">
                                <option value="">Todas las dependencias</option>
                                @foreach($agencies as $agency)
                                    <option value="{{ $agency->id }}" @selected((string)request('agency_id') === (string)$agency->id)>
                                        {{ $agency->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold" for="month">Mes programado</label>
                            <select id="month" name="month" class="form-select">
                                <option value="">Todos los meses</option>
                                @foreach($months as $month)
                                    <option value="{{ $month }}" @selected(request('month') === $month)>
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold" for="template_id">Pauta contratada</label>
                            <select id="template_id" name="template_id" class="form-select">
                                <option value="">Todas las pautas</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected((string)request('template_id') === (string)$template->id)>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-semibold" for="unit_id">Dirección</label>
                            <select id="unit_id" name="unit_id" class="form-select">
                                <option value="">Todas las direcciones</option>
                                @foreach($filterUnits as $unit)
                                    <option value="{{ $unit->id }}" @selected((string)request('unit_id') === (string)$unit->id)>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-funnel me-1"></i>Filtrar evidencias programadas
                            </button>
                            <a class="btn btn-outline-secondary" href="{{ route('loads.mine') }}">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            @forelse($loads->groupBy(fn ($load) => optional($load->agency)->id ?? 'sin-dependencia') as $agencyLoads)
                @php($agency = $agencyLoads->first()->agency)
                <section class="card border mb-4">
                    <div class="card-header bg-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="small text-secondary">Dependencia</div>
                            <h2 class="h5 mb-0">{{ $agency?->name ?? 'Sin dependencia' }}</h2>
                        </div>
                        <span class="badge text-bg-primary">{{ $agencyLoads->count() }} carga(s) programada(s)</span>
                    </div>

                    <div class="card-body">
                        @foreach($agencyLoads as $load)
                            <article class="card border mb-4">
                                <div class="card-header bg-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                    <div>
                                        <div class="small text-secondary">
                                            {{ $load->period_label }} · {{ $load->effective_open_at?->format('d/m/Y H:i') }}
                                        </div>
                                        <h3 class="h6 mb-1">{{ $load->title }}</h3>
                                        <div class="small text-secondary">
                                            <i class="bi bi-file-earmark-text me-1"></i>{{ optional($load->template)->name ?? 'Pauta contratada' }}
                                        </div>
                                    </div>
                                    <a href="{{ route('loads.show', $load) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-folder2-open me-1"></i>Ver carga
                                    </a>
                                </div>

                                <div class="card-body">
                                    <div class="row g-3">
                                        @forelse($load->deliverables as $deliverable)
                                            @php
                                                $evidence = $deliverable->evidences->sortByDesc('id')->first();
                                                $direction = $deliverable->organizationalUnit;
                                                $requirement = $deliverable->templateRequirement;
                                                $minFiles = (int) ($requirement?->min_files ?? 1);
                                                $currentFiles = $evidence?->files->where('version', $evidence->current_version)->count() ?? 0;
                                                $status = $evidence?->status?->value ?? (string) ($evidence?->status ?? '');
                                                $closed = in_array($status, ['ENVIADO', 'VALIDADO', 'CERRADO'], true);
                                            @endphp
                                            <div class="col-xl-6">
                                                <div class="border rounded p-3 h-100">
                                                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                                                        <span class="badge text-bg-light">
                                                            <i class="bi bi-diagram-3 me-1"></i>{{ $direction?->name ?? 'Sin dirección' }}
                                                        </span>
                                                        @if($closed)
                                                            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Cerrada y enviada</span>
                                                        @elseif($evidence)
                                                            <span class="badge text-bg-warning">{{ $currentFiles }}/{{ $minFiles }} archivo(s)</span>
                                                        @else
                                                            <span class="badge text-bg-secondary">Pendiente de captura</span>
                                                        @endif
                                                    </div>

                                                    <h4 class="h6 mt-3 mb-1">{{ $requirement?->name ?? 'Entregable programado' }}</h4>
                                                    @if($requirement?->description)
                                                        <p class="small text-secondary mb-2">{{ $requirement->description }}</p>
                                                    @endif

                                                    @if($evidence)
                                                        <div class="small mb-3">
                                                            <strong>{{ $evidence->title }}</strong>
                                                            <span class="text-secondary">· {{ $evidence->files->count() }} archivo(s) total</span>
                                                        </div>
                                                    @endif

                                                    @if(!$evidence)
                                                        <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">
                                                            @csrf
                                                            <input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}">
                                                            <div class="mb-2">
                                                                <input name="title" class="form-control" placeholder="Título de la evidencia programada" required>
                                                            </div>
                                                            <div class="input-group">
                                                                <input name="file" type="file" class="form-control" required>
                                                                <button class="btn btn-primary" type="submit">
                                                                    <i class="bi bi-paperclip me-1"></i>Adjuntar evidencia
                                                                </button>
                                                            </div>
                                                        </form>
                                                    @elseif(!$closed)
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="{{ route('evidences.show', $evidence) }}" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-folder2-open me-1"></i>Gestionar archivos
                                                            </a>
                                                            <form action="{{ route('evidences.submit', $evidence) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                <button class="btn btn-sm btn-success" type="submit" @disabled($currentFiles < $minFiles)>
                                                                    <i class="bi bi-send-check me-1"></i>Cerrar evidencia y enviar
                                                                </button>
                                                            </form>
                                                        </div>
                                                        @if($currentFiles < $minFiles)
                                                            <div class="small text-danger mt-2">
                                                                Debes adjuntar al menos {{ $minFiles }} archivo(s) antes de cerrar y enviar.
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="{{ route('evidences.show', $evidence) }}" class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-eye me-1"></i>Consultar evidencia
                                                            </a>
                                                            <span class="small text-success align-self-center">
                                                                <i class="bi bi-check2-circle me-1"></i>Disponible para revisión institucional y por dirección.
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12 text-secondary">No hay evidencias programadas dentro de esta carga para tu alcance.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    No tienes evidencias programadas asignadas dentro de tu alcance con los filtros seleccionados.
                </div>
            @endforelse

            <div class="mt-3">
                {{ $loads->links() }}
            </div>
        </div>
    </section>
</div>
@endsection
