@extends('layouts.app')
@section('title', 'Mis cargas')
@section('page-title', 'Mis cargas')
@section('page-subtitle', 'Repositorio operativo de evidencias programadas asignadas a tu alcance.')

@section('content')
<style>
    .mis-cargas-page { width: 100%; max-width: none; margin: 0; padding: 0 1rem 2rem; }
    .mis-cargas-page .siget-card { width: 100%; max-width: none; margin: 0; }
    .mis-cargas-page .siget-file-toolbar { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--bs-border-color); }
    .mis-cargas-page .siget-file-section { padding: 1.5rem; }
    .mis-cargas-page .filter-card { position: sticky; top: .75rem; z-index: 5; background: var(--bs-body-bg); }
    .mis-cargas-page .dependency-card { overflow: hidden; }
    .mis-cargas-page .dependency-header { padding: 1rem 1.25rem; }
    .mis-cargas-page .date-group + .date-group { margin-top: 1.25rem; }
    .mis-cargas-page .date-header { padding: .75rem 1rem; border: 1px solid var(--bs-border-color); border-radius: .75rem; background: var(--bs-tertiary-bg); }
    .mis-cargas-page .loads-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .mis-cargas-page .load-card { height: 100%; margin: 0 !important; display: flex; flex-direction: column; }
    .mis-cargas-page .load-card > .card-body { flex: 1; }
    .mis-cargas-page .deliverables-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .mis-cargas-page .deliverable-card { min-width: 0; height: 100%; }
    .mis-cargas-page .deliverable-title { overflow-wrap: anywhere; }
    .mis-cargas-page .evidence-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .mis-cargas-page .evidence-actions form { margin: 0; }
    @media (min-width: 1600px) { .mis-cargas-page .deliverables-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 1100px) { .mis-cargas-page .loads-grid, .mis-cargas-page .deliverables-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .mis-cargas-page { padding: 0 .5rem 1.5rem; } .mis-cargas-page .siget-file-section { padding: .75rem; } .mis-cargas-page .filter-card { position: static; } }

/* Mis cargas: misma estructura y acciones con controles compactos. */
.mis-cargas-page{padding:0 .5rem 1.25rem}
.mis-cargas-page .siget-file-toolbar{padding:.75rem 1rem}
.mis-cargas-page .siget-file-section{padding:1rem}
.mis-cargas-page .filter-card{margin-bottom:10px!important}
.mis-cargas-page .filter-card .card-header{padding:.55rem .75rem!important}
.mis-cargas-page .filter-card .card-body{padding:.7rem .75rem}
.mis-cargas-page .filter-card .row{row-gap:.5rem!important}
.mis-cargas-page .filter-card .form-label{font-size:.67rem;margin-bottom:.2rem}
.mis-cargas-page .filter-card .form-select-lg{height:31px;min-height:31px;padding:.25rem .45rem;font-size:.68rem}
.mis-cargas-page .filter-card .btn-lg{height:31px;min-height:31px;padding:.25rem .55rem;font-size:.67rem}
.mis-cargas-page .dependency-header{padding:.7rem .9rem}
.mis-cargas-page .loads-grid{gap:.65rem}
.mis-cargas-page .deliverables-grid{gap:.65rem}
.mis-cargas-page .date-header{padding:.55rem .7rem}

</style>

