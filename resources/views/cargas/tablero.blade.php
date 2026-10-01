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
@media(max-width:1200px){.board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.board-direction-compact .board-kpi{height:68px;min-height:68px}}

/* Densidad visual del módulo Tablero: solo presentación; no cambia formularios, rutas ni datos. */
.board-direction-compact .board-heading{margin-bottom:8px!important}
.board-direction-compact .board-heading h2{font-size:.92rem}
.board-direction-compact .board-heading p{font-size:.62rem}
.board-direction-compact .board-heading .btn{height:28px;font-size:.64rem;padding:.2rem .45rem}
.board-direction-compact .board-kpis{gap:6px!important;margin-bottom:10px!important}
.board-direction-compact .board-kpi{min-height:64px;height:64px;padding:7px 8px;grid-template-columns:25px 1fr;column-gap:7px;border-radius:10px}
.board-direction-compact .board-kpi .board-kpi-icon{width:25px;height:25px;border-radius:7px;font-size:.76rem}
.board-direction-compact .board-kpi small{font-size:.58rem}
.board-direction-compact .board-kpi strong{font-size:.98rem}
.board-direction-compact .siget-card{border-radius:11px!important}
.board-direction-compact .siget-card .card-header{padding:9px 11px}
.board-direction-compact .siget-card .card-header h2{font-size:.82rem}
.board-direction-compact .siget-card .card-header p{font-size:.62rem}
.board-direction-compact .siget-card .card-body{padding:9px}
.board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:7px}
.board-direction-compact .siget-dependency-card{padding:8px;border-radius:9px}
.board-direction-compact .siget-dependency-logo{width:32px;height:32px;border-radius:8px}
.board-direction-compact .siget-dependency-stats{gap:4px 7px;margin-top:7px;font-size:.62rem}
.board-direction-compact #loadBoardFilters{margin-bottom:10px!important}
.board-direction-compact #loadBoardFilters .card-body{padding:7px 8px}
.board-direction-compact #loadBoardFilters .row{row-gap:.35rem!important}
.board-direction-compact #loadBoardFilters .form-label{font-size:.61rem;margin-bottom:.15rem}
.board-direction-compact #loadBoardFilters .form-control,
.board-direction-compact #loadBoardFilters .form-select{height:29px;min-height:29px;padding:.2rem .4rem;font-size:.64rem}
.board-direction-compact #loadBoardFilters .btn{height:29px;min-height:29px;font-size:.62rem;padding:.2rem .38rem}
.board-direction-compact .board-period-segmented{margin-top:6px!important;font-size:.62rem}
.board-direction-compact .board-period-segmented .btn{height:26px;min-height:26px;padding:.18rem .38rem;font-size:.59rem}
.board-direction-compact .board-period-segmented .small{font-size:.61rem}
.board-direction-compact .board-period-segmented .form-label{font-size:.6rem}


