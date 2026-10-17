<form method="GET" class="siget-dashboard-filters" id="siget-dashboard-filters" action="{{ route('dashboard') }}">
    <div class="row g-3 align-items-end">
        <div class="col-xl-3 col-md-6">
            <label for="siget-agency-filter" class="form-label">Dependencia</label>
            <select name="agency_id" id="siget-agency-filter" class="form-select">
                <option value="">Todas las dependencias autorizadas</option>
                @foreach($filterAgencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-4 col-md-6">
            <label for="siget-pauta-filter" class="form-label">Pauta</label>
            <select name="pauta_id" id="siget-pauta-filter" class="form-select">
                <option value="">Todas las pautas</option>
                @foreach($filterPautas as $pauta)<option value="{{ $pauta['value'] }}" data-agency-id="{{ $pauta['agency_id'] }}" @selected((string)($filters['pauta_id'] ?? '') === (string)$pauta['value'])>{{ $pauta['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-xl-3 col-md-6">
            <label for="siget-unit-filter" class="form-label">Dirección</label>
            <select name="organizational_unit_id" id="siget-unit-filter" class="form-select">
                <option value="">Todas las direcciones autorizadas</option>
                @foreach($filterUnits as $unit)
                    @php $ids=implode(',', $unit->filter_unit_ids); @endphp
                    <option value="{{ $ids }}" @selected((string)($filters['organizational_unit_id'] ?? '') === $ids)>{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-md-6 d-flex gap-2"><button class="btn btn-primary" type="submit">Aplicar</button><a class="btn btn-outline-secondary" href="{{ route('dashboard') }}">Limpiar</a></div>
    </div>
    <details class="mt-3" @if(!empty($filters['from']) || !empty($filters['to']) || !empty($filters['status']) || !empty($filters['responsible_id']) || !empty($filters['deliverable_group'])) open @endif>
        <summary>Más filtros</summary>
        <div class="row g-3 mt-1">
            <div class="col-lg-3 col-md-6"><label class="form-label" for="siget-responsible-filter">Responsable</label><select id="siget-responsible-filter" name="responsible_id" class="form-select"><option value="">Todos los responsables</option>@foreach($filterResponsibles as $responsible)<option value="{{ $responsible->id }}" @selected((string)($filters['responsible_id'] ?? '') === (string)$responsible->id)>{{ $responsible->name }}</option>@endforeach</select></div>
            <div class="col-lg-3 col-md-6"><label class="form-label" for="dashboard-group">Estado del entregable</label><select name="deliverable_group" id="dashboard-group" class="form-select"><option value="">Todos los estados</option>@foreach(['pending'=>'Pendiente de envío','review'=>'En revisión','observed'=>'Con observaciones','validated'=>'Validado / cerrado'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['deliverable_group'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-lg-3 col-md-6"><label class="form-label" for="dashboard-from">Mes programado desde</label><input class="form-control" id="dashboard-from" name="from" type="month" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-lg-3 col-md-6"><label class="form-label" for="dashboard-to">Mes programado hasta</label><input class="form-control" id="dashboard-to" name="to" type="month" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-lg-6"><label class="form-label" for="dashboard-load-status">Estado del expediente</label><select id="dashboard-load-status" name="status" class="form-select"><option value="">Todos los estados</option>@foreach(\App\Enums\ScheduledLoadStatus::cases() as $state)<option value="{{ $state->value }}" @selected(($filters['status'] ?? '') === $state->value)>{{ str_replace('_',' ', $state->value) }}</option>@endforeach</select></div>
        </div>
    </details>
</form>
<script>
(() => {
    const form = document.getElementById('siget-dashboard-filters');
    const agency = form.elements.agency_id;
    agency.addEventListener('change', () => {
        // Reload options and clear dependent selections from the previous dependency.
        form.elements.pauta_id.value = '';
        form.elements.responsible_id.value = '';
        form.requestSubmit();
    });
    form.addEventListener('submit', e => {
        const from = form.elements.from, to = form.elements.to;
        if (from.value && to.value && from.value > to.value) {
            e.preventDefault();
            to.setCustomValidity('El mes final debe ser igual o posterior al inicial.');
            to.reportValidity();
        }
    });
    form.elements.to.addEventListener('input', () => form.elements.to.setCustomValidity(''));
    form.elements.from.addEventListener('input', () => form.elements.to.setCustomValidity(''));
})();
</script>