<div class="mis-cargas-page">
    <section class="card siget-card">
        <div class="siget-file-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div><div class="small text-secondary">SIGET / Mis cargas</div><strong class="fs-5">Captura y cierre de evidencias programadas</strong></div>
            <span class="badge rounded-pill text-bg-primary px-3 py-2">Repositorio operativo</span>
        </div>
        <div class="siget-file-section">
            <div class="alert alert-info mb-4"><i class="bi bi-info-circle me-1"></i>Este repositorio es exclusivo del usuario operativo. La <strong>Pauta</strong> es el Excel confirmado por Enlace Institucional; sus cargas, entregables y evidencias permanecen vinculados automáticamente a ese mismo archivo. Al enviar la evidencia, los KPIs de la Dirección se actualizan sobre esa Pauta y sus cargas.</div>

            <form method="GET" action="{{ route('loads.mine') }}" class="card border mb-4 filter-card">
                <div class="card-header bg-body py-3"><div class="d-flex align-items-center gap-2"><i class="bi bi-funnel"></i><strong>Filtrar evidencias programadas</strong></div></div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label fw-semibold" for="agency_id">Dependencia</label>
                            <select id="agency_id" name="agency_id" class="form-select form-select-lg">
                                <option value="">Todas las dependencias</option>
                                @foreach($agencies as $agency)<option value="{{ $agency->id }}" @if((string) request('agency_id') === (string) $agency->id) selected @endif>{{ $agency->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label fw-semibold" for="month">Mes programado</label>
                            <select id="month" name="month" class="form-select form-select-lg">
                                <option value="">Todos los meses</option>
                                @foreach($months as $month)<option value="{{ $month }}" @if(request('month') === $month) selected @endif>{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label fw-semibold" for="pauta_id">Pauta (Excel)</label>
                            <select id="pauta_id" name="pauta_id" class="form-select form-select-lg">
                                <option value="">Todas las pautas</option>
                                @foreach($pautas as $pauta)<option value="{{ $pauta->id }}" @if((string) request('pauta_id') === (string) $pauta->id) selected @endif>{{ $pauta->agency_name }} · {{ $pauta->name }} · {{ $pauta->load_count }} programación(es)</option>@endforeach
                            </select>
                            <div class="form-text">Corresponde al archivo Excel confirmado por Enlace Institucional.</div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label fw-semibold" for="unit_id">Dirección</label>
                            <select id="unit_id" name="unit_id" class="form-select form-select-lg" @if($isDirectionLocked ?? false) disabled @endif>
                                @if(!($isDirectionLocked ?? false))<option value="">Todas las direcciones</option>@endif
                                @foreach($filterUnits as $unit)<option value="{{ $unit->id }}" @if((string) request('unit_id') === (string) $unit->id || (($isDirectionLocked ?? false) && $unit->id === ($filterUnits->first()->id ?? null))) selected @endif>{{ $unit->name }}</option>@endforeach
                            </select>
                            @if($isDirectionLocked ?? false)
                                <input type="hidden" name="unit_id" value="{{ $filterUnits->first()->id ?? '' }}">
                                <div class="form-text">Dirección asignada al usuario operativo.</div>
                            @endif
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-1">
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button>
                            <a class="btn btn-outline-secondary btn-lg" href="{{ route('loads.mine') }}"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar</a>
                        </div>
                    </div>
                </div>
            </form>

            @forelse($loads->groupBy(function ($load) { return optional($load->agency)->id ?: 'sin-dependencia'; }) as $agencyLoads)
                @php $agency = $agencyLoads->first()->agency; @endphp
                <section class="card border mb-4 dependency-card">
                    <div class="card-header bg-body dependency-header d-flex justify-content-between align-items-center flex-wrap gap-3"><div><div class="small text-secondary text-uppercase">Dependencia</div><h2 class="h4 mb-0">{{ optional($agency)->name ?: 'Sin dependencia' }}</h2></div><span class="badge text-bg-primary fs-6 px-3 py-2">{{ $agencyLoads->count() }} carga(s) programada(s)</span></div>
                    <div class="card-body p-3 p-lg-4">
                        @foreach($agencyLoads->groupBy(function ($load) { return optional($load->effective_open_at)->format('Y-m-d') ?: 'sin-fecha'; }) as $date => $dateLoads)
                            <div class="date-group">
                                <div class="date-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <strong><i class="bi bi-calendar3 me-1"></i>{{ $date === 'sin-fecha' ? 'Fecha no definida' : \Carbon\Carbon::createFromFormat('Y-m-d', $date)->translatedFormat('d \d\e F \d\e Y') }}</strong>
                                    <span class="small text-secondary">{{ $dateLoads->count() }} carga(s) programada(s)</span>
                                </div>
                                <div class="loads-grid">
                                    @foreach($dateLoads as $load)
                                        <article class="card border load-card">
                                            <div class="card-header bg-body d-flex justify-content-between align-items-start gap-3 flex-wrap p-3"><div class="min-w-0"><div class="small text-secondary">{{ $load->period_label }} · {{ optional($load->effective_open_at)->format('d/m/Y H:i') }}</div><h3 class="h5 mb-1 mt-1">{{ $load->title }}</h3><div class="small text-primary fw-semibold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Pauta: {{ optional($load->calendarImport)->original_filename ?: 'Pauta sin nombre' }}</div></div><a href="{{ route('loads.show', $load) }}" class="btn btn-sm btn-outline-primary flex-shrink-0"><i class="bi bi-folder2-open me-1"></i>Ver carga</a></div>
                                            <div class="card-body p-3"><div class="deliverables-grid">
                                                @forelse($load->deliverables as $deliverable)
                                                    @php $evidence = $deliverable->evidences->sortByDesc('id')->first(); $direction = $deliverable->organizationalUnit; $requirement = $deliverable->templateRequirement; $minFiles = (int) (optional($requirement)->min_files ?: 1); $currentFiles = $evidence ? $evidence->files->where('version', $evidence->current_version)->count() : 0; $status = $evidence ? (is_object($evidence->status) && property_exists($evidence->status, 'value') ? $evidence->status->value : (string) $evidence->status) : ''; $closed = in_array($status, ['ENVIADO', 'VALIDADO', 'CERRADO'], true); @endphp
                                                    <div class="border rounded-3 p-3 deliverable-card d-flex flex-column"><div class="d-flex justify-content-between gap-2 flex-wrap"><span class="badge text-bg-light"><i class="bi bi-diagram-3 me-1"></i>{{ optional($direction)->name ?: 'Sin dirección' }}</span>@if($closed)<span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Cerrada</span>@elseif($evidence)<span class="badge text-bg-warning">{{ $currentFiles }}/{{ $minFiles }} archivo(s)</span>@else<span class="badge text-bg-secondary">Pendiente</span>@endif</div><h4 class="h6 mt-3 mb-1 deliverable-title">{{ optional($requirement)->name ?: 'Entregable programado' }}</h4>@if(optional($requirement)->description)<p class="small text-secondary mb-2">{{ $requirement->description }}</p>@endif
                                                        @if($evidence)<div class="small mb-3"><strong>{{ $evidence->title }}</strong><span class="text-secondary"> · {{ $evidence->files->count() }} archivo(s) total</span></div>@endif
                                                        <div class="mt-auto">@if(!$evidence)<form action="{{ route('evidences.store') }}" method="POST" enctype="multipart/form-data">@csrf<input type="hidden" name="deliverable_id" value="{{ $deliverable->id }}"><div class="mb-2"><input name="title" class="form-control" placeholder="Título de la evidencia programada" required></div><div class="input-group"><input name="file" type="file" class="form-control" accept=".xlsx,.xls,.pdf,.doc,.docx" required><button class="btn btn-primary" type="submit"><i class="bi bi-paperclip me-1"></i>Adjuntar</button></div><div class="form-text">Formatos permitidos para esta evidencia: XLSX, XLS, PDF, DOC y DOCX.</div></form>@elseif(!$closed)<div class="evidence-actions"><a href="{{ route('evidences.show', $evidence) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-folder2-open me-1"></i>Gestionar archivos</a><form action="{{ route('evidences.submit', $evidence) }}" method="POST">@csrf<button class="btn btn-sm btn-success" type="submit" @if($currentFiles < $minFiles) disabled @endif><i class="bi bi-send-check me-1"></i>Cerrar y enviar</button></form></div>@if($currentFiles < $minFiles)<div class="small text-danger mt-2">Debes adjuntar al menos {{ $minFiles }} archivo(s) antes de cerrar y enviar.</div>@endif @else<div class="evidence-actions"><a href="{{ route('evidences.show', $evidence) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Consultar</a><span class="small text-success"><i class="bi bi-check2-circle me-1"></i>Disponible para revisión institucional y por dirección.</span></div>@endif</div></div>
                                                @empty<div class="col-12 text-secondary">No hay evidencias programadas dentro de esta carga para tu alcance.</div>@endforelse
                                            </div></div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty<div class="text-center py-5 text-secondary"><i class="bi bi-inbox fs-1 d-block mb-3"></i><h3 class="h5">No hay evidencias programadas</h3><p class="mb-0">No tienes evidencias asignadas dentro de tu alcance con los filtros seleccionados.</p></div>@endforelse
            <div class="mt-3">{{ $loads->links() }}</div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pauta = document.getElementById('pauta_id');
    const agency = document.getElementById('agency_id');
    const month = document.getElementById('month');
    if (!pauta || !agency || !month) return;

    const monthsByPauta = @json($monthsByPauta ?? []);
    const selectedMonth = @json(request('month'));
    const selectedPauta = @json(request('pauta_id'));

    function rebuildMonths() {
        const pautaId = pauta.value;
        const allowed = pautaId && Object.prototype.hasOwnProperty.call(monthsByPauta, pautaId)
            ? monthsByPauta[pautaId]
            : Object.values(monthsByPauta)
                .flat()
                .filter((value, index, values) => values.indexOf(value) === index)
                .sort()
                .reverse();

        month.innerHTML = '';
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = pautaId ? 'Todos los meses de esta Pauta' : 'Todos los meses disponibles';
        month.appendChild(allOption);

        allowed.forEach(function (value) {
            const option = document.createElement('option');
            option.value = value;
            const parts = value.split('-');
            const date = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);
            option.textContent = date.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
            if (value === selectedMonth) option.selected = true;
            month.appendChild(option);
        });

        if (selectedMonth && !allowed.includes(selectedMonth)) month.value = '';
    }

    pauta.addEventListener('change', rebuildMonths);
    agency.addEventListener('change', function () {
        // La dependencia cambia el universo de Pautas en el servidor al aplicar
        // filtros; no se inventan Pautas ni meses del lado cliente.
        if (!pauta.value) rebuildMonths();
    });
    if (selectedPauta) rebuildMonths();
});
</script>
@endsection
