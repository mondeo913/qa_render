@extends('layouts.app')
@section('title', 'Tablero de cargas')
@section('page-title', 'Tablero de cargas por dependencia')
@section('content')
<div class="board-direction-compact">

<style>
/* Estándar compacto universal del tablero para todos los roles. */
.board-direction-compact{padding-top:0}
.board-direction-compact .board-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin:0 0 16px}
.board-direction-compact .board-kpis>[class*="col-"]{width:auto!important;max-width:none!important;padding-left:0;padding-right:0}
.board-direction-compact .board-kpi{min-height:72px;height:72px;padding:8px 9px;display:grid;grid-template-columns:28px 1fr;grid-template-rows:auto 1fr;column-gap:8px;align-items:center;background:#121b24;border:1px solid rgba(255,255,255,.08);border-radius:12px;color:#fff}
.board-direction-compact .board-kpi .board-kpi-icon{grid-row:1 / span 2;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:.82rem}
.board-direction-compact .board-kpi small{font-size:.62rem;line-height:1.05;color:#91a5b7;white-space:normal;overflow-wrap:anywhere}
.board-direction-compact .board-kpi strong{font-size:1.05rem;line-height:1;color:#fff}
.board-direction-compact .board-heading{margin-bottom:12px!important}
.board-direction-compact .board-heading .scope-label{font-size:.65rem;color:#91a5b7}
.board-direction-compact .board-heading h2{font-size:1rem;color:#fff;margin-bottom:2px!important}
.board-direction-compact .board-heading p{font-size:.68rem;color:#91a5b7}
.board-direction-compact .board-heading .btn{height:30px;padding:.25rem .55rem;font-size:.68rem}
/* Kanban compacto universal: las cuatro etapas deben caber en una sola vista de escritorio. */
.board-direction-compact .siget-kanban{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    grid-auto-columns:minmax(0,1fr);
    gap:8px!important;
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    overflow:visible!important;
    align-items:start;
}
.board-direction-compact .siget-kanban > .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    margin:0!important;
}
.board-direction-compact .siget-kanban-column{
    min-width:0!important;
    width:100%!important;
    border-radius:10px;
}
.board-direction-compact .siget-kanban-column > header{
    min-height:58px;
    padding:8px 9px!important;
}
.board-direction-compact .siget-kanban-column > header{
    min-width:0;
}
.board-direction-compact .siget-kanban-column > header > div:first-child{
    min-width:0;
    flex:1 1 auto;
}
.board-direction-compact .siget-kanban-column > header h3{
    font-size:.76rem!important;
    line-height:1.08;
    margin:0!important;
    white-space:normal;
    overflow-wrap:anywhere;
    word-break:normal;
}
.board-direction-compact .siget-kanban-column > header p{
    font-size:.58rem!important;
    line-height:1.15;
    margin:3px 0 0!important;
    overflow-wrap:anywhere;
}
.board-direction-compact .siget-kanban-column > header > span{
    flex:0 0 auto;
    min-width:22px;
    height:22px;
    padding:0 5px;
    display:grid;
    place-items:center;
    font-size:.64rem;
}
.board-direction-compact .siget-kanban-stack{
    padding:6px!important;
    gap:6px!important;
}
.board-direction-compact .siget-load-card{
    padding:7px!important;
    border-radius:8px;
    min-width:0;
}
.board-direction-compact .siget-load-card h4{
    font-size:.68rem!important;
    line-height:1.15;
    margin:2px 0 0!important;
    overflow-wrap:anywhere;
}
.board-direction-compact .siget-load-agency{
    font-size:.56rem!important;
    line-height:1.05;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.54rem!important;
    padding:.2rem .32rem!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.55rem!important;
    line-height:1.18;
}
.board-direction-compact .siget-load-meta{
    gap:5px!important;
    margin-top:5px!important;
}
.board-direction-compact .siget-load-card .small{
    font-size:.56rem!important;
}
.board-direction-compact .siget-load-card .progress{
    height:4px!important;
}
.board-direction-compact .siget-load-footer{
    margin-top:6px!important;
    gap:5px!important;
}
.board-direction-compact .siget-load-counters{
    font-size:.55rem!important;
    gap:6px!important;
}
.board-direction-compact .siget-load-footer .btn{
    font-size:.56rem!important;
    padding:.2rem .38rem!important;
}
.board-direction-compact .siget-kanban-empty{
    padding:12px 7px!important;
    font-size:.6rem!important;
    text-align:center;
}
@media(max-width:980px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
        gap:6px!important;
    }
    .board-direction-compact .siget-kanban-column > header{
        padding:7px 6px!important;
    }
    .board-direction-compact .siget-kanban-column > header h3{
        font-size:.67rem!important;
    }
}
@media(max-width:760px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:7px!important;
    }
}
@media(max-width:520px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:1fr!important;
    }
}

/* Ajuste visual definitivo: encabezados y filtros legibles; compactar únicamente estados y tarjetas inferiores. */
.board-direction-compact .board-heading{
    min-height:50px!important;
    margin:0 0 10px!important;
}
.board-direction-compact .board-heading .scope-label{
    font-size:.64rem!important;
}
.board-direction-compact .board-heading h2{
    font-size:1rem!important;
    line-height:1.08!important;
}
.board-direction-compact .board-heading p{
    font-size:.68rem!important;
    line-height:1.15!important;
}
.board-direction-compact .board-heading .btn{
    height:32px!important;
    min-height:32px!important;
    padding:.25rem .6rem!important;
    font-size:.68rem!important;
}

/* KPI superiores: tamaño medio, no miniatura. */
.board-direction-compact .board-kpis{
    gap:7px!important;
    margin-bottom:9px!important;
}
.board-direction-compact .board-kpi{
    height:68px!important;
    min-height:68px!important;
    padding:8px 9px!important;
    grid-template-columns:28px 1fr!important;
    column-gap:8px!important;
    border-radius:10px!important;
}
.board-direction-compact .board-kpi .board-kpi-icon{
    width:28px!important;
    height:28px!important;
    border-radius:7px!important;
    font-size:.78rem!important;
}
.board-direction-compact .board-kpi small{
    font-size:.59rem!important;
    line-height:1.02!important;
}
.board-direction-compact .board-kpi strong{
    font-size:1rem!important;
}

/* Filtros superiores: recuperan presencia y altura; no son el objetivo de la compactación. */
.board-direction-compact #loadBoardFilters{
    margin-bottom:8px!important;
}
.board-direction-compact #loadBoardFilters .card-body{
    padding:8px 10px!important;
}
.board-direction-compact #loadBoardFilters .form-label{
    font-size:.58rem!important;
    margin-bottom:3px!important;
}
.board-direction-compact #loadBoardFilters .form-control,
.board-direction-compact #loadBoardFilters .form-select,
.board-direction-compact #loadBoardFilters .input-group-text{
    height:34px!important;
    min-height:34px!important;
    font-size:.63rem!important;
    padding:.22rem .45rem!important;
}
.board-direction-compact #loadBoardFilters .btn{
    height:34px!important;
    min-height:34px!important;
    font-size:.62rem!important;
}
.board-direction-compact .board-period-segmented{
    margin-top:5px!important;
    padding-top:5px!important;
}
.board-direction-compact .board-period-segmented .btn{
    height:25px!important;
    min-height:25px!important;
    font-size:.55rem!important;
}

