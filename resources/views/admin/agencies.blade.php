@extends('layouts.app')
@section('title','Dependencias')
@section('page-title','Dependencias y estructura organizacional')
@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card siget-card mb-4">
            <div class="card-header"><div><h2>Nueva dependencia</h2></div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.agencies.store') }}">
                    @csrf
                    <input name="code" class="form-control mb-2" placeholder="Código" required>
                    <input name="name" class="form-control mb-2" placeholder="Nombre" required>
                    <input name="legal_name" class="form-control mb-3" placeholder="Razón social">
                    <button class="btn btn-primary w-100">Crear</button>
                </form>
            </div>
        </div>
        <div class="card siget-card">
            <div class="card-header"><div><h2>Nueva unidad</h2></div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.units.store') }}">
                    @csrf
                    <select name="contracting_agency_id" class="form-select mb-2" required>
                        @foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach
                    </select>
                    <input name="code" class="form-control mb-2" placeholder="Código" required>
                    <input name="name" class="form-control mb-2" placeholder="Nombre" required>
                    <select name="unit_type" class="form-select mb-3"><option>DIRECTION</option><option>AREA</option><option>COORDINATION</option></select>
                    <button class="btn btn-primary w-100">Crear unidad</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        @foreach($agencies as $agency)
            <div class="card siget-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h2>{{ $agency->name }}</h2>
                        <p>{{ $agency->code }} · {{ $agency->legal_name }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-edit-agency="{{ $agency->id }}">Editar</button>
                        <form method="POST" action="{{ route('admin.agencies.destroy', $agency) }}" onsubmit="return confirm('¿Desea eliminar esta dependencia? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                        </form>
                    </div>
                </div>
                <div class="card-body d-none" data-agency-form="{{ $agency->id }}">
                    <form method="POST" action="{{ route('admin.agencies.update', $agency) }}">
                        @csrf
                        @method('PATCH')
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label" for="agency-code-{{ $agency->id }}">Código</label><input id="agency-code-{{ $agency->id }}" name="code" value="{{ $agency->code }}" class="form-control" required></div>
                            <div class="col-md-8"><label class="form-label" for="agency-name-{{ $agency->id }}">Nombre</label><input id="agency-name-{{ $agency->id }}" name="name" value="{{ $agency->name }}" class="form-control" required></div>
                            <div class="col-12"><label class="form-label" for="agency-legal-name-{{ $agency->id }}">Razón social</label><input id="agency-legal-name-{{ $agency->id }}" name="legal_name" value="{{ $agency->legal_name }}" class="form-control"></div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel-agency="{{ $agency->id }}">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
                        </div>
                    </form>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($agency->units as $unit)
                        <div class="list-group-item d-flex justify-content-between"><span>{{ $unit->name }}</span><span class="badge text-bg-light">{{ $unit->code }} · {{ $unit->unit_type }}</span></div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('[data-edit-agency]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector(`[data-agency-form="${button.dataset.editAgency}"]`).classList.remove('d-none');
            button.classList.add('d-none');
        });
    });
    document.querySelectorAll('[data-cancel-agency]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector(`[data-agency-form="${button.dataset.cancelAgency}"]`).classList.add('d-none');
            document.querySelector(`[data-edit-agency="${button.dataset.cancelAgency}"]`).classList.remove('d-none');
        });
    });
</script>
@endpush
