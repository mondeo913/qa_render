@extends('layouts.app')
@section('title', 'Tablero de cargas')
@section('page-title', 'Tablero de cargas por dependencia')

@section('content')
<div class="siget-board-heading mb-4">
    <div>
        <p class="mb-1 text-secondary">{{ $scopeLabel }}</p>
        <h2 class="h4 mb-1">Seguimiento operativo conectado con pauta y calendario</h2>
        <p class="mb-0 text-secondary small">
            Las tarjetas representan cargas reales de SIGET. No se arrastran manualmente: cambian de columna al ejecutar el flujo autorizado.
        </p>
    </div>
    <a href="{{ route('calendar.index') }}" class="btn btn-outline-primary">
        <i class="bi bi-calendar3 me-1"></i> Ver calendario inteligente
    </a>
</div>

<div class="row g-3 mb-4">
    @php
        $kpis = [
            ['label' => 'Total', 'value' => $summary['total'], 'icon' => 'bi-collection'],
            ['label' => 'Por hacer', 'value' => $summary['todo'], 'icon' => 'bi-list-check'],
            ['label' => 'En progreso', 'value' => $summary['progress'], 'icon' => 'bi-hourglass-split'],
            ['label' => 'En revisión', 'value' => $summary['review'], 'icon' => 'bi-search'],
            ['label' => 'Cerradas', 'value' => $summary['done'], 'icon' => 'bi-check2-circle'],
            ['label' => 'Vencidas', 'value' => $summary['overdue'], 'icon' => 'bi-exclamation-triangle'],
        ];
    @endphp
    @foreach($kpis as $kpi)
        <div class="col-6 col-md-4 col-xl-2">
            <div class="siget-kpi siget-board-kpi h-100">
                <span class="siget-kpi-icon"><i class="bi {{ $kpi['icon'] }}"></i></span>
                <div><small>{{ $kpi['label'] }}</small><strong>{{ $kpi['value'] }}</strong></div>
            </div>
        </div>
    @endforeach
</div>

