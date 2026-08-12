@extends('layouts.app')
@section('title', 'Mis cargas')
@section('page-title', 'Mis cargas')
@section('page-subtitle', 'Repositorio operativo de las cargas asignadas a tu alcance.')

@section('content')
<div class="siget-file-manager">
    <section class="card siget-card">
        <div class="siget-file-toolbar">
            <div>
                <div class="small text-secondary">SIGET / Mis cargas</div>
                <strong>Cargas asignadas</strong>
            </div>
        </div>

        <div class="siget-file-section">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i>
                Este repositorio es exclusivo del usuario operativo. Aquí solo aparecen las cargas y direcciones que forman parte de su alcance. La revisión institucional consolidada se realiza en el Repositorio de Revisión de Enlace Institucional.
            </div>

            @forelse($loads as $load)
                <div class="card border mb-4">
                    <div class="card-header bg-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="small text-secondary">
                                {{ optional($load->agency)->name }} · {{ $load->period_label }}
                            </div>
                            <h2 class="h5 mb-1">{{ $load->title }}</h2>
                            <div class="small text-secondary">
                                {{ optional($load->template)->name ?? 'Pauta contratada' }}
                            </div>
                        </div>
                        <a href="{{ route('loads.show', $load) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Ver carga
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            @forelse($load->deliverables as $deliverable)
                                @php
                                    $evidence = $deliverable->evidences->sortByDesc('id')->first();
                                    $direction = $deliverable->organizationalUnit;
                                    $requirement = $deliverable->templateRequirement;
                                @endphp
                                <div class="col-xl-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="badge text-bg-light">
                                                <i class="bi bi-diagram-3 me-1"></i>{{ $direction?->name ?? 'Sin dirección' }}
                                            </span>
                                            @if($evidence)
                                                <span class="badge text-bg-success">Evidencia registrada</span>
                                            @else
                                                <span class="badge text-bg-warning">Pendiente</span>
                                            @endif
                                        </div>

                                        <h3 class="h6 mt-3">{{ $requirement?->name ?? 'Entregable' }}</h3>
                                        @if($requirement?->description)
                                            <p class="small text-secondary mb-2">{{ $requirement->description }}</p>
                                        @endif

                                        @if($evidence)
                                            <div class="small mb-3">
                                                <strong>{{ $evidence->title }}</strong>
                                                <span class="text-secondary">· {{ $evidence->files->count() }} archivo(s)</span>
                                            </div>
                                        @endif

                                        <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}">
                                            <div class="mb-2">
                                                <input name="title" class="form-control" placeholder="Título de la evidencia" value="{{ $evidence?->title }}">
                                            </div>
                                            <div class="input-group">
                                                <input name="file" type="file" class="form-control" required>
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="bi bi-paperclip me-1"></i>Adjuntar evidencia
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-secondary">No hay entregables dentro de esta carga para tu alcance.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    No tienes cargas asignadas dentro de tu alcance.
                </div>
            @endforelse

            <div class="mt-3">
                {{ $loads->links() }}
            </div>
        </div>
    </section>
</div>
@endsection