/* Estándar visual universal del tablero de cargas: cuatro estados compactos y alineados. */
.board-direction-compact{
    --board-gap:6px;
    --board-radius:9px;
}
.board-direction-compact .siget-board-heading{padding:0 0 2px}
.board-direction-compact .board-heading .scope-label{font-size:.58rem!important;line-height:1.05}
.board-direction-compact .board-heading h2{font-size:.9rem!important;line-height:1.1}
.board-direction-compact .board-heading p{font-size:.6rem!important;line-height:1.15}
.board-direction-compact .board-kpis{gap:6px!important;margin-bottom:8px!important}
.board-direction-compact .board-kpi{min-height:58px!important;height:58px!important;padding:6px 7px!important;grid-template-columns:23px 1fr!important;column-gap:6px!important;border-radius:9px!important}
.board-direction-compact .board-kpi .board-kpi-icon{width:23px!important;height:23px!important;border-radius:6px!important;font-size:.68rem!important}
.board-direction-compact .board-kpi small{font-size:.56rem!important}
.board-direction-compact .board-kpi strong{font-size:.9rem!important}
.board-direction-compact .siget-card{margin-bottom:8px!important;border-radius:10px!important}
.board-direction-compact .siget-card .card-header{padding:8px 10px!important}
.board-direction-compact .siget-card .card-header h2{font-size:.78rem!important}
.board-direction-compact .siget-card .card-header p{font-size:.58rem!important;line-height:1.15}
.board-direction-compact .siget-card .card-body{padding:8px!important}
.board-direction-compact .siget-dependency-grid{gap:6px!important}
.board-direction-compact .siget-dependency-card{padding:7px!important;border-radius:8px!important}
.board-direction-compact .siget-dependency-logo{width:29px!important;height:29px!important;border-radius:7px!important}
.board-direction-compact .siget-dependency-stats{font-size:.58rem!important;gap:3px 6px!important;margin-top:5px!important}
.board-direction-compact #loadBoardFilters{margin-bottom:8px!important}
.board-direction-compact #loadBoardFilters .card-body{padding:6px 7px!important}
.board-direction-compact #loadBoardFilters .form-label{font-size:.58rem!important}
.board-direction-compact #loadBoardFilters .form-control,.board-direction-compact #loadBoardFilters .form-select{height:27px!important;min-height:27px!important;font-size:.61rem!important;padding:.2rem .4rem}
.board-direction-compact #loadBoardFilters .btn{height:27px!important;min-height:27px!important;font-size:.58rem!important}
.board-direction-compact .board-period-segmented{margin-top:5px!important}
.board-direction-compact .board-period-segmented .btn{height:24px!important;min-height:24px!important;font-size:.56rem!important}

/* Las cuatro etapas comparten exactamente la misma jerarquía, altura y separación. */
.board-direction-compact .siget-kanban{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--board-gap)!important;align-items:start}
.board-direction-compact .siget-kanban-column{min-width:0!important;width:100%!important;border-radius:var(--board-radius)!important}
.board-direction-compact .siget-kanban-column>header{min-height:50px!important;height:50px!important;padding:6px 7px!important;gap:5px!important}
.board-direction-compact .siget-kanban-column>header>div:first-child{min-width:0!important;flex:1 1 auto}
.board-direction-compact .siget-kanban-column>header h3{font-size:.68rem!important;line-height:1.05!important;font-weight:700;margin:0!important}
.board-direction-compact .siget-kanban-column>header h3 .bi{margin-right:2px!important}
.board-direction-compact .siget-kanban-column>header p{font-size:.52rem!important;line-height:1.05!important;margin:2px 0 0!important;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:1;overflow:hidden}
.board-direction-compact .siget-kanban-column>header>span{min-width:20px!important;width:20px;height:20px!important;padding:0!important;border-radius:6px!important;font-size:.56rem!important}
.board-direction-compact .siget-kanban-stack{padding:5px!important;gap:5px!important;min-height:110px}
.board-direction-compact .siget-load-card{padding:6px!important;border-radius:7px!important}
.board-direction-compact .siget-load-agency{font-size:.5rem!important;line-height:1!important;margin-bottom:1px;display:block}
.board-direction-compact .siget-load-card h4{font-size:.62rem!important;line-height:1.08!important;margin:0 0 4px!important;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
.board-direction-compact .siget-load-card .badge{font-size:.47rem!important;line-height:1!important;padding:.18rem .28rem!important;border-radius:4px!important}
.board-direction-compact .siget-load-units,.board-direction-compact .siget-load-meta,.board-direction-compact .siget-assignees{font-size:.49rem!important;line-height:1.08!important}
.board-direction-compact .siget-load-units{gap:2px!important;margin-top:3px}
.board-direction-compact .siget-load-meta{gap:2px!important;margin-top:3px!important}
.board-direction-compact .siget-load-card .small{font-size:.5rem!important}
.board-direction-compact .siget-load-card .progress{height:3px!important}
.board-direction-compact .siget-load-footer{margin-top:4px!important;padding-top:4px!important;gap:4px!important}
.board-direction-compact .siget-load-counters{gap:4px!important;font-size:.48rem!important}
.board-direction-compact .siget-load-footer .btn{height:22px!important;min-height:22px!important;padding:.12rem .3rem!important;font-size:.5rem!important}
.board-direction-compact .siget-kanban-empty{min-height:90px!important;padding:10px 5px!important;font-size:.54rem!important}
@media(max-width:900px){.board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}.board-direction-compact .siget-kanban-column>header{height:auto!important;min-height:46px!important}}
@media(max-width:520px){.board-direction-compact .siget-kanban{grid-template-columns:1fr!important}}


