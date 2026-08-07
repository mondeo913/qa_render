<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#1f2937}
h1{font-size:18px;color:#23395d}h2{font-size:13px;color:#23395d;margin-top:20px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:6px;vertical-align:top}
th{background:#eef2f7}.muted{color:#64748b}.page-break{page-break-before:always}
</style></head>
<body>
<h1>Expediente para firma del Director</h1>
<p><strong>Carga:</strong> {{ $load->title }}</p>
<p><strong>Dependencia:</strong> {{ $load->agency->name }}</p>
<p><strong>Periodo:</strong> {{ $load->period_label }}</p>
<p><strong>Fecha original:</strong> {{ $load->original_open_at->format('d/m/Y H:i') }} - {{ $load->original_close_at->format('d/m/Y H:i') }}</p>
<p><strong>Fecha efectiva:</strong> {{ $load->effective_open_at->format('d/m/Y H:i') }} - {{ $load->effective_close_at->format('d/m/Y H:i') }}</p>

<h2>Entregables integrados</h2>
<table>
<thead><tr><th>Dirección</th><th>Requisito</th><th>Estado</th><th>Archivos</th></tr></thead>
<tbody>
@foreach($load->deliverables as $deliverable)
<tr>
<td>{{ $deliverable->organizationalUnit?->name }}</td>
<td>{{ $deliverable->templateRequirement?->name }}</td>
<td>{{ str_replace('_',' ',$deliverable->status->value) }}</td>
<td>
@foreach($deliverable->evidences as $evidence)
    @foreach($evidence->files as $file)
    <div>{{ $file->original_name }} - SHA-256 {{ $file->sha256 }}</div>
    @endforeach
@endforeach
</td>
</tr>
@endforeach
</tbody></table>

<h2>Verificación institucional</h2>
<p>[{{ $load->institutionalReview?->evidences_correct ? 'X' : ' ' }}] Evidencias operativas completas y correctas.</p>
<p>[{{ $load->institutionalReview?->package_prepared_for_signature ? 'X' : ' ' }}] Expediente integrado y preparado para firma.</p>

<div style="margin-top:70px;text-align:center">
_________________________________________<br>
Nombre y firma del Director
</div>
</body></html>