/* Catálogo de dependencias: compacto pero todavía perfectamente legible. */
.board-direction-compact>.siget-card{
    margin-bottom:8px!important;
}
.board-direction-compact>.siget-card .card-header{
    min-height:34px!important;
    padding:7px 10px!important;
}
.board-direction-compact>.siget-card .card-header h2{
    font-size:.78rem!important;
}
.board-direction-compact>.siget-card .card-body{
    padding:6px 8px!important;
}
.board-direction-compact .siget-dependency-grid{
    gap:6px!important;
}
.board-direction-compact .siget-dependency-card{
    min-height:54px!important;
    height:54px!important;
    padding:6px!important;
    border-radius:7px!important;
}
.board-direction-compact .siget-dependency-logo{
    width:24px!important;
    height:24px!important;
}
.board-direction-compact .siget-dependency-top strong{
    font-size:.57rem!important;
}
.board-direction-compact .siget-dependency-top small{
    font-size:.46rem!important;
}
.board-direction-compact .siget-dependency-stats{
    margin-top:4px!important;
    font-size:.44rem!important;
}
.board-direction-compact .siget-dependency-card .progress{
    height:3px!important;
    margin-top:4px!important;
}

/* ===== SOLO ESTA ZONA SE COMPACTA FUERTE ===== */
.board-direction-compact .siget-kanban{
    gap:7px!important;
    margin-top:1px!important;
}

/* Encabezado de cada estado: visible, compacto y uniforme. */
.board-direction-compact .siget-kanban-column>header{
    min-height:52px!important;
    height:52px!important;
    padding:7px 8px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.70rem!important;
    line-height:1.04!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.50rem!important;
    line-height:1.05!important;
    margin-top:2px!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:22px!important;
    min-width:22px!important;
    height:22px!important;
    font-size:.57rem!important;
    border-radius:6px!important;
}

