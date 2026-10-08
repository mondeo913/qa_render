import './bootstrap';
import 'bootstrap';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import bootstrap5Plugin from '@fullcalendar/bootstrap5';
import Chart from 'chart.js/auto';

const palette = ['#0db8c9','#3b82f6','#22a06b','#f59e0b','#d64550','#7c5ce7','#0ea5e9','#64748b'];
const charts = [];
const resolve = t => t === 'auto'
    ? (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light')
    : t;

function theme(t) {
    const r = resolve(t);
    document.documentElement.dataset.sigetTheme = t;
    document.documentElement.dataset.bsTheme = r;
    localStorage.setItem('siget-theme', t);
    const i = document.querySelector('[data-theme-icon]');
    if (i) i.className = `bi ${t === 'light' ? 'bi-sun' : t === 'dark' ? 'bi-moon-stars' : 'bi-circle-half'}`;
    document.querySelectorAll('[data-theme-value]').forEach(x => x.classList.toggle('active', x.dataset.themeValue === t));
    Chart.defaults.color = r === 'dark' ? '#c7cfda' : '#667085';
    Chart.defaults.borderColor = r === 'dark' ? '#3a3e46' : '#e4e8ef';
    charts.forEach(c => c.update());
}

function setupTheme() {
    theme(localStorage.getItem('siget-theme') || 'auto');
    document.querySelectorAll('[data-theme-value]').forEach(x => x.addEventListener('click', () => theme(x.dataset.themeValue)));
    matchMedia('(prefers-color-scheme:dark)').addEventListener('change', () => {
        if ((localStorage.getItem('siget-theme') || 'auto') === 'auto') theme('auto');
    });
}

function sidebar() {
    document.querySelectorAll('[data-siget-sidebar-toggle]').forEach(b => b.addEventListener('click', () => {
        if (matchMedia('(max-width:991px)').matches) document.body.classList.toggle('siget-sidebar-open');
        else {
            document.body.classList.toggle('siget-sidebar-collapsed');
            localStorage.setItem('siget-sidebar-collapsed', document.body.classList.contains('siget-sidebar-collapsed') ? '1' : '0');
        }
    }));
    document.querySelectorAll('[data-siget-sidebar-close]').forEach(b => b.addEventListener('click', () => document.body.classList.remove('siget-sidebar-open')));
    if (!matchMedia('(max-width:991px)').matches && localStorage.getItem('siget-sidebar-collapsed') === '1') document.body.classList.add('siget-sidebar-collapsed');
}

function passwords() {
    document.querySelectorAll('[data-password-toggle]').forEach(b => b.addEventListener('click', () => {
        const i = document.getElementById(b.dataset.passwordToggle);
        if (!i) return;
        i.type = i.type === 'password' ? 'text' : 'password';
        b.querySelector('i')?.classList.toggle('bi-eye');
        b.querySelector('i')?.classList.toggle('bi-eye-slash');
    }));
}

function files() {
    const m = document.querySelector('[data-file-manager]');
    if (!m) return;
    const apply = v => {
        m.classList.toggle('siget-file-list-view', v === 'list');
        localStorage.setItem('siget-file-view', v);
        document.querySelectorAll('[data-file-view]').forEach(b => b.classList.toggle('active', b.dataset.fileView === v));
    };
    apply(localStorage.getItem('siget-file-view') || 'grid');
    document.querySelectorAll('[data-file-view]').forEach(b => b.addEventListener('click', () => apply(b.dataset.fileView)));
}

function renderCharts() {
    document.querySelectorAll('script[data-siget-chart]').forEach(n => {
        const c = document.getElementById(n.dataset.sigetChart);
        if (!c) return;
        const q = JSON.parse(n.textContent);

        // Reportes antiguos/actuales pueden enviar la estructura Chart.js dentro de data.
        // Normalizamos ambas formas para evitar gráficos vacíos.
        if (q.data && (!q.labels || !q.datasets)) {
            q.labels = q.data.labels || q.labels || [];
            q.datasets = q.data.datasets || q.datasets || [];
        }

        // La pestaña Dependencias debe representar agency_performance, no direction_performance.
        // Tomamos los datos de la tabla visible del panel para mantenerlos sincronizados con el reporte filtrado.
        if (n.dataset.sigetChart === 'sigetReportUnits') {
            const panel = c.closest('.panel');
            const rows = [...(panel?.querySelectorAll('table.tbl tbody tr') || [])]
                .map(row => [...row.children].map(cell => cell.textContent.trim()))
                .filter(row => row.length >= 6 && row[0]);

            if (rows.length) {
                q.type = 'bar';
                q.labels = rows.map(row => row[0]);
                q.datasets = [{
                    label: 'Cumplimiento por dependencia %',
                    data: rows.map(row => Number.parseFloat(row[5].replace('%', '').replace(',', '.')) || 0)
                }];
            }
        }

        const intelligenceTrend = n.dataset.sigetChart === 'executiveTrendChart' && location.pathname.includes('/intelligence');
        if (intelligenceTrend) {
            q.type = 'bar';
            q.datasets = (q.datasets || []).map((d, i) => i === 0
                ? { ...d, type: 'bar', borderRadius: 5, maxBarThickness: 34 }
                : { ...d, type: 'line', pointRadius: 4, pointHoverRadius: 7, borderWidth: 2, tension: .34, fill: false });
            q.options = {
                ...(q.options || {}),
                animation: { duration: 1500, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: { ...(q.options?.plugins || {}), legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
            };
        }

        q.data = {
            labels: q.labels || [],
            datasets: (q.datasets || []).map((d, i) => ({
                ...d,
                borderColor: d.borderColor || palette[i % palette.length],
                backgroundColor: d.backgroundColor || (d.type === 'line' ? 'transparent' : palette[i % palette.length]),
                borderWidth: d.borderWidth || 2,
                tension: d.tension ?? (q.type === 'line' ? .32 : undefined),
                fill: d.fill ?? false,
                borderRadius: d.borderRadius ?? (d.type === 'bar' || q.type === 'bar' ? 6 : undefined)
            }))
        };
        delete q.labels;
        delete q.datasets;
        q.options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1600, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: ['bar', 'line'].includes(q.type)
                ? { y: { beginAtZero: true }, x: { grid: { display: false } }, ...(q.data.datasets.some(x => x.yAxisID === 'y1') ? { y1: { beginAtZero: true, position: 'right', max: 100, grid: { drawOnChartArea: false } } } : {}) }
                : undefined,
            ...(q.options || {})
        };
        charts.push(new Chart(c, q));
    });
}

function animateReportTabCharts(panel) {
    if (!panel) return;
    panel.querySelectorAll('canvas').forEach(canvas => {
        const chart = Chart.getChart(canvas);
        if (!chart) return;
        chart.reset();
        chart.update();
    });
}

function setupReportTabAnimations() {
    document.querySelectorAll('[data-r]').forEach(button => {
        button.addEventListener('click', () => {
            setTimeout(() => {
                const panel = document.getElementById(button.dataset.r);
                animateReportTabCharts(panel);
                window.dispatchEvent(new Event('resize'));
            }, 80);
        });
    });
}

function installTrendStyles() {
    // El sistema visual Zenith/Shadcn vive exclusivamente en app.scss.
    // Se conserva la función por compatibilidad con el flujo de gráficas.
}
function buildTrendDataFromChart(chart) {
    if (!chart?.data) return null;
    const labels = chart.data.labels || [];
    const get = label => {
        const ds = chart.data.datasets.find(d => d.label === label);
        return ds ? ds.data.map(v => Number(v ?? 0)) : [];
    };
    return {
        labels,
        entries: get('Entradas').length ? get('Entradas') : get('Entradas (cargas)'),
        closures: get('Cierres').length ? get('Cierres') : get('Cierres (cargas)'),
        compliance: get('Cumplimiento %').length ? get('Cumplimiento %') : get('Cumplimiento (%)')
    };
}

function renderModernTrend(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return;
    const old = Chart.getChart(canvas);
    const data = buildTrendDataFromChart(old);
    if (!data || !data.labels.length) return;
    if (old) old.destroy();
    const host = canvas.closest('.panel,.siget-card,.role-card');
    if (!host) return;
    host.classList.add('siget-trend-modern');
    host.innerHTML = `
        <div class="stm-head"><div><div class="stm-title">Tendencia institucional <span style="font-size:.7rem;color:#9eb0c0">ⓘ</span></div><div class="stm-question">¿La capacidad de cierre acompaña el volumen de trabajo?</div></div><div class="stm-sub">Evolución mensual · SIGET</div></div>
        <div class="stm-kpis">
            <div class="stm-kpi"><div class="stm-kpi-label">Total entradas (periodo)</div><div class="stm-kpi-value">${data.entries.reduce((a,b)=>a+b,0)} <span style="font-size:.72rem;font-weight:400">cargas</span></div><div class="stm-kpi-note">Volumen de trabajo</div></div>
            <div class="stm-kpi"><div class="stm-kpi-label">Total cierres (periodo)</div><div class="stm-kpi-value">${data.closures.reduce((a,b)=>a+b,0)} <span style="font-size:.72rem;font-weight:400">cargas</span></div><div class="stm-kpi-note">Capacidad de cierre</div></div>
            <div class="stm-kpi"><div class="stm-kpi-label">Cumplimiento promedio</div><div class="stm-kpi-value">${(data.compliance.reduce((a,b)=>a+b,0)/(data.compliance.length||1)).toFixed(1)}%</div><div class="stm-kpi-note">Cierres / entradas × 100</div></div>
            <div class="stm-kpi"><div class="stm-kpi-label">Brecha de cierre (periodo)</div><div class="stm-kpi-value">${Math.max(0,data.entries.reduce((a,b)=>a+b,0)-data.closures.reduce((a,b)=>a+b,0))} <span style="font-size:.72rem;font-weight:400">cargas</span></div><div class="stm-kpi-note warn">Entradas que no alcanzaron cierre</div></div>
        </div>
        <div class="stm-chart"><canvas id="${canvasId}-modern"></canvas></div>
        <div class="stm-read"><strong>Lectura ejecutiva:</strong> la gráfica compara el volumen de trabajo (barras), la capacidad de cierre (línea roja) y el cumplimiento (línea verde). La escala izquierda muestra <strong>Número de cargas / volumen de trabajo</strong>; la derecha muestra <strong>Capacidad de cierre (%)</strong>.</div>
        <div class="stm-footer">
            <div class="stm-foot-item"><div class="stm-foot-title blue">Entradas (cargas)</div><div class="stm-foot-text">Volumen de cargas/entregables que ingresaron al sistema en cada periodo.</div></div>
            <div class="stm-foot-item"><div class="stm-foot-title red">Cierres (cargas)</div><div class="stm-foot-text">Volumen de cargas/entregables que fueron cerradas en cada periodo.</div></div>
            <div class="stm-foot-item"><div class="stm-foot-title green">Cumplimiento (%)</div><div class="stm-foot-text">Porcentaje de cargas cerradas respecto al volumen de trabajo del periodo.</div></div>
            <div class="stm-foot-item"><div class="stm-foot-title blue">Fórmula</div><div class="stm-foot-text">Cumplimiento = Cierres / Entradas × 100.</div></div>
            <div class="stm-foot-item"><div class="stm-foot-title gold">Meta institucional</div><div class="stm-foot-text">≥ 90% de cumplimiento mensual.</div></div>
        </div>`;

    const target = document.getElementById(`${canvasId}-modern`);
    const chart = new Chart(target, {
        type:'bar',
        data:{labels:data.labels,datasets:[
            {type:'bar',label:'Entradas (cargas)',data:data.entries,backgroundColor:'#2f73dc',borderColor:'#4f8dff',borderWidth:1,borderRadius:4,maxBarThickness:42,yAxisID:'y'},
            {type:'line',label:'Cierres (cargas)',data:data.closures,borderColor:'#ff4655',backgroundColor:'#ff4655',pointBackgroundColor:'#ff4655',pointBorderColor:'#fff',pointBorderWidth:1,pointRadius:4,pointHoverRadius:7,borderWidth:2,tension:.34,yAxisID:'y'},
            {type:'line',label:'Cumplimiento (%)',data:data.compliance,borderColor:'#35c77a',backgroundColor:'#35c77a',pointBackgroundColor:'#35c77a',pointBorderColor:'#fff',pointBorderWidth:1,pointRadius:4,pointHoverRadius:7,borderWidth:2,borderDash:[5,4],tension:.34,yAxisID:'y1'}
        ]},
        options:{responsive:true,maintainAspectRatio:false,animation:{duration:1500,easing:'easeOutQuart'},interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{color:'#eaf2f7',usePointStyle:true,boxWidth:10,font:{size:11}}},tooltip:{callbacks:{label:ctx=>{const v=ctx.parsed.y;return `${ctx.dataset.label}: ${ctx.dataset.label.includes('%')?v.toFixed(1)+'%':v+' cargas'}`}}}},scales:{x:{title:{display:true,text:'Periodo (mes)',color:'#b7c9d8',font:{size:11,weight:'600'}},ticks:{color:'#e5edf4'},grid:{color:'rgba(255,255,255,.06)'}},y:{beginAtZero:true,title:{display:true,text:'Número de cargas / volumen de trabajo',color:'#4f8dff',font:{size:11,weight:'600'}},ticks:{color:'#4f8dff',precision:0},grid:{color:'rgba(255,255,255,.07)'}},y1:{beginAtZero:true,min:0,max:100,position:'right',title:{display:true,text:'Capacidad de cierre (%)',color:'#35c77a',font:{size:11,weight:'600'}},ticks:{color:'#35c77a',callback:v=>v+'%'},grid:{drawOnChartArea:false}}}}
    });
    charts.push(chart);
}

