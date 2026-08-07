<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1f2937;font-size:11px}
.header{text-align:center;border-bottom:3px solid #23395d;padding-bottom:12px;margin-bottom:25px}
h1{font-size:19px;color:#23395d}.box{border:1px solid #cbd5e1;padding:12px;margin:12px 0}
.success{background:#e9f8f0;border-color:#86d3af}.label{color:#64748b;font-size:9px;text-transform:uppercase}
code{font-size:8px;word-break:break-all}.signature{margin-top:75px;text-align:center}
</style></head>
<body>
<div class="header"><h1>Constancia de validación y cierre</h1><div>SIGET - Sistema de Gestión de Evidencias de Transmisión</div></div>
<div class="box">
<div class="label">Carga programada</div><strong>{{ $load->title }}</strong><br>
{{ $load->agency->name }} · {{ $load->period_label }}
</div>
<div class="box success">
<strong>ESTADO FINAL: VALIDADO Y CERRADO</strong><br>
Fecha de cierre: {{ $closure->closed_at->format('d/m/Y H:i:s') }}
</div>
<div class="box">
<div class="label">Documento firmado</div>
ID {{ $closure->signed_document_id }}<br>
<div class="label" style="margin-top:8px">Hash del expediente</div>
<code>{{ $closure->package_sha256 }}</code>
</div>
<div class="box">
<div class="label">Comentario de cierre</div>
{{ $closure->closing_comment ?: 'Sin comentarios adicionales.' }}
</div>
<p>Esta constancia acredita que los entregables obligatorios fueron revisados, el expediente fue preparado para firma, el documento firmado fue incorporado y el expediente quedó bloqueado para modificaciones ordinarias.</p>
<div class="signature">
_________________________________________<br>
Enlace Institucional responsable
</div>
</body></html>