/* Las cajas de carga son el elemento que más espacio estaba consumiendo. */
.board-direction-compact .siget-kanban-stack{
    padding:5px!important;
    gap:5px!important;
    min-height:60px!important;
}
.board-direction-compact .siget-load-card{
    padding:7px!important;
    border-radius:7px!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.48rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card h4{
    font-size:.60rem!important;
    line-height:1.04!important;
    margin:2px 0 4px!important;
    max-height:2.08em!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.46rem!important;
    padding:2px 4px!important;
    border-radius:4px!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.47rem!important;
    line-height:1.06!important;
}
.board-direction-compact .siget-load-meta{
    gap:4px!important;
    margin-top:4px!important;
}
.board-direction-compact .siget-load-card .small{
    font-size:.46rem!important;
}
.board-direction-compact .siget-load-card .progress{
    height:3px!important;
}
.board-direction-compact .siget-load-footer{
    margin-top:4px!important;
    padding-top:4px!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-counters{
    font-size:.46rem!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-footer .btn{
    height:23px!important;
    min-height:23px!important;
    font-size:.47rem!important;
    padding:0 .35rem!important;
    border-radius:4px!important;
}
.board-direction-compact .siget-assignees{
    margin-top:3px!important;
    padding-top:3px!important;
}

/* Evita que una tarjeta excepcionalmente larga agrande una columna completa. */
.board-direction-compact .siget-kanban-stack .siget-load-card{
    max-height:154px!important;
    overflow:hidden!important;
}

/* En la pantalla principal, los estados permanecen visibles y el contenido interno desplaza. */
@media(min-width:1101px){
    .board-direction-compact .siget-kanban{
        align-items:start!important;
    }
    .board-direction-compact .siget-kanban-column{
        min-height:330px!important;
        max-height:430px!important;
    }
    .board-direction-compact .siget-kanban-stack{
        max-height:375px!important;
        overflow-y:auto!important;
        scrollbar-width:thin;
    }
}
@media(max-width:1100px){
    .board-direction-compact .siget-kanban-column{
        min-height:260px!important;
    }
    .board-direction-compact .siget-kanban-stack{
        max-height:none!important;
        overflow:visible!important;
    }
}
@media(max-width:700px){
    .board-direction-compact .board-heading h2{font-size:.94rem!important}
    .board-direction-compact .board-kpi{height:62px!important;min-height:62px!important}
    .board-direction-compact #loadBoardFilters .form-control,
    .board-direction-compact #loadBoardFilters .form-select,
    .board-direction-compact #loadBoardFilters .input-group-text,
    .board-direction-compact #loadBoardFilters .btn{
        height:31px!important;min-height:31px!important;
    }
}

@media(max-width:1200px){.board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.board-direction-compact .board-kpi{height:68px;min-height:68px}}

/* Restauración de estándar visual de la versión 4b14127 + cierre institucional visible. */
.board-direction-compact .siget-kanban{
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:10px!important;
    overflow:visible!important;
    margin:0!important;
}
.board-direction-compact .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    border-radius:12px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header{
    min-height:74px!important;
    height:auto!important;
    padding:10px 12px!important;
    box-sizing:border-box!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.86rem!important;
    line-height:1.18!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.65rem!important;
    line-height:1.25!important;
    margin:4px 0 0!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:28px!important;
    min-width:28px!important;
    height:28px!important;
    border-radius:8px!important;
    font-size:.68rem!important;
}
.board-direction-compact .siget-kanban-stack{
    padding:9px!important;
    gap:9px!important;
    min-height:120px!important;
}
.board-direction-compact .siget-load-card{
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    padding:11px!important;
    border-radius:10px!important;
    box-sizing:border-box!important;
}
.board-direction-compact .siget-load-agency{font-size:.63rem!important}
.board-direction-compact .siget-load-card h4{
    font-size:.79rem!important;
    line-height:1.2!important;
    margin:2px 0 7px!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.59rem!important;
    padding:.24rem .4rem!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.61rem!important;
    line-height:1.25!important;
}
.board-direction-compact .siget-load-card .progress{height:6px!important}
.board-direction-compact .siget-load-footer{margin-top:8px!important;padding-top:7px!important}
.board-direction-compact .siget-load-counters{font-size:.59rem!important;gap:7px!important}
.board-direction-compact .siget-load-footer .btn{
    height:29px!important;
    min-height:29px!important;
    font-size:.61rem!important;
    padding:.22rem .48rem!important;
}

/* El cierre institucional queda inequívocamente verde en el tablero. */
.board-direction-compact .siget-kanban-done{
    border:1px solid #22a06b!important;
}
.board-direction-compact .siget-kanban-done>header{
    border-top:4px solid #22a06b!important;
    background:color-mix(in srgb,#22a06b 14%,var(--surface2))!important;
}
.board-direction-compact .siget-kanban-done .siget-load-card{
    border-color:color-mix(in srgb,#22a06b 42%,var(--border))!important;
}
.board-direction-compact .siget-kanban-done .progress-bar{
    background:#22a06b!important;
}

@media(max-width:1100px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }
}
@media(max-width:650px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:1fr!important;
    }
}

/* Compactación final del tablero: libera espacio vertical y mantiene las 4 etapas visibles en escritorio. */
.board-direction-compact .board-heading{
    margin-bottom:8px!important;
    min-height:40px;
}
.board-direction-compact .board-heading h2{
    font-size:.92rem!important;
    line-height:1.05!important;
}
.board-direction-compact .board-heading p{
    font-size:.62rem!important;
    line-height:1.15!important;
}
.board-direction-compact .board-heading .scope-label{
    font-size:.58rem!important;
    line-height:1!important;
}
.board-direction-compact .board-heading .btn{
    height:27px!important;
    font-size:.61rem!important;
    padding:.18rem .45rem!important;
}

/* KPI: mismo ancho, menor altura y separación. */
.board-direction-compact .board-kpis{
    grid-template-columns:repeat(6,minmax(0,1fr))!important;
    gap:6px!important;
    margin-bottom:8px!important;
}
.board-direction-compact .board-kpi{
    min-height:58px!important;
    height:58px!important;
    padding:6px 7px!important;
    grid-template-columns:24px 1fr!important;
    column-gap:7px!important;
    border-radius:9px!important;
}
.board-direction-compact .board-kpi .board-kpi-icon{
    width:24px!important;height:24px!important;border-radius:7px!important;font-size:.7rem!important;
}
.board-direction-compact .board-kpi small{
    font-size:.56rem!important;
    line-height:1!important;
}
.board-direction-compact .board-kpi strong{
    font-size:.92rem!important;
    line-height:1!important;
}

/* Catálogo compacto: una fila de tarjetas en escritorio siempre que exista ancho. */
.board-direction-compact>.siget-card{
    margin-bottom:8px!important;
}
.board-direction-compact .siget-card .card-header{
    padding:7px 10px!important;
}
.board-direction-compact .siget-card .card-header h2{
    font-size:.78rem!important;
    line-height:1.05!important;
}
.board-direction-compact .siget-card .card-header p{
    font-size:.58rem!important;
    line-height:1.15!important;
    margin:2px 0 0!important;
}
.board-direction-compact .siget-card .card-header .badge{
    font-size:.56rem!important;
    padding:.25rem .38rem!important;
}
.board-direction-compact .siget-card .card-body{
    padding:7px 9px!important;
}
.board-direction-compact .siget-dependency-grid{
    grid-template-columns:repeat(auto-fit,minmax(150px,1fr))!important;
    gap:6px!important;
}
.board-direction-compact .siget-dependency-card{
    padding:7px!important;
    border-radius:8px!important;
}
.board-direction-compact .siget-dependency-top{
    gap:7px!important;
}
.board-direction-compact .siget-dependency-logo{
    width:28px!important;height:28px!important;border-radius:7px!important;
}
.board-direction-compact .siget-dependency-top strong{
    display:block;
    font-size:.65rem!important;
    line-height:1.05!important;
}
.board-direction-compact .siget-dependency-top small{
    display:block;
    font-size:.52rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-dependency-stats{
    grid-template-columns:1fr 1fr!important;
    gap:3px 6px!important;
    margin-top:6px!important;
    font-size:.53rem!important;
    line-height:1.05!important;
}
.board-direction-compact .siget-dependency-card .progress{
    height:3px!important;
    margin-top:6px!important;
}
.board-direction-compact .siget-dependency-card>.d-flex.justify-content-between{
    margin-top:4px!important;
    font-size:.52rem!important;
    line-height:1!important;
}

/* Filtros: una sola banda compacta; el detalle de fechas sigue disponible sin ocupar una pantalla. */
.board-direction-compact #loadBoardFilters{
    margin-bottom:8px!important;
}
.board-direction-compact #loadBoardFilters .card-body{
    padding:7px 9px!important;
}
.board-direction-compact #loadBoardFilters .row.g-3{
    --bs-gutter-x:.4rem;
    --bs-gutter-y:.35rem;
}
.board-direction-compact #loadBoardFilters .form-label{
    font-size:.58rem!important;
    line-height:1!important;
    margin-bottom:2px!important;
}
.board-direction-compact #loadBoardFilters .form-control,
.board-direction-compact #loadBoardFilters .form-select,
.board-direction-compact #loadBoardFilters .input-group-text{
    height:29px!important;
    min-height:29px!important;
    font-size:.61rem!important;
    padding:.18rem .38rem!important;
}
.board-direction-compact #loadBoardFilters .form-text{
    display:none!important;
}
.board-direction-compact #loadBoardFilters .bg-light-subtle{
    min-height:29px;
    padding:5px 7px!important;
    font-size:.58rem!important;
}
.board-direction-compact #loadBoardFilters .form-switch{
    margin-bottom:0!important;
    font-size:.59rem!important;
}
.board-direction-compact #loadBoardFilters .btn{
    height:29px!important;
    min-height:29px!important;
    padding:.18rem .4rem!important;
    font-size:.6rem!important;
}
.board-direction-compact .board-period-segmented{
    margin-top:5px!important;
    padding-top:5px!important;
    border-top:1px solid var(--border);
}
.board-direction-compact .board-period-segmented .small{
    font-size:.57rem!important;
}
.board-direction-compact .board-period-segmented .btn{
    height:25px!important;
    min-height:25px!important;
    padding:.15rem .35rem!important;
    font-size:.56rem!important;
}
.board-direction-compact #boardCustomRange{
    margin-top:4px!important;
}

