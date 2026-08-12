@extends('layouts.app')
@section('title','Expediente')
@section('page-title','Expediente de carga #'.$load->id)
@section('content')
@php
    $status = $load->status instanceof \BackedEnum ? $load->status->value : $load->status;
    $role = auth()->user()->role?->code;
@endphp

<div class="card siget-card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <span class="badge text-bg-primary mb-2">{{ $load->agency?->name }}</span>
                <h2 class="h3 mb-1">{{ $load->title }}</h2>
                <p class="text-secondary mb-0">{{ $load->period_label }} · {{ $load->effective_open_at?->format('d/m/Y H:i') }} a {{ $load->effective_close_at?->format('d/m/Y H:i') }}</p>
                <div class="mt-2"><span class="badge {{ $uploadEnabled ? 'text-bg-success' : 'text-bg-secondary' }}"><i class="bi {{ $uploadEnabled ? 'bi-unlock' : 'bi-lock' }} me-1"></i>{{ $uploadEnabled ? 'Fecha habilitada para evidencias' : 'Fecha programada / carga no habilitada' }}</span></div>
                <small class="text-secondary d-block mt-1">{{ $uploadTooltip }}</small>
            </div>
            <div class="text-end">
                <span class="badge siget-status fs-6">{{ $status }}</span>
                <div class="mt-2"><strong>{{ $load->completion_percentage }}% completado</strong></div>
                <div class="progress mt-2" style="width:240px"><div class="progress-bar" style="width:{{ $load->completion_percentage }}%"></div></div>
            </div>
        </div>
        @if($load->is_blocked)<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-lock"></i> {{ $load->block_reason }}</div>@endif
    </div>
</div>

@if($load->metadata['services'] ?? false)
<div class="card siget-card mb-4">
    <div class="card-header"><div><h2>Servicios agrupados del Excel</h2><p>{{ $load->metadata['service_count'] ?? count($load->metadata['services']) }} marcas X en esta fecha</p></div></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0"><thead><tr><th>Campaña</th><th>Canal</th><th>Plaza</th><th>Programa</th><th>Horario</th></tr></thead>
        <tbody>@foreach($load->metadata['services'] as $item)<tr>
            <td>{{ data_get($item,'service.campana') }}</td><td>{{ data_get($item,'service.canal') }}</td><td>{{ data_get($item,'service.plaza') }}</td><td>{{ data_get($item,'service.espacio_programatico') }}</td><td>{{ data_get($item,'service.franja_horaria') }}</td>
        </tr>@endforeach</tbody></table>
    </div>
</div>
@endif

<div class="row g-4">
@foreach($load->deliverables as $deliverable)
    @php($evidence = $deliverable->evidences->sortByDesc('id')->first())
    <div class="col-xl-6">
        <div class="card siget-card h-100">
            <div class="card-header">
                <div>
                    <span class="badge text-bg-light">{{ $deliverable->organizationalUnit?->name }}</span>
                    <h2 class="mt-2">{{ $deliverable->templateRequirement?->name }}</h2>
                    <p>Formatos: {{ implode(', ', $deliverable->templateRequirement?->allowed_extensions ?? []) }}</p>
                </div>
                <span class="badge siget-status">{{ $deliverable->status instanceof \BackedEnum ? $deliverable->status->value : $deliverable->status }}</span>
            </div>
            <div class="card-body">
                @if($evidence)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between"><strong>{{ $evidence->title }}</strong><span>V{{ $evidence->current_version }}</span></div>
                        <p class="small text-secondary mb-2">Estado: {{ $evidence->status instanceof \BackedEnum ? $evidence->status->value : $evidence->status }}</p>
                        <ul class="list-group list-group-flush mb-3">
                            @forelse($evidence->files as $file)
                                <li class="list-group-item px-0 d-flex justify-content-between">
                                    <span><i class="bi bi-file-earmark"></i> {{ $file->original_name }} <small>v{{ $file->version }} · {{ number_format($file->size_bytes/1024,1) }} KB</small></span>
                                    <a href="{{ route('evidence-files.download',$file) }}">Descargar</a>
                                </li>
                            @empty
                                <li class="list-group-item px-0 text-secondary">Evidencia QA sin archivo físico; cargue una versión real.</li>
                            @endforelse
                        </ul>
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('evidences.show',$evidence) }}">Abrir flujo de evidencia</a>
                    </div>
                @endif

                @if(auth()->user()->hasPermission('evidence.upload') && in_array($deliverable->organizational_unit_id, auth()->user()->scopes()->pluck('organizational_unit_id')->filter()->all() ?: [auth()->user()->organizational_unit_id]))
                    <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}">
                        <div class="mb-2"><input name="title" class="form-control" placeholder="Título de la evidencia"></div>
                        <div class="input-group">
                            <input name="file" type="file" class="form-control" required @disabled(!$uploadEnabled)>
                            <button class="btn btn-primary" @disabled(!$uploadEnabled)><i class="bi bi-cloud-arrow-up"></i> {{ $uploadEnabled ? 'Cargar evidencia' : 'Carga cerrada' }}</button>
                        </div>
                        @unless($uploadEnabled)<small class="text-secondary d-block mt-2"><i class="bi bi-calendar-event me-1"></i>{{ $uploadTooltip }}</small>@endunless
                    </form>
                @endif
            </div>
        </div>
    </div>