function upgradeInstitutionalTrends() {
    installTrendStyles();
    if (document.querySelector('.siget-exec')) renderModernTrend('roleTrendChart');
    if (document.getElementById('executiveTrendChart')) renderModernTrend('executiveTrendChart');
}

function calendar(){const e=document.getElementById('sigetCalendar');if(!e)return;const a=document.getElementById('calendarAgency');let dates=new Set;const load=async(s,t)=>{const u=new URL(e.dataset.programmedUrl,location.origin);u.searchParams.set('start',s.toISOString());u.searchParams.set('end',t.toISOString());if(a?.value)u.searchParams.set('contracting_agency_id',a.value);const r=await fetch(u,{headers:{Accept:'application/json'}});if(r.ok)dates=new Set((await r.json()).dates||[])};const c=new Calendar(e,{plugins:[dayGridPlugin,interactionPlugin,bootstrap5Plugin],themeSystem:'bootstrap5',locale:'es',initialView:'dayGridMonth',height:'auto',firstDay:1,headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,dayGridWeek'},buttonText:{today:'Hoy',month:'Mes',week:'Semana'},events:{url:e.dataset.eventsUrl,extraParams:()=>({contracting_agency_id:a?.value||''})},datesSet:async i=>load(i.start,i.end),dayCellDidMount:i=>{const d=i.date.toISOString().slice(0,10);if(!dates.has(d)){i.el.classList.add('fc-day-disabled-by-siget');i.el.title='Día sin carga programada en la pauta confirmada.'}},eventDidMount:i=>i.el.title=`${i.event.extendedProps.status} · ${i.event.extendedProps.completion}%`,eventClick:i=>{i.jsEvent.preventDefault();if(i.event.extendedProps.url)location.href=i.event.extendedProps.url}});c.render();a?.addEventListener('change',()=>c.refetchEvents())}

document.addEventListener('DOMContentLoaded',()=>{setupTheme();sidebar();passwords();files();renderCharts();setupReportTabAnimations();requestAnimationFrame(()=>requestAnimationFrame(upgradeInstitutionalTrends));calendar();document.querySelectorAll('form[data-confirm-close]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm('Esta acción validará y cerrará el expediente. ¿Continuar?'))e.preventDefault()}))});
