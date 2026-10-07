@extends('layouts.app')
@section('title', 'Tablero de cargas')
@section('page-title', 'Tablero de cargas por dependencia')
@section('content')
<div class="board-direction-compact">

<style>
/* SIGET — estándar visual del Tablero de cargas.
   Objetivo: recuperar tamaño general legible y compactar SOLO las cajas de información. */
.board-direction-compact{
    --board-primary:#123b68;
    --board-primary-2:#0d2d57;
    --board-teal:#0f929f;
    --board-green:#22a06b;
    --board-amber:#e7a118;
    --board-red:#c93333;
    --board-purple:#6650a8;
    --board-surface:var(--surface,#fff);
    --board-surface-2:var(--surface2,#f7f9fb);
    --board-border:var(--border,#dfe6ee);
    --board-muted:var(--muted,#68798e);
    color:var(--text,#1f3045);
    width:100%;
}

/* Encabezado y zona superior: tamaño normal. */
.board-direction-compact .board-heading{
    min-height:58px!important;
    margin:0 0 12px!important;
}
.board-direction-compact .board-heading h2{
    font-size:1.35rem!important;
    line-height:1.1!important;
    margin:0 0 3px!important;
    color:var(--board-primary-2)!important;
}
.board-direction-compact .board-heading p{
    font-size:.82rem!important;
    line-height:1.2!important;
    margin:0!important;
    color:var(--board-teal)!important;
}
.board-direction-compact .board-heading .scope-label{
    font-size:.68rem!important;
}
.board-direction-compact .board-heading .btn{
    height:36px!important;
    min-height:36px!important;
    padding:.35rem .7rem!important;
    font-size:.72rem!important;
    border-radius:7px!important;
}

/* KPI: visibles y no miniaturizados. */
.board-direction-compact .board-kpis{
    display:grid!important;
    grid-template-columns:repeat(6,minmax(0,1fr))!important;
    gap:8px!important;
    margin:0 0 12px!important;
}
.board-direction-compact .board-kpis>[class*="col-"]{
    width:auto!important;
    max-width:none!important;
    padding-left:0!important;
    padding-right:0!important;
}
.board-direction-compact .board-kpi{
    height:76px!important;
    min-height:76px!important;
    padding:9px 10px!important;
    display:grid!important;
    grid-template-columns:31px 1fr!important;
    grid-template-rows:auto 1fr!important;
    column-gap:8px!important;
    align-items:center!important;
    background:var(--board-surface)!important;
    border:1px solid var(--board-border)!important;
    border-radius:10px!important;
    color:var(--text,#1f3045)!important;
}
.board-direction-compact .board-kpi .board-kpi-icon{
    grid-row:1 / span 2!important;
    width:31px!important;
    height:31px!important;
    border-radius:8px!important;
    display:grid!important;
    place-items:center!important;
    font-size:.82rem!important;
}
.board-direction-compact .board-kpi small{
    font-size:.62rem!important;
    line-height:1.04!important;
    color:var(--board-muted)!important;
}
.board-direction-compact .board-kpi strong{
    font-size:1.08rem!important;
    line-height:1!important;
    color:var(--board-primary)!important;
}

/* Dependencias: compactas, pero legibles. */
.board-direction-compact>.siget-card{
    margin:0 0 10px!important;
    border-radius:10px!important;
}
.board-direction-compact>.siget-card .card-header{
    min-height:40px!important;
    padding:8px 11px!important;
}
.board-direction-compact>.siget-card .card-header h2{
    font-size:.82rem!important;
    line-height:1.1!important;
    margin:0!important;
}
.board-direction-compact>.siget-card .card-header p{
    font-size:.62rem!important;
    margin:2px 0 0!important;
}
.board-direction-compact>.siget-card .card-body{
    padding:7px 9px!important;
}
.board-direction-compact .siget-dependency-grid{
    display:grid!important;
    grid-template-columns:repeat(auto-fit,minmax(155px,1fr))!important;
    gap:7px!important;
}
.board-direction-compact .siget-dependency-card{
    min-height:58px!important;
    height:58px!important;
    padding:6px 7px!important;
    border-radius:8px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-dependency-logo{
    width:25px!important;
    height:25px!important;
    border-radius:6px!important;
}
.board-direction-compact .siget-dependency-top{
    gap:6px!important;
}
.board-direction-compact .siget-dependency-top strong{
    font-size:.58rem!important;
    line-height:1.02!important;
}
.board-direction-compact .siget-dependency-top small{
    font-size:.45rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-dependency-stats{
    display:flex!important;
    gap:7px!important;
    margin-top:4px!important;
    font-size:.46rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-dependency-card .progress{
    height:3px!important;
    margin-top:4px!important;
}

/* Filtros: tamaño intermedio y legible. */
.board-direction-compact #loadBoardFilters{
    margin:0 0 10px!important;
    border-radius:9px!important;
}
.board-direction-compact #loadBoardFilters .card-body{
    padding:8px 10px!important;
}
.board-direction-compact #loadBoardFilters .form-label{
    font-size:.60rem!important;
    margin-bottom:3px!important;
}
.board-direction-compact #loadBoardFilters .form-control,
.board-direction-compact #loadBoardFilters .form-select,
.board-direction-compact #loadBoardFilters .input-group-text{
    height:36px!important;
    min-height:36px!important;
    font-size:.65rem!important;
    padding:.25rem .45rem!important;
    border-radius:6px!important;
}
.board-direction-compact #loadBoardFilters .form-text{
    display:none!important;
}
.board-direction-compact #loadBoardFilters .btn{
    height:36px!important;
    min-height:36px!important;
    padding:.25rem .55rem!important;
    font-size:.63rem!important;
    border-radius:6px!important;
}

/* KANBAN — quatro estados visíveis, com caixas de carga compactas. */
.board-direction-compact .siget-kanban{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:10px!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    margin:0!important;
    overflow:visible!important;
    align-items:start!important;
}
.board-direction-compact .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    height:430px!important;
    min-height:430px!important;
    max-height:430px!important;
    border-radius:10px!important;
    overflow:hidden!important;
    background:var(--board-surface-2)!important;
    border:1px solid var(--board-border)!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:60px!important;
    min-height:60px!important;
    padding:8px 10px!important;
    display:flex!important;
    box-sizing:border-box!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.78rem!important;
    line-height:1.05!important;
    margin:0!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.57rem!important;
    line-height:1.1!important;
    margin:3px 0 0!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:24px!important;
    min-width:24px!important;
    height:24px!important;
    border-radius:7px!important;
    font-size:.62rem!important;
}
.board-direction-compact .siget-kanban-stack{
    height:370px!important;
    min-height:0!important;
    max-height:370px!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    padding:7px!important;
    gap:7px!important;
    scrollbar-width:thin!important;
}

/* Caja de información de una carga: esta es la parte compactada. */
.board-direction-compact .siget-load-card{
    width:100%!important;
    height:142px!important;
    min-height:142px!important;
    max-height:142px!important;
    padding:8px!important;
    margin:0!important;
    border-radius:8px!important;
    box-sizing:border-box!important;
    overflow:hidden!important;
    background:var(--board-surface)!important;
    border:1px solid var(--board-border)!important;
}
.board-direction-compact .siget-load-card .load-card-top{
    display:flex!important;
    align-items:flex-start!important;
    justify-content:space-between!important;
    gap:6px!important;
    height:28px!important;
}
.board-direction-compact .siget-load-card .load-card-title{
    flex:1 1 auto!important;
    min-width:0!important;
}
.board-direction-compact .siget-load-agency{
    display:block!important;
    max-width:100%!important;
    font-size:.50rem!important;
    line-height:1!important;
    margin:0!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card h4{
    width:100%!important;
    height:14px!important;
    max-height:14px!important;
    margin:2px 0 0!important;
    font-size:.66rem!important;
    line-height:14px!important;
    font-weight:800!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card .siget-traffic{
    flex:0 0 auto!important;
    width:9px!important;
    height:9px!important;
}
.board-direction-compact .siget-load-card .load-card-badges{
    display:flex!important;
    align-items:center!important;
    flex-wrap:nowrap!important;
    gap:4px!important;
    height:16px!important;
    margin:3px 0 3px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-load-card .load-card-badges .badge{
    height:15px!important;
    max-width:46%!important;
    padding:1px 5px!important;
    margin:0!important;
    border-radius:4px!important;
    font-size:.46rem!important;
    line-height:13px!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card .siget-load-units{
    height:13px!important;
    max-height:13px!important;
    margin:0!important;
    overflow:hidden!important;
    white-space:nowrap!important;
    text-overflow:ellipsis!important;
    font-size:.46rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta{
    display:flex!important;
    flex-wrap:nowrap!important;
    gap:6px!important;
    height:13px!important;
    max-height:13px!important;
    margin:3px 0 0!important;
    overflow:hidden!important;
    font-size:.45rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta span{
    max-width:50%!important;
    min-width:0!important;
    overflow:hidden!important;
    white-space:nowrap!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label{
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    height:12px!important;
    margin:3px 0 1px!important;
    font-size:.44rem!important;
    line-height:12px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label strong{
    font-size:.48rem!important;
}
.board-direction-compact .siget-load-card .load-card-progress{
    height:3px!important;
    min-height:3px!important;
    margin:0!important;
}
.board-direction-compact .siget-load-card .siget-load-footer{
    height:20px!important;
    margin:4px 0 0!important;
    padding-top:3px!important;
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:5px!important;
}
.board-direction-compact .siget-load-card .siget-load-counters{
    display:flex!important;
    align-items:center!important;
    gap:5px!important;
    min-width:0!important;
    font-size:.43rem!important;
    white-space:nowrap!important;
}
.board-direction-compact .siget-load-card .siget-load-footer .btn{
    height:21px!important;
    min-height:21px!important;
    padding:0 6px!important;
    border-radius:4px!important;
    font-size:.44rem!important;
}
.board-direction-compact .siget-load-card .siget-assignees{
    display:block!important;
    height:11px!important;
    max-height:11px!important;
    margin:3px 0 0!important;
    padding:0!important;
    border:0!important;
    font-size:.42rem!important;
    line-height:11px!important;
    white-space:nowrap!important;
    overflow:hidden!important;
    text-overflow:ellipsis!important;
}
.board-direction-compact .siget-kanban-empty{
    min-height:55px!important;
    height:55px!important;
    padding:8px!important;
    font-size:.53rem!important;
}

/* Cierre institucional: verde, como en el estándar. */
.board-direction-compact .siget-kanban-done{
    border-color:#22a06b!important;
}
.board-direction-compact .siget-kanban-done>header{
    border-top:4px solid #22a06b!important;
}
.board-direction-compact .siget-kanban-done .siget-load-card{
    border-color:rgba(34,160,107,.35)!important;
}

/* Responsive */
@media(max-width:1200px){
    .board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))!important}
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(4,minmax(140px,1fr))!important}
}
@media(max-width:1000px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:760px){
    .board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .board-direction-compact .siget-dependency-grid{grid-template-columns:repeat(2,minmax(140px,1fr))!important}
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:560px){
    .board-direction-compact .board-kpis{grid-template-columns:1fr 1fr!important}
    .board-direction-compact .siget-dependency-grid{grid-template-columns:1fr!important}
    .board-direction-compact .siget-kanban{grid-template-columns:1fr!important}
    .board-direction-compact .siget-kanban-column{height:auto!important;min-height:0!important;max-height:none!important}
    .board-direction-compact .siget-kanban-stack{height:auto!important;max-height:none!important;overflow:visible!important}
}

/* SIGET — ESTÁNDAR FINAL DE DENSIDAD Y TIPOGRAFÍA DEL KANBAN. */

.board-direction-compact{
    font-family:Inter,"Segoe UI",system-ui,sans-serif!important;
    font-size:1rem!important;
}
.board-direction-compact .siget-kanban{
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:10px!important;
    width:100%!important;
    min-width:0!important;
    max-width:100%!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    max-width:none!important;
    height:320px!important;
    min-height:320px!important;
    max-height:320px!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:68px!important;
    min-height:68px!important;
    padding:10px 11px!important;
    gap:8px!important;
}
.board-direction-compact .siget-kanban-column>header>div:first-child{
    min-width:0!important;
    flex:1 1 auto!important;
    overflow:hidden!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.90rem!important;
    line-height:1.12!important;
    font-weight:800!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.64rem!important;
    line-height:1.18!important;
    margin:4px 0 0!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:25px!important;
    min-width:25px!important;
    height:25px!important;
    font-size:.68rem!important;
    font-weight:800!important;
}
.board-direction-compact .siget-kanban-stack{
    height:252px!important;
    min-height:0!important;
    max-height:252px!important;
    padding:8px!important;
    gap:8px!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
}
.board-direction-compact .siget-kanban-stack>*{
    min-width:0!important;
}
.board-direction-compact .siget-load-card{
    width:100%!important;
    height:178px!important;
    min-height:178px!important;
    max-height:178px!important;
    padding:10px!important;
    border-radius:8px!important;
    min-width:0!important;
    overflow:hidden!important;
    font-family:inherit!important;
}
.board-direction-compact .siget-load-card .load-card-top,
.board-direction-compact .siget-load-card .load-card-title,
.board-direction-compact .siget-load-card .load-card-badges,
.board-direction-compact .siget-load-card .siget-load-meta,
.board-direction-compact .siget-load-card .siget-load-footer{
    min-width:0!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.62rem!important;
    line-height:1.05!important;
    font-weight:600!important;
}
.board-direction-compact .siget-load-card h4{
    height:17px!important;
    max-height:17px!important;
    margin:3px 0 0!important;
    font-size:.78rem!important;
    line-height:17px!important;
    font-weight:800!important;
}
.board-direction-compact .siget-load-card .siget-traffic{
    width:10px!important;
    height:10px!important;
}
.board-direction-compact .siget-load-card .load-card-badges{
    height:19px!important;
    margin:5px 0 4px!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-card .load-card-badges .badge{
    height:18px!important;
    padding:1px 6px!important;
    font-size:.58rem!important;
    line-height:16px!important;
    font-weight:700!important;
}
.board-direction-compact .siget-load-card .siget-load-units{
    height:15px!important;
    max-height:15px!important;
    font-size:.60rem!important;
    line-height:15px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta{
    gap:8px!important;
    height:15px!important;
    max-height:15px!important;
    margin-top:4px!important;
    font-size:.58rem!important;
    line-height:15px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta span{
    max-width:50%!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label{
    height:15px!important;
    margin:5px 0 2px!important;
    font-size:.58rem!important;
    line-height:15px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label strong{
    font-size:.66rem!important;
    font-weight:800!important;
}
.board-direction-compact .siget-load-card .load-card-progress{
    height:5px!important;
    min-height:5px!important;
}
.board-direction-compact .siget-load-card .siget-load-footer{
    height:24px!important;
    margin:5px 0 0!important;
    padding-top:4px!important;
    gap:6px!important;
}
.board-direction-compact .siget-load-card .siget-load-counters{
    gap:7px!important;
    font-size:.57rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card .siget-load-footer .btn{
    height:24px!important;
    min-height:24px!important;
    padding:0 7px!important;
    font-size:.58rem!important;
    font-weight:700!important;
}
.board-direction-compact .siget-load-card .siget-assignees{
    height:13px!important;
    max-height:13px!important;
    margin:4px 0 0!important;
    font-size:.55rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-kanban-empty{
    min-height:90px!important;
    height:90px!important;
    padding:12px!important;
    font-size:.68rem!important;
}
@media(max-width:760px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        overflow:visible!important;
    }
}
@media(max-width:560px){
    .board-direction-compact .siget-kanban{
        grid-template-columns:1fr!important;
    }
    .board-direction-compact .siget-kanban-column{
        height:auto!important;
        min-height:0!important;
        max-height:none!important;
    }
    .board-direction-compact .siget-kanban-stack{
        height:auto!important;
        max-height:none!important;
        overflow:visible!important;
    }
}


/* SIGET — AJUSTE FINAL: KANBAN MÁS COMPACTO Y ENCABEZADOS NEUTROS. */

.board-direction-compact .siget-kanban{
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:8px!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    overflow:visible!important;
}
.board-direction-compact .siget-kanban-column{
    width:100%!important;
    min-width:0!important;
    height:278px!important;
    min-height:278px!important;
    max-height:278px!important;
    border-radius:9px!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:56px!important;
    min-height:56px!important;
    padding:7px 9px!important;
    color:var(--text,#f4f7fb)!important;
}
.board-direction-compact .siget-kanban-column>header h3,
.board-direction-compact .siget-kanban-column>header h3 *,
.board-direction-compact .siget-kanban-column>header p{
    color:var(--text,#f4f7fb)!important;
}
html[data-bs-theme=dark] .board-direction-compact .siget-kanban-column>header h3,
html[data-bs-theme=dark] .board-direction-compact .siget-kanban-column>header h3 *{
    color:#f4f7fb!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.78rem!important;
    line-height:1.05!important;
    font-weight:800!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.54rem!important;
    line-height:1.08!important;
    margin:3px 0 0!important;
    color:var(--muted,#aeb7c5)!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:22px!important;
    min-width:22px!important;
    height:22px!important;
    border-radius:6px!important;
    font-size:.60rem!important;
}
.board-direction-compact .siget-kanban-stack{
    height:222px!important;
    min-height:0!important;
    max-height:222px!important;
    padding:6px!important;
    gap:6px!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
}
.board-direction-compact .siget-load-card{
    height:150px!important;
    min-height:150px!important;
    max-height:150px!important;
    padding:8px!important;
    border-radius:7px!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.55rem!important;
    line-height:1!important;
}
.board-direction-compact .siget-load-card h4{
    height:15px!important;
    max-height:15px!important;
    margin:2px 0 0!important;
    font-size:.70rem!important;
    line-height:15px!important;
}
.board-direction-compact .siget-load-card .load-card-badges{
    height:17px!important;
    margin:4px 0 3px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-card .load-card-badges .badge{
    height:16px!important;
    padding:1px 5px!important;
    font-size:.51rem!important;
    line-height:14px!important;
}
.board-direction-compact .siget-load-card .siget-load-units{
    height:13px!important;
    max-height:13px!important;
    font-size:.53rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta{
    height:13px!important;
    max-height:13px!important;
    margin-top:3px!important;
    gap:6px!important;
    font-size:.52rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label{
    height:13px!important;
    margin:4px 0 1px!important;
    font-size:.52rem!important;
    line-height:13px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label strong{
    font-size:.58rem!important;
}
.board-direction-compact .siget-load-card .load-card-progress{
    height:4px!important;
    min-height:4px!important;
}
.board-direction-compact .siget-load-card .siget-load-footer{
    height:21px!important;
    margin:4px 0 0!important;
    padding-top:3px!important;
    gap:5px!important;
}
.board-direction-compact .siget-load-card .siget-load-counters{
    gap:6px!important;
    font-size:.51rem!important;
}
.board-direction-compact .siget-load-card .siget-load-footer .btn{
    height:21px!important;
    min-height:21px!important;
    padding:0 6px!important;
    font-size:.52rem!important;
}
.board-direction-compact .siget-load-card .siget-assignees{
    height:12px!important;
    max-height:12px!important;
    margin:3px 0 0!important;
    font-size:.49rem!important;
    line-height:12px!important;
}
.board-direction-compact .siget-kanban-empty{
    height:70px!important;
    min-height:70px!important;
    padding:8px!important;
    font-size:.58rem!important;
}
@media(max-width:1000px){
    .board-direction-compact .siget-kanban{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:560px){
    .board-direction-compact .siget-kanban{grid-template-columns:1fr!important}
    .board-direction-compact .siget-kanban-column{height:auto!important;min-height:0!important;max-height:none!important}
    .board-direction-compact .siget-kanban-stack{height:auto!important;max-height:none!important;overflow:visible!important}
}


/* SIGET — CORRECCIÓN FINAL SOLICITADA: encabezado neutro + tarjetas de detalle compactas. */
.board-direction-compact .siget-board-heading .scope-label{
    color:#68798e!important;
}
.board-direction-compact .siget-board-heading>div>p:last-of-type{
    color:#68798e!important;
}
html[data-bs-theme=dark] .board-direction-compact .siget-board-heading .scope-label,
html[data-bs-theme=dark] .board-direction-compact .siget-board-heading>div>p:last-of-type{
    color:#aebbc9!important;
}

/* Las columnas mantienen los cuatro estados, pero liberan más espacio vertical. */
.board-direction-compact .siget-kanban-column{
    height:270px!important;
    min-height:270px!important;
    max-height:270px!important;
}
.board-direction-compact .siget-kanban-column>header{
    height:50px!important;
    min-height:50px!important;
    padding:6px 8px!important;
}
.board-direction-compact .siget-kanban-column>header h3{
    font-size:.74rem!important;
    line-height:1.02!important;
}
.board-direction-compact .siget-kanban-column>header p{
    font-size:.50rem!important;
    line-height:1.02!important;
    margin:2px 0 0!important;
}
.board-direction-compact .siget-kanban-column>header>span{
    width:20px!important;
    min-width:20px!important;
    height:20px!important;
    font-size:.56rem!important;
}
.board-direction-compact .siget-kanban-stack{
    height:220px!important;
    min-height:0!important;
    max-height:220px!important;
    padding:5px!important;
    gap:5px!important;
}

/* Caja de detalle: de 150px a 122px, conservando la información esencial. */
.board-direction-compact .siget-load-card{
    height:122px!important;
    min-height:122px!important;
    max-height:122px!important;
    padding:6px!important;
    border-radius:6px!important;
}
.board-direction-compact .siget-load-card .load-card-top{
    height:22px!important;
    min-height:22px!important;
    gap:5px!important;
}
.board-direction-compact .siget-load-agency{
    font-size:.50rem!important;
    line-height:10px!important;
    height:10px!important;
}
.board-direction-compact .siget-load-card h4{
    height:12px!important;
    max-height:12px!important;
    margin:1px 0 0!important;
    font-size:.62rem!important;
    line-height:12px!important;
}
.board-direction-compact .siget-load-card .siget-traffic{
    width:8px!important;
    height:8px!important;
}
.board-direction-compact .siget-load-card .load-card-badges{
    height:14px!important;
    margin:2px 0 2px!important;
    gap:3px!important;
}
.board-direction-compact .siget-load-card .load-card-badges .badge{
    height:14px!important;
    padding:1px 4px!important;
    font-size:.46rem!important;
    line-height:12px!important;
}
.board-direction-compact .siget-load-card .siget-load-units{
    height:11px!important;
    max-height:11px!important;
    font-size:.48rem!important;
    line-height:11px!important;
}
.board-direction-compact .siget-load-card .siget-load-meta{
    height:11px!important;
    max-height:11px!important;
    margin-top:2px!important;
    gap:5px!important;
    font-size:.47rem!important;
    line-height:11px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label{
    height:10px!important;
    margin:3px 0 1px!important;
    font-size:.47rem!important;
    line-height:10px!important;
}
.board-direction-compact .siget-load-card .load-card-progress-label strong{
    font-size:.53rem!important;
}
.board-direction-compact .siget-load-card .load-card-progress{
    height:3px!important;
    min-height:3px!important;
}
.board-direction-compact .siget-load-card .siget-load-footer{
    height:18px!important;
    margin:3px 0 0!important;
    padding-top:0!important;
    gap:4px!important;
}
.board-direction-compact .siget-load-card .siget-load-counters{
    gap:5px!important;
    font-size:.45rem!important;
}
.board-direction-compact .siget-load-card .siget-load-footer .btn{
    height:18px!important;
    min-height:18px!important;
    padding:0 5px!important;
    border-radius:4px!important;
    font-size:.46rem!important;
}
.board-direction-compact .siget-load-card .siget-assignees{
    height:9px!important;
    max-height:9px!important;
    margin:2px 0 0!important;
    font-size:.42rem!important;
    line-height:9px!important;
}
@media(max-width:560px){
    .board-direction-compact .siget-kanban-column{
        height:auto!important;
        min-height:0!important;
        max-height:none!important;
    }
    .board-direction-compact .siget-kanban-stack{
        height:auto!important;
        max-height:none!important;
        overflow:visible!important;
    }
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
<article class="siget-load-card {{ $load->board_overdue?'is-overdue':'' }}">
    <div class="load-card-top">
        <div class="load-card-title">
            <span class="siget-load-agency">{{ $load->agency?->name }}</span>
            <h4 title="{{ $load->title }}">{{ $load->title }}</h4>
        </div>
        <span class="siget-traffic siget-traffic-{{ strtolower($traffic?:'gray') }}" title="Semáforo {{ $traffic }}"></span>
    </div>
    <div class="load-card-badges">
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
        <span><i class="bi bi-calendar-event"></i> {{ $load->effective_open_at?->format('d/m/Y')??'Sin fecha' }}</span>
        <span><i class="bi bi-alarm"></i> {{ $load->effective_close_at?->format('d/m/Y H:i')??'Sin límite' }}</span>
    </div>
    <div class="load-card-progress-label">
        <span>Avance visible</span>
        <strong>{{ number_format($load->board_progress,0) }}%</strong>
    </div>
    <div class="progress load-card-progress"><div class="progress-bar" style="width: {{ $load->board_progress }}%"></div></div>
    <div class="siget-load-footer">
        <div class="siget-load-counters">
            <span title="Entregables"><i class="bi bi-check2-square"></i> {{ $load->deliverables->count() }}</span>
            <span title="Evidencias"><i class="bi bi-paperclip"></i> {{ $load->board_evidence_count }}</span>
            <span title="Observaciones"><i class="bi bi-chat-left-text"></i> {{ $load->board_observation_count }}</span>
        </div>
        <a href="{{ route('loads.show',$load) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
    </div>
    @if($load->board_responsibles->isNotEmpty())
        <div class="siget-assignees"><i class="bi bi-person-check"></i> {{ $load->board_responsibles->join(', ') }}</div>
    @endif
</article>
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