/* Kanban: encabezados y tarjetas reducidos para que Por hacer se vea inmediatamente. */
.board-direction-compact .siget-kanban{
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:7px!important;
    margin-top:0!important;
}
.board-direction-compact .siget-kanban-column{
    border-radius:9px!important;
}
.board-direction-compact .siget-kanban-column>header{
    min-height:52px!important;
    padding:6px 7px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.69rem!important;
    line-height:1.03!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.52rem!important;
    line-height:1.05!important;
    margin:2px 0 0!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:23px!important;
    min-width:23px!important;
    height:23px!important;
    border-radius:6px!important;
    font-size:.58rem!important;
}
.board-direction-compact .siget-kanban-stack{
    padding:5px!important;
    gap:5px!important;
    min-height:72px!important;
}
.board-direction-compact .siget-load-card{
    padding:7px!important;
    border-radius:7px!important;
}
.board-direction-compact .siget-load-card h4{
    font-size:.63rem!important;
    line-height:1.08!important;
    margin:2px 0 4px!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.5rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.5rem!important;
    padding:.16rem .26rem!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.5rem!important;
    line-height:1.08!important;
}
.board-direction-compact .siget-load-meta{
    gap:3px!important;
    margin-top:4px!important;
}
.board-direction-compact .siget-load-card .progress{
    height:3px!important;
}
.board-direction-compact .siget-load-footer{
    margin-top:4px!important;
    padding-top:4px!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-counters{
    font-size:.49rem!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-footer .btn{
    height:24px!important;
    min-height:24px!important;
    padding:.15rem .34rem!important;
    font-size:.5rem!important;
}
.board-direction-compact .siget-kanban-empty{
    min-height:70px!important;
    padding:8px 5px!important;
    font-size:.55rem!important;
}
@media(max-width:980px){
    .board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))!important}
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(4,minmax(0,1fr))!important}
}
@media(max-width:760px){
    .board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:520px){
    .board-direction-compact .siget-dependency-grid{grid-template-columns:1fr!important}
    .board-direction-compact .siget-kanban{grid-template-columns:1fr!important}
}

/* ÚLTIMO NIVEL DE COMPACTACIÓN — prevalece sobre reglas heredadas anteriores. */
.board-direction-compact{
    padding-top:0!important;
    font-size:.8rem!important;
}
.board-direction-compact .board-heading{
    min-height:34px!important;
    margin:0 0 6px!important;
    gap:8px!important;
}
.board-direction-compact .board-heading .scope-label{
    margin:0 0 1px!important;
    font-size:.52rem!important;
    line-height:1!important;
}
.board-direction-compact .board-heading h2{
    margin:0!important;
    font-size:.86rem!important;
    line-height:1!important;
}
.board-direction-compact .board-heading p{
    margin:2px 0 0!important;
    font-size:.53rem!important;
    line-height:1.05!important;
}
.board-direction-compact .board-heading .btn{
    height:25px!important;
    min-height:25px!important;
    padding:0 .45rem!important;
    font-size:.56rem!important;
    line-height:1!important;
}

