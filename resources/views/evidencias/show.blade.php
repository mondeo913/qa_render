@extends('layouts.app')
@section('title','Evidencia')
@section('page-title','Flujo de evidencia #'.$evidence->id)
@section('content')
@php($status = $evidence->status instanceof \BackedEnum ? $evidence->status->value : $evidence->status)
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card siget-card">
            <div class="card-header"><div><h2>{{ $evidence->title }}</h2><p>{{ $evidence->deliverable->organizationalUnit?->name }} · Versión {{ $evidence->current_version }}</p></div><span class="badge siget-status">{{ $status }}</span></div>
            <div class="card-body">
                <h3 class="h6">Archivos y versiones</h3>
                <div class="table-responsive">
                    <table class="table"><thead><tr><th>Archivo</th><th>Versión</th><th>SHA-256</th><th>Antivirus</th><th></th></tr></thead>
                    <tbody>@forelse($evidence->files->sortByDesc('created_at') as $file)<tr><td>{{ $file->original_name }}</td><td>v{{ $file->version }}</td><td><code>{{ Str::limit($file->sha256,20) }}</code></td><td>{{ $file->antivirus_status }}</td><td><a href="{{ route('evidence-files.download',$file) }}">Descargar</a></td></tr>@empty<tr><td colspan="5" class="text-secondary">Sin archivos físicos.</td></tr>@endforelse</tbody></table>
                </div>

                @if(in_array($status,['EN_CAPTURA','CORREGIDO','OBSERVADO','RECHAZADO']) && auth()->user()->hasPermission('evidence.upload'))
                <form action="{{ route('evidences.submit',$evidence) }}" method="POST">@csrf<button class="btn btn-primary"><i class="bi bi-send"></i> Enviar a revisión</button></form>
                @endif
            </div>
        </div>

        <div class="card siget-card mt-4">
            <div class="card-header"><div><h2>Revisiones</h2><p>Decisiones institucionales y de fiscalización</p></div></div>
            <div class="list-group list-group-flush">
                @forelse($evidence->reviews->sortByDesc('created_at') as $review)
                    <div class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $review->decision }}</strong><small>{{ $review->created_at?->format('d/m/Y H:i') }}</small></div><p class="mb-0">{{ $review->comments }}</p><small>{{ $review->review_type }}</small></div>
                @empty<div class="p-4 text-secondary">Aún no hay revisiones.</div>@endforelse
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @if($canReview)
        <div class="card siget-card">
            <div class="card-header"><div><h2>Registrar revisión</h2><p>La decisión actualiza evidencia, entregable y carga.</p></div></div>
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