/* Ajuste final de escala de las cápsulas de estado: prioriza que las 4 etapas sean visibles completas. */
.board-direction-compact{
    --board-gap:4px;
    --board-radius:7px;
}
.board-direction-compact .siget-kanban{
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:var(--board-gap)!important;
}
.board-direction-compact .siget-kanban-column{
    min-width:0!important;
    max-width:100%!important;
    width:100%!important;
    overflow:hidden!important;
    border-radius:var(--board-radius)!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:42px!important;
    min-height:42px!important;
    padding:5px 6px!important;
    gap:4px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.62rem!important;
    line-height:1!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis;
}
.board-direction-compact .siget-kanban-column>header p{
    display:none!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:18px!important;
    min-width:18px!important;
    height:18px!important;
    border-radius:5px!important;
    font-size:.52rem!important;
}
.board-direction-compact .siget-kanban-stack{
    padding:4px!important;
    gap:4px!important;
    min-height:75px!important;
}
.board-direction-compact .siget-load-card{
    padding:5px!important;
    border-radius:6px!important;
}
.board-direction-compact .siget-load-card h4{
    font-size:.57rem!important;
    line-height:1.04!important;
    -webkit-line-clamp:2;
    margin:0 0 3px!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.46rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.43rem!important;
    padding:.14rem .22rem!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.44rem!important;
    line-height:1.03!important;
}
.board-direction-compact .siget-load-units{margin-top:2px!important;gap:1px!important}
.board-direction-compact .siget-load-meta{margin-top:2px!important;gap:1px!important}
.board-direction-compact .siget-load-card .small{font-size:.45rem!important}
.board-direction-compact .siget-load-card .progress{height:2px!important}
.board-direction-compact .siget-load-footer{
    margin-top:3px!important;
    padding-top:3px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-counters{font-size:.43rem!important;gap:3px!important}
.board-direction-compact .siget-load-footer .btn{
    height:20px!important;
    min-height:20px!important;
    padding:.1rem .25rem!important;
    font-size:.45rem!important;
}
@media(max-width:980px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
        gap:3px!important;
    }
    .board-direction-compact .siget-kanban-column>header{
        padding:4px 5px!important;
    }
    .board-direction-compact .siget-kanban-column>header h3{
        font-size:.56rem!important;
    }
}
@media(max-width:760px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:5px!important;
    }
}
@media(max-width:520px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:1fr!important;
    }
}


