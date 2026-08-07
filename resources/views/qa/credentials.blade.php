@extends('layouts.app')
@section('title','Credenciales QA')
@section('page-title','Credenciales QA')
@section('content')
<div class="card siget-card">
 <div class="card-body">
  <h1 class="h4">Usuarios demostrativos</h1>
  <p class="text-secondary">Todos usan la contraseña definida durante la instalación.</p>
  <table class="table">
   <thead><tr><th>Rol</th><th>Correo</th></tr></thead>
   <tbody>
    <tr><td>Administrador</td><td>{{ env('SIGET_ADMIN_EMAIL','admin@siget.local') }}</td></tr>
    <tr><td>Director General</td><td>director.general@siget.local</td></tr>
    <tr><td>Director</td><td>director@siget.local</td></tr>
    <tr><td>Enlace Institucional</td><td>enlace@siget.local</td></tr>
    <tr><td>Operador Monitoreo</td><td>operador.monitoreo@siget.local</td></tr>
    <tr><td>Operador Producción</td><td>operador.produccion@siget.local</td></tr>
    <tr><td>Fiscalizador</td><td>fiscalizador@siget.local</td></tr>
   </tbody>
  </table>
 </div>
</div>
@endsection