/* KPI: máximo ahorro vertical sin perder lectura. */
.board-direction-compact .board-kpis{
    grid-template-columns:repeat(6,minmax(0,1fr))!important;
    gap:5px!important;
    margin:0 0 6px!important;
}
.board-direction-compact .board-kpi{
    height:48px!important;
    min-height:48px!important;
    padding:5px 6px!important;
    grid-template-columns:21px 1fr!important;
    column-gap:6px!important;
    border-radius:7px!important;
}
.board-direction-compact .board-kpi .board-kpi-icon{
    width:21px!important;
    height:21px!important;
    border-radius:5px!important;
    font-size:.61rem!important;
}
.board-direction-compact .board-kpi small{
    font-size:.49rem!important;
    line-height:.95!important;
}
.board-direction-compact .board-kpi strong{
    font-size:.82rem!important;
    line-height:.9!important;
}

/* Catálogo: pasa de tarjetas altas a una tira compacta de selección. */
.board-direction-compact>.siget-card{
    margin:0 0 6px!important;
    border-radius:8px!important;
}
.board-direction-compact>.siget-card .card-header{
    min-height:28px!important;
    padding:5px 8px!important;
    align-items:center!important;
}
.board-direction-compact>.siget-card .card-header h2{
    margin:0!important;
    font-size:.64rem!important;
    line-height:1!important;
}
.board-direction-compact>.siget-card .card-header p{
    display:none!important;
}
.board-direction-compact>.siget-card .card-header .badge{
    font-size:.49rem!important;
    padding:2px 4px!important;
}
.board-direction-compact>.siget-card .card-body{
    padding:5px!important;
}
.board-direction-compact .siget-dependency-grid{
    display:grid!important;
    grid-template-columns:repeat(7,minmax(90px,1fr))!important;
    gap:4px!important;
}
.board-direction-compact .siget-dependency-card{
    min-height:44px!important;
    height:44px!important;
    padding:4px 5px!important;
    border-radius:6px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-dependency-top{
    gap:4px!important;
    min-height:20px!important;
}
.board-direction-compact .siget-dependency-logo{
    width:19px!important;
    height:19px!important;
    min-width:19px!important;
    border-radius:4px!important;
}
.board-direction-compact .siget-dependency-top strong{
    font-size:.49rem!important;
    line-height:.92!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-dependency-top small{
    font-size:.4rem!important;
    line-height:.9!important;
}
.board-direction-compact .siget-dependency-stats{
    display:flex!important;
    gap:5px!important;
    margin-top:3px!important;
    font-size:.38rem!important;
    line-height:.9!important;
    white-space:nowrap!important;
}
.board-direction-compact .siget-dependency-stats span:nth-child(n+3){display:none!important}
.board-direction-compact .siget-dependency-card .progress{
    height:2px!important;
    margin-top:3px!important;
}
.board-direction-compact .siget-dependency-card>.d-flex.justify-content-between{
    display:none!important;
}

/* Filtros: altura mínima, sin explicaciones ni espacios muertos. */
.board-direction-compact #loadBoardFilters{
    margin:0 0 6px!important;
    border-radius:8px!important;
}
.board-direction-compact #loadBoardFilters .card-body{
    padding:5px 6px!important;
}
.board-direction-compact #loadBoardFilters .row.g-3{
    --bs-gutter-x:.3rem!important;
    --bs-gutter-y:.25rem!important;
}
.board-direction-compact #loadBoardFilters .form-label{
    font-size:.46rem!important;
    line-height:1!important;
    margin:0 0 1px!important;
}
.board-direction-compact #loadBoardFilters .form-control,
.board-direction-compact #loadBoardFilters .form-select,
.board-direction-compact #loadBoardFilters .input-group-text{
    height:25px!important;
    min-height:25px!important;
    font-size:.52rem!important;
    padding:1px 4px!important;
    border-radius:5px!important;
}
.board-direction-compact #loadBoardFilters .form-text{
    display:none!important;
}
.board-direction-compact #loadBoardFilters .bg-light-subtle{
    min-height:25px!important;
    height:25px!important;
    padding:4px 5px!important;
    font-size:.47rem!important;
    overflow:hidden!important;
    white-space:nowrap!important;
}
.board-direction-compact #loadBoardFilters .form-switch{
    margin:0!important;
    min-height:25px!important;
    font-size:.5rem!important;
}
.board-direction-compact #loadBoardFilters .btn{
    height:25px!important;
    min-height:25px!important;
    padding:1px 5px!important;
    font-size:.5rem!important;
    border-radius:5px!important;
}
.board-direction-compact .board-period-segmented{
    margin:3px 0 0!important;
    padding-top:3px!important;
    border-top:1px solid var(--border)!important;
}
.board-direction-compact .board-period-segmented .small,
.board-direction-compact .board-period-segmented strong{
    font-size:.46rem!important;
    line-height:1!important;
}
.board-direction-compact .board-period-segmented .btn{
    height:21px!important;
    min-height:21px!important;
    padding:0 4px!important;
    font-size:.43rem!important;
    border-radius:4px!important;
}
.board-direction-compact #boardCustomRange{
    margin-top:3px!important;
}

