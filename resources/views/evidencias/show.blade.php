@extends('layouts.app')
@section('title','Evidencia')
@section('page-title','Flujo de evidencia #'.$evidence->id)
@section('content')
@php($status = $evidence->status instanceof \BackedEnum ? $evidence->status->value : $evidence->status)
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card siget-card">
            <div class="card-header"><div><h2>{{ $evidence->title }}</h2><p>{{ $evidence->deliverable->organizationalUnit?->name }} · Versión {{ $evidence->current_version }}</p><div class="small text-primary fw-semibold mt-1"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Pauta: {{ $evidence->scheduledLoad?->calendarImport?->original_filename ?: 'Pauta sin nombre' }}</div></div><span class="badge siget-status">{{ $status }}</span></div>
            <div class="card-body">
                <h3 class="h6">Archivos y versiones</h3>
                <div class="table-responsive">
                    <table class="table"><thead><tr><th>Archivo</th><th>Versión</th><th>SHA-256</th><th>Antivirus</th><th></th></tr></thead>
                    <tbody>@forelse($evidence->files->sortByDesc('created_at') as $file)<tr><td>{{ $file->original_name }}</td><td>v{{ $file->version }}</td><td><code>{{ Str::limit($file->sha256,20) }}</code></td><td>{{ $file->antivirus_status }}</td><td><a href="{{ route('evidence-files.download',$file) }}">Descargar</a></td></tr>@empty<tr><td colspan="5" class="text-secondary">Sin archivos físicos.</td></tr>@endforelse</tbody></table>
                </div>

                @if($canEdit)
                <form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data" class="mb-3">@csrf<input type="hidden" name="deliverable_id" value="{{ $evidence->deliverable_id }}"><label class="form-label">Adjuntar archivo a la versión vigente (una corrección inicia nueva versión)</label><div class="input-group"><input type="file" name="file" class="form-control" required><button class="btn btn-outline-primary">Adjuntar</button></div></form>
                @endif
                @if($canEdit && in_array($status,['EN_CAPTURA','CORREGIDO'],true))
                <form action="{{ route('evidences.submit',$evidence) }}" method="POST">@csrf<button class="btn btn-primary"><i class="bi bi-send"></i> Enviar a revisión</button></form>
                @endif
            </div>
        </div>

        <div class="card siget-card mt-4">
            <div class="card-header"><div><h2>Revisiones</h2><p>Decisiones institucionales y de fiscalización</p></div></div>
            <div class="list-group list-group-flush">
                @forelse($evidence->reviews->sortByDesc('created_at') as $review)
                    <div class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $review->decision }}</strong><small>{{ $review->created_at?->format('d/m/Y H:i') }}</small></div><p class="mb-0">{{ $review->comments }}</p><small>{{ $review->review_type }} · Versión {{ $review->evidence_version ?? 'histórica' }}</small></div>
                @empty<div class="p-4 text-secondary">Aún no hay revisiones.</div>@endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @if($canReview)
        <div class="card siget-card">
            <div class="card-header"><div><h2>Registrar revisión</h2><p>La revisión de Dirección precede a la institucional. El dictamen fiscalizador conserva el estado institucional.</p></div></div>
            <div class="card-body">
                <form action="{{ route('evidences.review',$evidence) }}" method="POST">
                    @csrf
                    <div class="mb-3"><label class="form-label">Decisión</label><select name="decision" class="form-select" required><option>APROBADO</option><option>OBSERVADO</option><option>RECHAZADO</option></select></div>
                    <div class="mb-3"><label class="form-label">Comentarios</label><textarea name="comments" rows="5" class="form-control"></textarea></div>
                    <button class="btn btn-success w-100">Guardar revisión</button>
                </form>
            </div>
        </div>
        @endif
        <a href="{{ route('loads.show',$evidence->scheduledLoad) }}" class="btn btn-outline-secondary w-100 mt-3">Volver al expediente</a>
    </div>
</div>
@endsection