<div class="card siget-card mb-4">
    <div class="card-header">
        <div>
            <h2>Catálogo de dependencias</h2>
            <p>Seleccione una dependencia para ver solamente sus cargas dentro de la dirección autorizada.</p>
        </div>
        <span class="badge text-bg-light">Cumplimiento promedio: {{ $summary['completion'] }}%</span>
    </div>
    <div class="card-body">
        @if($dependencyCards->isEmpty())
            <div class="text-center py-4 text-secondary">No existen dependencias con cargas dentro de su alcance.</div>
        @else
            <div class="siget-dependency-grid">
                @foreach($dependencyCards as $card)
                    @php
                        $agency = $card['agency'];
                        $selected = (int)($filters['agency_id'] ?? 0) === (int)$agency->id;
                        $url = request()->fullUrlWithQuery(['agency_id' => $selected ? null : $agency->id]);
                    @endphp
                    <a href="{{ $url }}" class="siget-dependency-card {{ $selected ? 'active' : '' }}">
                        <div class="siget-dependency-top">
                            @if($card['logo_url'])
                                <img src="{{ $card['logo_url'] }}" alt="Logo de {{ $agency->name }}" class="siget-dependency-logo">
                            @else
                                <span class="siget-dependency-logo siget-dependency-initials">{{ $card['initials'] }}</span>
                            @endif
                            <div class="min-w-0">
                                <strong>{{ $agency->name }}</strong>
                                <small>{{ $agency->code }}</small>
                            </div>
                            @if($selected)<i class="bi bi-check-circle-fill ms-auto"></i>@endif
                        </div>
                        <div class="siget-dependency-stats">
                            <span><b>{{ $card['summary']['todo'] }}</b> por hacer</span>
                            <span><b>{{ $card['summary']['progress'] }}</b> progreso</span>
                            <span><b>{{ $card['summary']['review'] }}</b> revisión</span>
                            <span><b>{{ $card['summary']['done'] }}</b> cerradas</span>
                        </div>
                        <div class="progress mt-3" role="progressbar" aria-label="Cumplimiento">
                            <div class="progress-bar" style="width: {{ $card['summary']['completion'] }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-secondary">
                            <span>{{ $card['summary']['completion'] }}% avance</span>
                            <span>
                                @if($card['next_due'])
                                    Próximo: {{ $card['next_due']->format('d/m/Y') }}
                                @else
                                    Sin pendientes
                                @endif
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<form method="GET" class="card siget-card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3">
                <label class="form-label">Buscar cargas</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Título, periodo o dependencia">
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label">Dirección</label>
                <select name="unit_id" class="form-select">
                    <option value="">Todas autorizadas</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected((int)($filters['unit_id'] ?? 0) === (int)$unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label">Dependencia</label>
                <select name="agency_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected((int)($filters['agency_id'] ?? 0) === (int)$agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label class="form-label">Periodo</label>
                <select name="period" class="form-select">
                    <option value="">Todos</option>
                    @foreach($periods as $period)
                        <option value="{{ $period }}" @selected(($filters['period'] ?? '') === $period)>{{ $period }}</option>
                    @endforeach
                </select>
            </div>
            @if($canUseMineFilter)
                <div class="col-md-4 col-lg-1">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="mine" value="0">
                        <input class="form-check-input" type="checkbox" name="mine" value="1" id="mineFilter" @checked($filters['mine'] ?? false)>
                        <label class="form-check-label" for="mineFilter">Mías</label>
                    </div>
                </div>
            @endif
            <div class="col-md-4 col-lg-1 d-grid"><button class="btn btn-primary"><i class="bi bi-funnel"></i></button></div>
            <div class="col-md-4 col-lg-1 d-grid"><a href="{{ route('loads.board') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
        </div>
    </div>
</form>

<div class="siget-kanban" aria-label="Tablero Kanban de cargas">
    @foreach($columns as $key => $column)
        <section class="siget-kanban-column siget-kanban-{{ $key }}">
            <header>
                <div>
                    <h3><i class="bi {{ $column['icon'] }} me-1"></i>{{ $column['label'] }}</h3>
                    <p>{{ $column['description'] }}</p>
                </div>
                <span>{{ $column['loads']->count() }}</span>
            </header>

            <div class="siget-kanban-stack">
                @forelse($column['loads'] as $load)
                    @php
                        $priority = strtoupper((string)($load->priority ?? 'NORMAL'));
                        $traffic = $load->traffic_light instanceof \BackedEnum ? $load->traffic_light->value : (string)$load->traffic_light;
                    @endphp
                    <article class="siget-load-card {{ $load->board_overdue ? 'is-overdue' : '' }}">
                        <div class="d-flex justify-content-between gap-2 align-items-start">
                            <div>
                                <span class="siget-load-agency">{{ $load->agency?->name }}</span>
                                <h4>{{ $load->title }}</h4>
                            </div>
                            <span class="siget-traffic siget-traffic-{{ strtolower($traffic ?: 'gray') }}" title="Semáforo {{ $traffic }}"></span>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge siget-status">{{ $load->board_status_label }}</span>
                            <span class="badge siget-priority siget-priority-{{ strtolower($priority) }}">{{ $priority }}</span>
                            @if($load->board_overdue)<span class="badge text-bg-danger">Vencida</span>@endif
                            @if($load->is_blocked)<span class="badge text-bg-secondary">Bloqueada</span>@endif
                        </div>

                        @if($load->board_unit_names->isNotEmpty())
                            <div class="siget-load-units">
                                @foreach($load->board_unit_names as $unitName)
                                    <span><i class="bi bi-diagram-3"></i> {{ $unitName }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="siget-load-meta">
                            <span><i class="bi bi-calendar-event"></i> {{ $load->effective_open_at?->format('d/m/Y') ?? 'Sin fecha' }}</span>
                            <span><i class="bi bi-alarm"></i> {{ $load->effective_close_at?->format('d/m/Y H:i') ?? 'Sin límite' }}</span>
                        </div>

                        <div class="d-flex justify-content-between small mt-3 mb-1">
                            <span>Avance visible</span><strong>{{ number_format($load->board_progress, 0) }}%</strong>
                        </div>
                        <div class="progress" role="progressbar" aria-valuenow="{{ $load->board_progress }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $load->board_progress }}%"></div>
                        </div>

                        <div class="siget-load-footer">
                            <div class="siget-load-counters">
                                <span title="Entregables"><i class="bi bi-check2-square"></i> {{ $load->deliverables->count() }}</span>
                                <span title="Evidencias"><i class="bi bi-paperclip"></i> {{ $load->board_evidence_count }}</span>
                                <span title="Observaciones"><i class="bi bi-chat-left-text"></i> {{ $load->board_observation_count }}</span>
                            </div>
                            <a href="{{ route('loads.show', $load) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                        </div>

                        @if($load->board_responsibles->isNotEmpty())
                            <div class="siget-assignees mt-2">
                                <i class="bi bi-person-check"></i> {{ $load->board_responsibles->join(', ') }}
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="siget-kanban-empty">
                        <i class="bi {{ $column['icon'] }}"></i>
                        <span>No hay cargas en esta etapa.</span>
                    </div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
@endsection
