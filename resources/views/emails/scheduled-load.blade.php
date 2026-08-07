<!doctype html>
<html lang="es">
<body style="font-family:Arial,sans-serif;color:#1f2937">
    <h2 style="color:#23395d">{{ $notification->subject }}</h2>
    <p>{{ $notification->message }}</p>
    @if($notification->action_url)
        <p><a href="{{ $notification->action_url }}" style="background:#5b4ce6;color:white;padding:10px 16px;text-decoration:none;border-radius:6px">Abrir en SIGET</a></p>
    @endif
    <hr>
    <small>Mensaje automático del Sistema Institucional SIGET.</small>
</body>
</html>