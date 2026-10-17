import './bootstrap';
import 'bootstrap';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import bootstrap5Plugin from '@fullcalendar/bootstrap5';
import Chart from 'chart.js/auto';

let palette = window.SigetAppearance?.palette() || ['#2563eb', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7'];
const charts = [];
function refreshAppearance() {
    palette = window.SigetAppearance?.palette() || palette;
    const dark = document.documentElement.dataset.bsTheme === 'dark';
    Chart.defaults.color = dark ? '#a1a1aa' : '#52525b';
    Chart.defaults.borderColor = dark ? '#27272a' : '#e4e4e7';
    charts.forEach(chart => {
        (chart.data.datasets || []).forEach((dataset, i) => {
            if (chart.$sigetSemanticColors) return;
            const color = palette[i % palette.length];
            const categorical = Array.isArray(dataset.backgroundColor);
            dataset.borderColor = categorical ? chart.data.labels.map((_, j) => palette[j % palette.length]) : color;
            dataset.backgroundColor = categorical ? chart.data.labels.map((_, j) => palette[j % palette.length]) : color;
            dataset.pointBackgroundColor = color;
        });
        if (chart.options.scales) Object.values(chart.options.scales).forEach(scale => {
            if (scale.ticks) scale.ticks.color = Chart.defaults.color;
            if (scale.grid) scale.grid.color = Chart.defaults.borderColor;
        });
        chart.update();
        chart.canvas.dataset.chartColors = JSON.stringify(chart.data.datasets.map(d => d.backgroundColor));
    });
}
document.addEventListener('siget:appearance', refreshAppearance);

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
    const manager = document.querySelector('[data-file-manager]');
    if (!manager) return;
    const render = () => {
        const value = window.SigetAppearance?.get('fileview') || 'grid';
        manager.classList.toggle('siget-file-list-view', value === 'list');
        document.querySelectorAll('[data-file-view]').forEach(button => {
            const selected = button.dataset.fileView === value;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
    };
    document.querySelectorAll('[data-file-view]').forEach(button => button.addEventListener('click', () => window.SigetAppearance?.set('fileview', button.dataset.fileView)));
    document.addEventListener('siget:appearance', render);
    render();
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
        const links = q.links || [];
        delete q.links;
        if (links.some(Boolean)) {
            q.options.onClick = (_event, elements) => {
                const target = links[elements[0]?.index];
                if (target) window.location.assign(target);
            };
            q.options.onHover = (_event, elements) => {
                c.style.cursor = links[elements[0]?.index] ? 'pointer' : 'default';
            };
        }
        if (c.closest('.siget-dashboard')) {
            q.options.animation = { duration: 450 };
            q.plugins = [...(q.plugins || []), {
                id: 'sigetDashboardValues',
                afterDatasetsDraw(chart) {
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.fillStyle = Chart.defaults.color;
                    ctx.font = '600 12px system-ui, sans-serif';
                    if (chart.config.type === 'doughnut') {
                        const area = chart.chartArea;
                        ctx.textAlign = 'center';
                        ctx.font = '700 28px system-ui, sans-serif';
                        ctx.fillText(chart.data.datasets[0].data.reduce((a, b) => a + Number(b), 0), (area.left + area.right) / 2, (area.top + area.bottom) / 2 + 9);
                    } else {
                        chart.data.datasets.forEach((dataset, index) => {
                            if (!chart.isDatasetVisible(index)) return;
                            chart.getDatasetMeta(index).data.forEach((bar, i) => {
                                if (!dataset.data[i]) return;
                                const horizontal = chart.options.indexAxis === 'y';
                                ctx.textAlign = horizontal ? 'left' : 'center';
                                ctx.fillText(dataset.data[i], horizontal ? bar.x + 4 : bar.x, horizontal ? bar.y + 4 : bar.y - 5);
                            });
                        });
                    }
                    ctx.restore();
                }
            }];
            if (q.type === 'bar') {
                const axis = q.options.indexAxis === 'y' ? 'x' : 'y';
                q.options.scales[axis] = {...q.options.scales[axis], grace: '15%'};
            }
        }
        const semanticColors = Boolean(q.semanticColors);
        delete q.semanticColors;
        const chart = new Chart(c, q);
        chart.$sigetSemanticColors = semanticColors;
        c.dataset.chartReady = 'true';
        c.dataset.chartColors = JSON.stringify(chart.data.datasets.map(d => d.backgroundColor));
        charts.push(chart);
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
            {type:'bar',label:'Entradas (cargas)',data:data.entries,backgroundColor:palette[0],borderColor:palette[0],borderWidth:1,borderRadius:4,maxBarThickness:42,yAxisID:'y'},
            {type:'line',label:'Cierres (cargas)',data:data.closures,borderColor:palette[4],backgroundColor:palette[4],pointBackgroundColor:palette[4],pointBorderColor:'#fff',pointBorderWidth:1,pointRadius:4,pointHoverRadius:7,borderWidth:2,tension:.34,yAxisID:'y'},
            {type:'line',label:'Cumplimiento (%)',data:data.compliance,borderColor:'#35c77a',backgroundColor:'#35c77a',pointBackgroundColor:'#35c77a',pointBorderColor:'#fff',pointBorderWidth:1,pointRadius:4,pointHoverRadius:7,borderWidth:2,borderDash:[5,4],tension:.34,yAxisID:'y1'}
        ]},
        options:{responsive:true,maintainAspectRatio:false,animation:{duration:1500,easing:'easeOutQuart'},interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{color:document.documentElement.dataset.bsTheme==='dark'?'#e2e8f0':'#344054',usePointStyle:true,boxWidth:10,font:{size:11}}},tooltip:{callbacks:{label:ctx=>{const v=ctx.parsed.y;return `${ctx.dataset.label}: ${ctx.dataset.label.includes('%')?v.toFixed(1)+'%':v+' cargas'}`}}}},scales:{x:{title:{display:true,text:'Periodo (mes)',color:document.documentElement.dataset.bsTheme==='dark'?'#aeb9c7':'#667085',font:{size:11,weight:'600'}},ticks:{color:document.documentElement.dataset.bsTheme==='dark'?'#d7dee8':'#475467'},grid:{color:'rgba(255,255,255,.06)'}},y:{beginAtZero:true,title:{display:true,text:'Número de cargas / volumen de trabajo',color:palette[1],font:{size:11,weight:'600'}},ticks:{color:'#4f8dff',precision:0},grid:{color:'rgba(255,255,255,.07)'}},y1:{beginAtZero:true,min:0,max:100,position:'right',title:{display:true,text:'Capacidad de cierre (%)',color:palette[2],font:{size:11,weight:'600'}},ticks:{color:'#35c77a',callback:v=>v+'%'},grid:{drawOnChartArea:false}}}}
    });
    charts.push(chart);
}

