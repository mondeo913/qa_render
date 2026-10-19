@extends('layouts.app')
@section('title','Vista previa')
@section('page-title','Validación de pauta')
@section('content')
<div class="alert alert-info">Dependencia: <strong>{{ \App\Models\ContractingAgency::find($import->contracting_agency_id)?->name }}</strong>. Todas las fechas de esta pauta se programarán para esta dependencia y generarán entregables para ambas direcciones al confirmar.</div>
<div class="row g-3 mb-4">
    @foreach([['Registros',$import->total_rows],['Válidas',$import->valid_rows],['Errores',$import->error_rows],['Estado',$import->status]] as [$label,$value])
        <div class="col-md-3"><div class="siget-kpi"><div><small>{{ $label }}</small><strong>{{ $value }}</strong></div></div></div>
    @endforeach
</div>

@if($import->warnings)
<div class="alert alert-warning"><strong>Reglas aplicadas:</strong><ul class="mb-0">@foreach($import->warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>
@endif

<div class="card siget-card">
    <div class="card-header">
        <div><h2>Registros detectados</h2><p>{{ $import->original_filename }} · SHA-256 {{ $import->sha256 }}</p></div>
        @if($import->status === 'VALIDATED')
            <form method="POST" action="{{ route('calendar.import.confirm',$import) }}">
                @csrf
                <button class="btn btn-success" onclick="return confirm('Se crearán cargas agrupadas por fecha. ¿Continuar?')">
                    <i class="bi bi-check2-circle"></i> Confirmar y crear cargas
                </button>
            </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Hoja</th><th>Celda</th><th>Fecha</th><th>Servicio</th><th>Resultado</th></tr></thead>
            <tbody>
            @foreach($import->rows as $row)
                <tr>
                    <td>{{ $row->sheet_name }}</td>
                    <td>{{ $row->source_column }}{{ $row->row_number }}</td>
                    <td>{{ $row->original_open_at?->format('d/m/Y') }}</td>
                    <td>{{ $row->delivery_name }}@if($row->payload['service']['nombre_archivo_bitacora'] ?? null)<small class="d-block text-muted text-break">{{ $row->payload['service']['nombre_archivo_bitacora'] }}</small>@endif</td>
                    <td><span class="badge {{ $row->is_valid ? 'text-bg-success' : 'text-bg-danger' }}">{{ $row->is_valid ? 'Válida' : 'Error' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
