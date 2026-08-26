@extends('layouts.app')

@section('title', 'Dependencias')

@section('page-title', 'Dependencias y estructura organizacional')

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Revisa los datos:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ======================================================
         CREACIÓN
         ====================================================== --}}
    <div class="row g-4 mb-4">

        <div class="col-xl-4">

            <div class="card siget-card mb-4">
                <div class="card-header">
                    <h2 class="mb-0">Nueva dependencia</h2>
                </div>

                <div class="card-body">

                    <p class="text-muted">
                        Al crear una dependencia se generan automáticamente
                        sus dos direcciones institucionales.
                    </p>

                    <form method="POST"
                          action="{{ route('admin.agencies.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input
                                type="text"
                                name="code"
                                class="form-control"
                                maxlength="50"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                maxlength="220"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Razón social</label>
                            <input
                                type="text"
                                name="legal_name"
                                class="form-control"
                                maxlength="260">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Crear dependencia
                        </button>

                    </form>
                </div>
            </div>

            {{-- NUEVA UNIDAD --}}
            <div class="card siget-card">

                <div class="card-header">
                    <h2 class="mb-0">Nueva unidad</h2>
                </div>

                <div class="card-body">

                    <form method="POST"
                          action="{{ route('admin.units.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Dependencia</label>

                            <select
                                name="contracting_agency_id"
                                class="form-select"
                                required>

                                @foreach($agencies as $agency)
                                    <option value="{{ $agency->id }}">
                                        {{ $agency->code }} · {{ $agency->name }}
                                    </option>
                                @endforeach

                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input
                                type="text"
                                name="code"
                                class="form-control"
                                maxlength="70"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                maxlength="220"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo</label>

                            <select
                                name="unit_type"
                                class="form-select"
                                required>

                                <option value="AREA">Área</option>
                                <option value="COORDINATION">Coordinación</option>
                                <option value="DIRECTION">Dirección</option>

                            </select>
                        </div>

                        <button type="submit"
                                class="btn btn-outline-primary w-100">
                            Crear unidad
                        </button>

                    </form>

                </div>
            </div>

        </div>

        {{-- ==================================================
             DEPENDENCIAS EXISTENTES
             ================================================== --}}
        <div class="col-xl-8">

            @forelse($agencies as $agency)

                <div class="card siget-card mb-4">

                    {{-- ENCABEZADO --}}
                    <div class="card-header d-flex justify-content-between align-items-start">

                        <div>
                            <h2 class="mb-1">
                                {{ $agency->name }}
                            </h2>

                            <div class="text-muted">
                                Código:
                                <strong>{{ $agency->code }}</strong>

                                @if($agency->legal_name)
                                    · {{ $agency->legal_name }}
                                @endif
                            </div>
                        </div>

                        <span class="badge {{ $agency->active
                            ? 'text-bg-success'
                            : 'text-bg-secondary' }}">
                            {{ $agency->active ? 'ACTIVA' : 'INACTIVA' }}
                        </span>

                    </div>

                    {{-- ==================================================
                         EDITAR DEPENDENCIA
                         ================================================== --}}
                    <div class="card-body border-bottom">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div>
                                <h3 class="mb-1">
                                    Datos de la dependencia
                                </h3>

                                <small class="text-muted">
                                    Modificar información general sin alterar
                                    las cargas existentes.
                                </small>
                            </div>

                            <span class="badge text-bg-light">
                                Editar
                            </span>

                        </div>

                        <form method="POST"
                              action="{{ route('admin.agencies.update', $agency) }}">

                            @csrf
                            @method('PATCH')

                            <div class="row g-3">

                                <div class="col-md-3">
                                    <label class="form-label">
                                        Código
                                    </label>

                                    <input
                                        type="text"
                                        name="code"
                                        value="{{ $agency->code }}"
                                        class="form-control"
                                        maxlength="50"
                                        required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">
                                        Nombre
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $agency->name }}"
                                        class="form-control"
                                        maxlength="220"
                                        required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">
                                        Razón social
                                    </label>

                                    <input
                                        type="text"
                                        name="legal_name"
                                        value="{{ $agency->legal_name }}"
                                        class="form-control"
                                        maxlength="260">
                                </div>

                                <div class="col-md-2 d-flex align-items-end">

                                    <button type="submit"
                                            class="btn btn-primary w-100">
                                        Guardar
                                    </button>

                                </div>

                            </div>

                        </form>

                    </div>

                    {{-- ==================================================
                         DIRECCIONES
                         ================================================== --}}
                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div>
                                <h3 class="mb-1">
                                    Direcciones institucionales
                                </h3>

                                <small class="text-muted">
                                    La dependencia debe contar con estas
                                    dos direcciones.
                                </small>
                            </div>

                            <span class="badge text-bg-primary">
                                {{ $agency->units->whereIn('code', ['DIR_A', 'DIR_B'])->count() }}
                                / 2
                            </span>

                        </div>

                        @php
                            $directions = $agency->units
                                ->whereIn('code', ['DIR_A', 'DIR_B']);

                            $otherUnits = $agency->units
                                ->whereNotIn('code', ['DIR_A', 'DIR_B']);
                        @endphp

                        <div class="row g-3">

                            @foreach($directions as $unit)

                                <div class="col-lg-6">

                                    <div class="border rounded p-3 h-100">

                                        <div class="d-flex justify-content-between align-items-start mb-3">

                                            <div>
                                                <div class="fw-bold fs-5">
                                                    {{ $unit->name }}
                                                </div>

                                                <small class="text-muted">
                                                    {{ $unit->code }}
                                                    · Dirección institucional
                                                </small>
                                            </div>

                                            <span class="badge text-bg-primary">
                                                ESTRUCTURAL
                                            </span>

                                        </div>

                                        <form method="POST"
                                              action="{{ route('admin.units.update', $unit) }}">

                                            @csrf
                                            @method('PATCH')

                                            {{-- No se permite cambiar DIR_A / DIR_B --}}
                                            <input
                                                type="hidden"
                                                name="code"
                                                value="{{ $unit->code }}">

                                            <input
                                                type="hidden"
                                                name="unit_type"
                                                value="DIRECTION">

                                            <div class="mb-3">

                                                <label class="form-label">
                                                    Nombre
                                                </label>

                                                <input
                                                    type="text"
                                                    name="name"
                                                    value="{{ $unit->name }}"
                                                    class="form-control"
                                                    maxlength="220"
                                                    required>

                                            </div>

                                            <div class="d-flex justify-content-between align-items-center">

                                                <span class="badge {{ $unit->active
                                                    ? 'text-bg-success'
                                                    : 'text-bg-secondary' }}">
                                                    {{ $unit->active
                                                        ? 'ACTIVA'
                                                        : 'INACTIVA' }}
                                                </span>

                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-primary">
                                                    Guardar dirección
                                                </button>

                                            </div>

                                        </form>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                        {{-- ==================================================
                             OTRAS UNIDADES
                             ================================================== --}}
                        @if($otherUnits->isNotEmpty())

                            <hr class="my-4">

                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <div>
                                    <h3 class="mb-1">
                                        Áreas y coordinaciones
                                    </h3>

                                    <small class="text-muted">
                                        Unidades adicionales pertenecientes
                                        a esta dependencia.
                                    </small>
                                </div>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle">

                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Unidad</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th class="text-end">
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        @foreach($otherUnits as $unit)

                                            <tr>

                                                <td>
                                                    <strong>
                                                        {{ $unit->code }}
                                                    </strong>
                                                </td>

                                                <td>
                                                    {{ $unit->name }}
                                                </td>

                                                <td>
                                                    {{ $unit->unit_type }}
                                                </td>

                                                <td>
                                                    <span class="badge {{ $unit->active
                                                        ? 'text-bg-success'
                                                        : 'text-bg-secondary' }}">
                                                        {{ $unit->active
                                                            ? 'ACTIVA'
                                                            : 'INACTIVA' }}
                                                    </span>
                                                </td>

                                                <td class="text-end">

                                                    <details>

                                                        <summary
                                                            class="btn btn-sm btn-outline-secondary">
                                                            Modificar
                                                        </summary>

                                                        <div
                                                            class="border rounded p-3 mt-2 text-start">

                                                            <form
                                                                method="POST"
                                                                action="{{ route('admin.units.update', $unit) }}">

                                                                @csrf
                                                                @method('PATCH')

                                                                <div class="mb-2">

                                                                    <label class="form-label">
                                                                        Código
                                                                    </label>

                                                                    <input
                                                                        type="text"
                                                                        name="code"
                                                                        value="{{ $unit->code }}"
                                                                        class="form-control"
                                                                        maxlength="70"
                                                                        required>

                                                                </div>

                                                                <div class="mb-2">

                                                                    <label class="form-label">
                                                                        Nombre
                                                                    </label>

                                                                    <input
                                                                        type="text"
                                                                        name="name"
                                                                        value="{{ $unit->name }}"
                                                                        class="form-control"
                                                                        maxlength="220"
                                                                        required>

                                                                </div>

                                                                <div class="mb-2">

                                                                    <label class="form-label">
                                                                        Tipo
                                                                    </label>

                                                                    <select
                                                                        name="unit_type"
                                                                        class="form-select"
                                                                        required>

                                                                        <option
                                                                            value="AREA"
                                                                            {{ $unit->unit_type === 'AREA' ? 'selected' : '' }}>
                                                                            Área
                                                                        </option>

                                                                        <option
                                                                            value="COORDINATION"
                                                                            {{ $unit->unit_type === 'COORDINATION' ? 'selected' : '' }}>
                                                                            Coordinación
                                                                        </option>

                                                                        <option
                                                                            value="DIRECTION"
                                                                            {{ $unit->unit_type === 'DIRECTION' ? 'selected' : '' }}>
                                                                            Dirección
                                                                        </option>

                                                                    </select>

                                                                </div>

                                                                <div class="form-check mb-3">

                                                                    <input
                                                                        type="hidden"
                                                                        name="active"
                                                                        value="0">

                                                                    <input
                                                                        type="checkbox"
                                                                        name="active"
                                                                        value="1"
                                                                        class="form-check-input"
                                                                        id="active-{{ $unit->id }}"
                                                                        {{ $unit->active ? 'checked' : '' }}>

                                                                    <label
                                                                        class="form-check-label"
                                                                        for="active-{{ $unit->id }}">
                                                                        Unidad activa
                                                                    </label>

                                                                </div>

                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-primary btn-sm">
                                                                    Guardar cambios
                                                                </button>

                                                            </form>

                                                        </div>

                                                    </details>

                                                </td>

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        @endif

                    </div>

                </div>

            @empty

                <div class="alert alert-info">
                    No existen dependencias registradas.
                </div>

            @endforelse

        </div>

    </div>

</div>
@endsection