/* KANBAN: prioridad absoluta a que las cuatro etapas aparezcan arriba. */
.board-direction-compact .siget-kanban{
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:6px!important;
    margin:0!important;
    align-items:start!important;
}
.board-direction-compact .siget-kanban-column{
    border-radius:7px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header{
    min-height:40px!important;
    height:40px!important;
    padding:4px 6px!important;
    gap:5px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.59rem!important;
    line-height:.98!important;
    margin:0!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.41rem!important;
    line-height:.9!important;
    margin:2px 0 0!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:19px!important;
    min-width:19px!important;
    height:19px!important;
    border-radius:5px!important;
    font-size:.48rem!important;
}
.board-direction-compact .siget-kanban-stack{
    padding:4px!important;
    gap:4px!important;
    min-height:42px!important;
}
.board-direction-compact .siget-load-card{
    padding:5px!important;
    border-radius:6px!important;
}
.board-direction-compact .siget-load-card h4{
    font-size:.53rem!important;
    line-height:.98!important;
    margin:1px 0 3px!important;
    max-height:2.05em!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-load-agency{
    display:block!important;
    font-size:.4rem!important;
    line-height:.9!important;
    max-width:calc(100% - 12px)!important;
    overflow:hidden!important;
    white-space:nowrap!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.4rem!important;
    line-height:1!important;
    padding:2px 3px!important;
    border-radius:4px!important;
}
.board-direction-compact .siget-load-units{
    display:block!important;
    max-height:1.1em!important;
    overflow:hidden!important;
    font-size:.42rem!important;
    line-height:1.05!important;
}
.board-direction-compact .siget-load-meta{
    display:flex!important;
    flex-wrap:nowrap!important;
    gap:4px!important;
    margin-top:3px!important;
    font-size:.4rem!important;
    line-height:.95!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-load-meta span{
    min-width:0!important;
    overflow:hidden!important;
    white-space:nowrap!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card .d-flex.justify-content-between.small{
    margin-top:3px!important;
    margin-bottom:1px!important;
    font-size:.41rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card .progress{
    height:2px!important;
}
.board-direction-compact .siget-load-footer{
    margin-top:3px!important;
    padding-top:3px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-counters{
    font-size:.4rem!important;
    gap:3px!important;
    white-space:nowrap!important;
}
.board-direction-compact .siget-load-footer .btn{
    height:20px!important;
    min-height:20px!important;
    padding:0 4px!important;
    font-size:.42rem!important;
    line-height:1!important;
    border-radius:4px!important;
}
.board-direction-compact .siget-assignees{
    margin-top:2px!important;
    padding-top:2px!important;
    border-top:1px solid rgba(128,145,160,.18)!important;
    font-size:.4rem!important;
    line-height:.95!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-kanban-empty{
    min-height:42px!important;
    padding:7px 4px!important;
    font-size:.43rem!important;
}

/* Mantener la columna de cierre verde, pero igualmente compacta. */
.board-direction-compact .siget-kanban-done{
    border-color:#22a06b!important;
}
.board-direction-compact .siget-kanban-done>header{
    border-top-width:3px!important;
}

/* La cabecera Kanban queda dentro de la primera pantalla al hacer scroll. */
.board-direction-compact .siget-kanban-column>header{
    position:sticky!important;
    top:0!important;
    z-index:2!important;
}

@media(max-width:1200px){
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(4,minmax(90px,1fr))!important}
    .board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))!important}
}
@media(max-width:760px){
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(2,minmax(90px,1fr))!important}
    .board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:520px){
    .board-direction-compact .siget-dependency-grid{grid-template-columns:1fr!important}
    .board-direction-compact .siget-kanban{grid-template-columns:1fr!important}
}

</style>

<div class="siget-board-heading board-heading d-flex justify-content-between align-items-start gap-3">
    <div class="min-w-0">
        <p class="scope-label mb-1">{{ $scopeLabel }}</p>
        <h2 class="mb-1">Tablero de Dirección</h2>
        <p class="mb-0">Seguimiento compacto de cargas, evidencias, fechas y avance dentro del alcance autorizado.</p>
    </div>
    <a href="{{ route('calendar.index') }}" class="btn btn-outline-primary flex-shrink-0"><i class="bi bi-calendar3 me-1"></i> Calendario</a>
</div>

<div class="row g-2 mb-3 board-kpis">

@php $kpis=[
    ['label'=>'Total','value'=>$summary['total'],'icon'=>'bi-collection','type'=>'info'],
    ['label'=>'Por hacer','value'=>$summary['todo'],'icon'=>'bi-list-check','type'=>'primary'],
    ['label'=>'En progreso','value'=>$summary['progress'],'icon'=>'bi-hourglass-split','type'=>'warning'],
    ['label'=>'En revisión','value'=>$summary['review'],'icon'=>'bi-clipboard-check','type'=>'warning'],
    ['label'=>'Cerradas','value'=>$summary['done'],'icon'=>'bi-check2-circle','type'=>'success'],
    ['label'=>'Vencidas','value'=>$summary['overdue'],'icon'=>'bi-calendar-x','type'=>'danger']
]; @endphp
@foreach($kpis as $kpi)
<div><div class="board-kpi">
    <span class="board-kpi-icon text-bg-{{ $kpi['type'] }}"><i class="bi {{ $kpi['icon'] }}"></i></span>
    <small>{{ $kpi['label'] }}</small>
    <strong>{{ $kpi['value'] }}</strong>
</div></div>
@endforeach
</div>



<div class="card siget-card mb-4">
<div class="card-header"><div><h2>Catálogo de dependencias</h2><p>Solo aparecen dependencias con cargas pertenecientes al alcance autorizado. La Pauta identifica el archivo Excel importado por Enlace Institucional.</p></div><span class="badge text-bg-light">Cumplimiento promedio: {{ $summary['completion'] }}%</span></div>
<div class="card-body">
@if($dependencyCards->isEmpty())<div class="text-center py-4 text-secondary">No existen dependencias con cargas dentro de su alcance.</div>
@else<div class="siget-dependency-grid">@foreach($dependencyCards as $card)@php $agency=$card['agency']; $selected=(int)($filters['agency_id']??0)===(int)$agency->id; $url=request()->fullUrlWithQuery(['agency_id'=>$selected?null:$agency->id]); @endphp
<a href="{{ $url }}" class="siget-dependency-card {{ $selected?'active':'' }}"><div class="siget-dependency-top">@if($card['logo_url'])<img src="{{ $card['logo_url'] }}" alt="Logo de {{ $agency->name }}" class="siget-dependency-logo">@else<span class="siget-dependency-logo siget-dependency-initials">{{ $card['initials'] }}</span>@endif<div class="min-w-0"><strong>{{ $agency->name }}</strong><small>{{ $agency->code }}</small></div>@if($selected)<i class="bi bi-check-circle-fill ms-auto"></i>@endif</div><div class="siget-dependency-stats"><span><b>{{ $card['summary']['todo'] }}</b> por hacer</span><span><b>{{ $card['summary']['progress'] }}</b> progreso</span><span><b>{{ $card['summary']['review'] }}</b> revisión</span><span><b>{{ $card['summary']['done'] }}</b> cerradas</span></div><div class="progress mt-3"><div class="progress-bar" style="width: {{ $card['summary']['completion'] }}%"></div></div><div class="d-flex justify-content-between mt-2 small text-secondary"><span>{{ $card['summary']['completion'] }}% avance</span><span>@if($card['next_due']) Próximo: {{ $card['next_due']->format('d/m/Y') }} @else Sin pendientes @endif</span></div></a>
@endforeach</div>@endif
</div></div>

<form method="GET" class="card siget-card mb-4" id="loadBoardFilters">
<div class="card-body">
<div class="row g-3 align-items-end">
<div class="col-lg-3"><label class="form-label">Buscar cargas</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="{{ $filters['q']??'' }}" placeholder="Título, periodo o dependencia"></div></div>
<div class="col-md-4 col-lg-2"><label class="form-label">Pauta (Excel)</label><select name="pauta_id" class="form-select" id="boardPauta"><option value="">Todas las pautas visibles</option>@foreach(($pautas ?? []) as $pauta)<option value="{{ $pauta['id'] }}" data-agency-id="{{ $pauta['agency_id'] }}" @selected((int)($filters['pauta_id']??0)===(int)$pauta['id'])>{{ $pauta['agency'] }} · {{ $pauta['name'] }} · {{ $pauta['load_count'] }} programación(es)</option>@endforeach</select></div><div class="col-md-4 col-lg-2"><label class="form-label">Dirección</label><select name="unit_id" class="form-select"><option value="">{{ in_array(auth()->user()?->role?->code, ['DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'], true) ? 'Toda mi Dirección' : 'Todas las autorizadas' }}</option>@foreach($units as $unit)@php $unitIds=$unit->filter_unit_ids??[(int)$unit->id]; $unitValue=implode(',',array_map('intval',$unitIds)); @endphp<option value="{{ $unitValue }}" @selected((string)($filters['unit_id']??'')===$unitValue)>{{ $unit->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-2"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select" id="boardAgency"><option value="">Todas las dependencias visibles</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((int)($filters['agency_id']??0)===(int)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-3">
    <label class="form-label">Periodo contratado según pauta</label>
    <div class="border rounded px-2 py-1 bg-light-subtle small">
        <i class="bi bi-calendar3 me-1"></i>
        <strong>{{ ($periodBounds['min']??null)&&($periodBounds['max']??null) ? $periodBounds['min'].' → '.$periodBounds['max'] : 'Sin periodo disponible' }}</strong>
    </div>
    <div class="form-text">Se muestra únicamente el periodo disponible para la pauta, dependencia y Dirección visibles.</div>
</div>
@if($canUseMineFilter)<div class="col-md-4 col-lg-1"><div class="form-check form-switch mb-2"><input type="hidden" name="mine" value="0"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mineFilter" @checked($filters['mine']??false)><label class="form-check-label" for="mineFilter">Mías</label></div></div>@endif
<div class="col-md-4 col-lg-1 d-grid"><button class="btn btn-primary" title="Aplicar filtros"><i class="bi bi-funnel"></i></button></div>
<div class="col-md-4 col-lg-1 d-grid"><a href="{{ route('loads.board') }}" class="btn btn-outline-secondary" title="Restablecer"><i class="bi bi-x-lg"></i></a></div>
</div></div>
<div class="board-period-segmented mt-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <strong class="small">Rango segmentado:</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="today">Hoy</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="week">Semana actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="month">Mes actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="quarter">Trimestre actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="custom">Personalizado</button>
        <span class="small text-muted ms-auto" id="boardPeriodSummary">{{ ($filters['from']??'')&&($filters['to']??'') ? $filters['from'].' → '.$filters['to'] : (($periodBounds['min']??null)&&($periodBounds['max']??null) ? $periodBounds['min'].' → '.$periodBounds['max'] : 'Periodo completo de la pauta seleccionada') }}</span>
    </div>
    <div class="row g-2 mt-2 d-none" id="boardCustomRange">
        <div class="col-md-3"><label class="form-label">Desde</label><input type="month" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-sm board-custom-from"></div>
        <div class="col-md-3"><label class="form-label">Hasta</label><input type="month" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-sm board-custom-to"></div>
        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary btn-sm mt-auto">Aplicar rango</button></div>
    </div>
</div>
</div></div></form>

<div class="siget-kanban" aria-label="Tablero Kanban de cargas">
@foreach($columns as $key=>$column)<section class="siget-kanban-column siget-kanban-{{ $key }}"><header><div><h3><i class="bi {{ $column['icon'] }} me-1"></i>{{ $column['label'] }}</h3><p>{{ $column['description'] }}</p></div><span>{{ $column['loads']->count() }}</span></header><div class="siget-kanban-stack">
@forelse($column['loads'] as $load)@php $priority=strtoupper((string)($load->priority??'NORMAL')); $traffic=$load->traffic_light instanceof \BackedEnum?$load->traffic_light->value:(string)$load->traffic_light; @endphp
<article class="siget-load-card {{ $load->board_overdue?'is-overdue':'' }}"><div class="d-flex justify-content-between gap-2 align-items-start"><div><span class="siget-load-agency">{{ $load->agency?->name }}</span><h4>{{ $load->title }}</h4></div><span class="siget-traffic siget-traffic-{{ strtolower($traffic?:'gray') }}" title="Semáforo {{ $traffic }}"></span></div><div class="d-flex flex-wrap gap-1 mb-2"><span class="badge siget-status">{{ $load->board_status_label }}</span><span class="badge siget-priority siget-priority-{{ strtolower($priority) }}">{{ $priority }}</span>@if($load->board_overdue)<span class="badge text-bg-danger">Vencida</span>@endif @if($load->is_blocked)<span class="badge text-bg-secondary">Bloqueada</span>@endif</div>@if($load->board_unit_names->isNotEmpty())<div class="siget-load-units">@foreach($load->board_unit_names as $unitName)<span><i class="bi bi-diagram-3"></i> {{ $unitName }}</span>@endforeach</div>@endif<div class="siget-load-meta"><span><i class="bi bi-calendar-event"></i> {{ $load->effective_open_at?->format('d/m/Y')??'Sin fecha' }}</span><span><i class="bi bi-alarm"></i> {{ $load->effective_close_at?->format('d/m/Y H:i')??'Sin límite' }}</span></div><div class="d-flex justify-content-between small mt-3 mb-1"><span>Avance visible</span><strong>{{ number_format($load->board_progress,0) }}%</strong></div><div class="progress"><div class="progress-bar" style="width: {{ $load->board_progress }}%"></div></div><div class="siget-load-footer"><div class="siget-load-counters"><span title="Entregables"><i class="bi bi-check2-square"></i> {{ $load->deliverables->count() }}</span><span title="Evidencias"><i class="bi bi-paperclip"></i> {{ $load->board_evidence_count }}</span><span title="Observaciones"><i class="bi bi-chat-left-text"></i> {{ $load->board_observation_count }}</span></div><a href="{{ route('loads.show',$load) }}" class="btn btn-sm btn-outline-primary">Abrir</a></div>@if($load->board_responsibles->isNotEmpty())<div class="siget-assignees mt-2"><i class="bi bi-person-check"></i> {{ $load->board_responsibles->join(', ') }}</div>@endif</article>
@empty<div class="siget-kanban-empty"><i class="bi {{ $column['icon'] }}"></i><span>No hay cargas en esta etapa.</span></div>@endforelse
</div></section>@endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('loadBoardFilters');
 const agency=document.getElementById('boardAgency');
 const pauta=document.getElementById('boardPauta');
 const from=form?.querySelector('[name="from"]'),to=form?.querySelector('[name="to"]');
 const custom=document.getElementById('boardCustomRange');
 const summary=document.getElementById('boardPeriodSummary');
 const min='{{ $periodBounds['min'] ?? '' }}',max='{{ $periodBounds['max'] ?? '' }}';

 const syncPautas=()=>{
   if(!agency||!pauta)return;
   const agencyId=agency.value;
   Array.from(pauta.options).forEach(option=>{
      if(!option.value)return;
      option.hidden=!!agencyId && option.dataset.agencyId && option.dataset.agencyId!==agencyId;
   });
   const selected=pauta.selectedOptions[0];
   if(agencyId&&selected?.dataset.agencyId&&selected.dataset.agencyId!==agencyId)pauta.value='';
 };

 const syncMonths=()=>{
   if(!from||!to)return;
   from.min=min; from.max=max; to.min=min; to.max=max;
   if(from.value)to.min=from.value;
   if(to.value)from.max=to.value;
   if(from.value&&to.value&&from.value>to.value)to.value=from.value;
   if(summary)summary.textContent=from.value&&to.value ? from.value+' → '+to.value : (min&&max ? min+' → '+max : 'Periodo completo de la pauta seleccionada');
 };

 const applyPreset=(range)=>{
   const now=new Date(), year=now.getFullYear();
   let start=now.getMonth()+1, end=start;
   if(range==='quarter') start=Math.floor(now.getMonth()/3)*3+1;
   const startValue=year+'-'+String(start).padStart(2,'0');
   const endValue=year+'-'+String(end).padStart(2,'0');
   if(from)from.value=startValue;
   if(to)to.value=endValue;
   custom?.classList.add('d-none');
   syncMonths();
   form?.submit();
 };

 document.querySelectorAll('.board-range-btn').forEach(button=>{
   button.addEventListener('click',()=>{
     document.querySelectorAll('.board-range-btn').forEach(x=>x.classList.remove('active'));
     button.classList.add('active');
     if(button.dataset.boardRange==='custom'){
       custom?.classList.remove('d-none');
       return;
     }
     applyPreset(button.dataset.boardRange);
   });
 });

 agency?.addEventListener('change',syncPautas);
 pauta?.addEventListener('change',()=>custom?.classList.remove('d-none'));
 from?.addEventListener('change',syncMonths);
 to?.addEventListener('change',syncMonths);
 syncPautas();
 syncMonths();
 if(from?.value||to?.value)custom?.classList.remove('d-none');
});
</script>

</div>
@endsection
