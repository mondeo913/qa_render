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
/* TAMAÑO FINAL ESTADOS_SIGET_20261001 */
.board-direction-compact .siget-kanban{display:flex!important;flex-wrap:nowrap!important;align-items:flex-start!important;gap:4px!important;width:100%!important;max-width:100%!important;overflow:visible!important}
.board-direction-compact .siget-kanban-column{flex:0 0 160px!important;width:160px!important;min-width:160px!important;max-width:160px!important;margin:0!important;border-radius:5px!important;overflow:hidden!important}
.board-direction-compact .siget-kanban-column>header{height:30px!important;min-height:30px!important;max-height:30px!important;padding:2px 4px!important;gap:2px!important;align-items:center!important}
.board-direction-compact .siget-kanban-column>header>div:first-child{min-width:0!important;flex:1 1 auto!important;overflow:hidden!important}
.board-direction-compact .siget-kanban-column>header h3{margin:0!important;font-size:.56rem!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.board-direction-compact .siget-kanban-column>header h3 .bi{font-size:.5rem!important;margin-right:1px!important}
.board-direction-compact .siget-kanban-column>header p{display:none!important}
.board-direction-compact .siget-kanban-column>header>span{width:14px!important;min-width:14px!important;height:14px!important;padding:0!important;border-radius:4px!important;font-size:.4rem!important;line-height:1!important}
.board-direction-compact .siget-kanban-stack{padding:3px!important;gap:3px!important;min-height:45px!important}
.board-direction-compact .siget-load-card{padding:4px!important;border-radius:4px!important}
.board-direction-compact .siget-load-card h4{font-size:.52rem!important;line-height:1.04!important;margin:0 0 2px!important}
.board-direction-compact .siget-load-agency{font-size:.4rem!important;line-height:1!important}
.board-direction-compact .siget-load-card .badge{font-size:.38rem!important;padding:.08rem .15rem!important}
.board-direction-compact .siget-load-units,.board-direction-compact .siget-load-meta,.board-direction-compact .siget-assignees{font-size:.4rem!important;line-height:1.02!important}
.board-direction-compact .siget-load-footer{margin-top:2px!important;padding-top:2px!important;gap:2px!important}
.board-direction-compact .siget-load-footer .btn{height:17px!important;min-height:17px!important;padding:.03rem .18rem!important;font-size:.38rem!important}
.board-direction-compact .siget-load-counters{gap:2px!important;font-size:.37rem!important}
@media(max-width:760px){.board-direction-compact .siget-kanban{flex-wrap:wrap!important}.board-direction-compact .siget-kanban-column{flex-basis:160px!important;width:160px!important;min-width:160px!important;max-width:160px!important}}
@media(max-width:360px){.board-direction-compact .siget-kanban-column{flex-basis:100%!important;width:100%!important;min-width:0!important;max-width:none!important}}


/* TABLERO ESTADOS GLOBAL TODOS_ROLES 20261001 */
/* Regla única y compartida para todos los roles que usan /tablero-cargas. */
.board-direction-compact .siget-kanban{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:4px!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    overflow:visible!important;
}
.board-direction-compact .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    border-radius:5px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:28px!important;
    min-height:28px!important;
    max-height:28px!important;
    padding:2px 4px!important;
    gap:2px!important;
    align-items:center!important;
}
.board-direction-compact .siget-kanban-column>header>div:first-child{
    min-width:0!important;
    flex:1 1 auto!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    margin:0!important;
    font-size:.55rem!important;
    line-height:1!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-kanban-column>header h3 .bi{
    font-size:.5rem!important;
    margin-right:1px!important;
}
.board-direction-compact .siget-kanban-column>header p{display:none!important}
.board-direction-compact .siget-kanban-column>header>span{
    width:14px!important;
    min-width:14px!important;
    height:14px!important;
    padding:0!important;
    border-radius:4px!important;
    font-size:.39rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-kanban-stack{
    min-height:40px!important;
    padding:3px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-card{
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    padding:4px!important;
    border-radius:4px!important;
    box-sizing:border-box!important;
}
.board-direction-compact .siget-load-card h4{font-size:.52rem!important;line-height:1.03!important;margin:0 0 2px!important}
.board-direction-compact .siget-load-agency{font-size:.4rem!important;line-height:1!important}
.board-direction-compact .siget-load-card .badge{font-size:.38rem!important;padding:.08rem .14rem!important}
.board-direction-compact .siget-load-units,.board-direction-compact .siget-load-meta,.board-direction-compact .siget-assignees{font-size:.39rem!important;line-height:1.02!important}
.board-direction-compact .siget-load-card .small{font-size:.4rem!important}
.board-direction-compact .siget-load-card .progress{height:2px!important}
.board-direction-compact .siget-load-footer{margin-top:2px!important;padding-top:2px!important;gap:2px!important}
.board-direction-compact .siget-load-counters{gap:2px!important;font-size:.36rem!important}
.board-direction-compact .siget-load-footer .btn{height:17px!important;min-height:17px!important;padding:.03rem .16rem!important;font-size:.38rem!important}
.board-direction-compact .siget-kanban-empty{min-height:45px!important;padding:5px 3px!important;font-size:.42rem!important}
@media(max-width:900px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:3px!important}
    .board-direction-compact .siget-kanban-column>header h3{font-size:.5rem!important}
}
@media(max-width:700px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:4px!important}
}
@media(max-width:420px){
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