@endforeach
</div>

@if(in_array($role,['ADMINISTRADOR','ENLACE_INSTITUCIONAL']))
<div class="card siget-card mt-4">
    <div class="card-header"><div><h2>Asignación de fiscalización</h2><p>El fiscalizador solo verá los expedientes asignados.</p></div></div>
    <div class="card-body">
        <form action="{{ route('loads.assign-fiscalizador',$load) }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-5"><select name="fiscalizador_id" class="form-select" required>@foreach($fiscalizadores as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select></div>
            <div class="col-md-5"><input name="notes" class="form-control" placeholder="Observaciones de la asignación"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Asignar</button></div>
        </form>
        <div class="mt-3">@foreach($load->reviewAssignments as $assignment)<span class="badge text-bg-light me-2">{{ $assignment->fiscalizador?->name }}</span>@endforeach</div>
    </div>
</div>
@endif

@if(auth()->user()->hasPermission('scheduled_load.verify'))
<div class="card siget-card mt-4">
    <div class="card-header"><div><h2>Cierre institucional</h2><p>Checklist, expediente para firma, documento firmado y cierre irreversible.</p></div></div>
    <div class="card-body">
        <form action="{{ route('loads.checklist',$load) }}" method="POST" class="row g-3 mb-4">
            @csrf @method('PATCH')
            <div class="col-md-4 form-check ms-3"><input type="checkbox" name="evidences_correct" value="1" class="form-check-input" @checked($load->institutionalReview?->evidences_correct)><label class="form-check-label">Evidencias correctas</label></div>
            <div class="col-md-4 form-check"><input type="checkbox" name="package_prepared_for_signature" value="1" class="form-check-input" @checked($load->institutionalReview?->package_prepared_for_signature)><label class="form-check-label">Expediente preparado para firma</label></div>
            <div class="col-12"><textarea name="observations" class="form-control" placeholder="Observaciones">{{ $load->institutionalReview?->observations }}</textarea></div>
            <div class="col-12"><button class="btn btn-primary">Guardar checklist</button></div>
        </form>

        <div class="row g-3">
            <div class="col-lg-4"><form method="POST" action="{{ route('loads.signature-package',$load) }}">@csrf<button class="btn btn-outline-primary w-100"><i class="bi bi-file-pdf"></i> Generar expediente para firma</button></form></div>
            <div class="col-lg-4">
                <form method="POST" action="{{ route('loads.signed-document',$load) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" class="form-control mb-2" required>
                    <input type="text" name="signer_name" class="form-control mb-2" placeholder="Nombre del firmante">
                    <button class="btn btn-outline-success w-100">Adjuntar documento firmado</button>
                </form>
            </div>
            <div class="col-lg-4">
                <form method="POST" action="{{ route('loads.close',$load) }}" data-confirm-close>
                    @csrf
                    <textarea name="closing_comment" class="form-control mb-2" placeholder="Comentario de cierre"></textarea>
                    <button class="btn btn-success w-100"><i class="bi bi-lock"></i> Validar y cerrar</button>
                </form>
            </div>
        </div>
        @if($load->accountingNotice)
            <div class="alert alert-info mt-3 mb-0">Aviso informativo a Contabilidad: {{ $load->accountingNotice->status }} · {{ $load->accountingNotice->sent_at?->format('d/m/Y H:i') }}</div>
        @endif
    </div>
</div>
@endif

<div class="card siget-card mt-4">
    <div class="card-header"><div><h2>Trazabilidad del expediente</h2><p>Historial de transiciones</p></div></div>
    <div class="table-responsive">
        <table class="table mb-0"><thead><tr><th>Fecha</th><th>Anterior</th><th>Nuevo</th><th>Usuario</th><th>Motivo</th></tr></thead>
        <tbody>@forelse($load->statusHistory->sortByDesc('created_at') as $item)<tr><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td><td>{{ $item->old_status }}</td><td>{{ $item->new_status }}</td><td>{{ $item->user?->name }}</td><td>{{ $item->reason }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Sin cambios registrados.</td></tr>@endforelse</tbody></table>
    </div>
</div>
@endsection