/* Corrección visual definitiva: las cápsulas de estado son encabezados compactos y las 4 etapas caben completas. */
.board-direction-compact .siget-kanban{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:3px!important;
    width:100%!important;
    max-width:100%!important;
    overflow:visible!important;
}
.board-direction-compact .siget-kanban-column{
    min-width:0!important;
    width:100%!important;
    max-width:none!important;
    border-radius:5px!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:30px!important;
    min-height:30px!important;
    padding:3px 5px!important;
    gap:3px!important;
    align-items:center!important;
}
.board-direction-compact .siget-kanban-column>header>div:first-child{
    min-width:0!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.54rem!important;
    line-height:1!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
    margin:0!important;
}
.board-direction-compact .siget-kanban-column>header h3 .bi{
    font-size:.55rem!important;
    margin-right:1px!important;
}
.board-direction-compact .siget-kanban-column>header p{
    display:none!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:15px!important;
    min-width:15px!important;
    height:15px!important;
    padding:0!important;
    border-radius:4px!important;
    font-size:.44rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-kanban-stack{
    min-height:55px!important;
    padding:3px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-card{
    padding:4px!important;
    border-radius:5px!important;
}
.board-direction-compact .siget-load-card h4{
    font-size:.52rem!important;
    line-height:1.02!important;
    margin:0 0 2px!important;
    -webkit-line-clamp:2!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.41rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card .badge{
    font-size:.39rem!important;
    padding:.1rem .18rem!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta,
.board-direction-compact .siget-assignees{
    font-size:.4rem!important;
    line-height:1.01!important;
}
.board-direction-compact .siget-load-units,
.board-direction-compact .siget-load-meta{gap:1px!important;margin-top:2px!important}
.board-direction-compact .siget-load-card .small{font-size:.41rem!important}
.board-direction-compact .siget-load-card .progress{height:2px!important}
.board-direction-compact .siget-load-footer{
    margin-top:2px!important;
    padding-top:2px!important;
    gap:2px!important;
}
.board-direction-compact .siget-load-counters{gap:2px!important;font-size:.38rem!important}
.board-direction-compact .siget-load-footer .btn{
    height:18px!important;
    min-height:18px!important;
    padding:.05rem .2rem!important;
    font-size:.4rem!important;
}
.board-direction-compact .siget-kanban-empty{
    min-height:50px!important;
    padding:6px 3px!important;
    font-size:.43rem!important;
}
@media(max-width:760px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:520px){
    .board-direction-compact .siget-kanban{grid-template-columns:1fr!important}
}

.board-direction-compact .siget-kanban{grid-template-columns:repeat(4,220px)!important;width:fit-content!important;max-width:100%!important;gap:6px!important}.board-direction-compact .siget-kanban-column{width:220px!important;min-width:220px!important;max-width:220px!important}.board-direction-compact .siget-kanban-column>header{height:36px!important;min-height:36px!important;max-height:36px!important;padding:3px 6px!important}.board-direction-compact .siget-kanban-column>header h3{font-size:.58rem!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}.board-direction-compact .siget-kanban-stack{padding:4px!important;gap:4px!important;min-height:50px!important}.board-direction-compact .siget-load-card{width:100%!important;min-width:0!important;padding:5px!important;box-sizing:border-box!important}.board-direction-compact .siget-load-card *{max-width:100%!important;min-width:0!important;overflow-wrap:anywhere!important}@media(max-width:980px){.board-direction-compact .siget-kanban{grid-template-columns:repeat(2,220px)!important;width:446px!important}}@media(max-width:520px){.board-direction-compact .siget-kanban{grid-template-columns:220px!important;width:220px!important}}

/* ESTADOS DEL TABLERO — SOLO TAMAÑO DE CAJAS, TODOS LOS ROLES */
.board-direction-compact .siget-kanban{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,205px))!important;
    justify-content:start!important;
    align-items:start!important;
    gap:6px!important;
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    overflow:visible!important;
}
.board-direction-compact .siget-kanban-column{
    width:205px!important;
    min-width:205px!important;
    max-width:205px!important;
    margin:0!important;
    box-sizing:border-box!important;
}
.board-direction-compact .siget-kanban-column>header{
    min-height:38px!important;
    height:38px!important;
    max-height:38px!important;
    padding:4px 6px!important;
    gap:4px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.61rem!important;
    line-height:1.05!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-kanban-column>header p{
    display:none!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:18px!important;
    min-width:18px!important;
    height:18px!important;
    font-size:.5rem!important;
    padding:0!important;
}
.board-direction-compact .siget-kanban-stack{
    padding:5px!important;
    gap:5px!important;
    min-height:70px!important;
}
@media(max-width:980px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:5px!important}
    .board-direction-compact .siget-kanban-column{width:100%!important;min-width:0!important;max-width:100%!important}
}
@media(max-width:760px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .board-direction-compact .siget-kanban-column{width:100%!important;min-width:0!important;max-width:100%!important}
}
@media(max-width:520px){.board-direction-compact .siget-kanban{grid-template-columns:1fr!important}}

</style>