function upgradeInstitutionalTrends() {
    installTrendStyles();
    if (document.querySelector('.siget-exec')) renderModernTrend('roleTrendChart');
    if (document.getElementById('executiveTrendChart')) renderModernTrend('executiveTrendChart');
}

function calendar() {
    const element = document.getElementById('sigetCalendar');
    if (!element) return;
    const agency = document.getElementById('calendarAgency');
    let requestVersion = 0;
    const refreshDates = async (start, end) => {
        const version = ++requestVersion;
        const url = new URL(element.dataset.programmedUrl, location.origin);
        url.searchParams.set('start', start.toISOString());
        url.searchParams.set('end', end.toISOString());
        if (agency?.value) url.searchParams.set('contracting_agency_id', agency.value);
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const dates = new Set((await response.json()).dates || []);
            if (version !== requestVersion) return;
            element.querySelectorAll('.fc-daygrid-day[data-date]').forEach(cell => {
                const disabled = !dates.has(cell.dataset.date);
                cell.classList.toggle('fc-day-disabled-by-siget', disabled);
                cell.title = disabled ? 'Día sin carga programada en la pauta confirmada.' : 'Fecha programada dentro de la dependencia y alcance seleccionados.';
            });
        } catch (error) { console.error('No fue posible actualizar las fechas programadas.', error); }
    };
    const calendar = new Calendar(element, {
        plugins: [dayGridPlugin, interactionPlugin, bootstrap5Plugin], themeSystem: 'bootstrap5', locale: 'es',
        initialView: 'dayGridMonth', initialDate: element.dataset.initialDate || undefined,
        height: 'auto', firstDay: 1,
        headerToolbar: {left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek'},
        buttonText: {today: 'Hoy', month: 'Mes', week: 'Semana'},
        events: {url: element.dataset.eventsUrl, extraParams: () => ({contracting_agency_id: agency?.value || ''})},
        datesSet: info => refreshDates(info.start, info.end),
        eventDidMount: info => {
            const props = info.event.extendedProps;
            info.el.style.setProperty('--siget-calendar-color', info.event.backgroundColor);
            info.el.dataset.loadId = info.event.id;
            info.el.title = `${props.agency} · ${props.appearance.label} · ${props.completion}% · ${props.tooltip}`;
            info.el.setAttribute('aria-label', info.el.title);
            const text = document.createElement('span');
            text.className = 'siget-calendar-state';
            text.textContent = props.appearance.label;
            info.el.appendChild(text);
        },
        eventClick: info => { info.jsEvent.preventDefault(); if (info.event.extendedProps.url) location.href = info.event.extendedProps.url; }
    });
    calendar.render();
    agency?.addEventListener('change', () => {
        calendar.refetchEvents();
        refreshDates(calendar.view.activeStart, calendar.view.activeEnd);
    });
}

document.addEventListener('DOMContentLoaded',()=>{refreshAppearance();passwords();files();renderCharts();setupReportTabAnimations();requestAnimationFrame(()=>requestAnimationFrame(upgradeInstitutionalTrends));calendar();document.querySelectorAll('form[data-confirm-close]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm('Esta acción validará y cerrará el expediente. ¿Continuar?'))e.preventDefault()}))});
