(function () {
    'use strict';

    function parseChart(id) {
        var node = document.querySelector('script[data-siget-chart="' + id + '"]');
        if (!node) return null;
        try { return JSON.parse(node.textContent || '{}'); } catch (e) { return null; }
    }

    function statusFromTables() {
        var values = { PROGRAMADO: 0, REPROGRAMADO: 0, 'VALIDADO Y CERRADO': 0, VENCIDO: 0 };
        document.querySelectorAll('#comp table tbody tr').forEach(function (tr) {
            var cells = tr.children;
            if (!cells || cells.length < 5) return;
            values.PROGRAMADO += Number(cells[1].textContent.trim()) || 0;
            values.REPROGRAMADO += Number(cells[2].textContent.trim()) || 0;
            values['VALIDADO Y CERRADO'] += Number(cells[3].textContent.trim()) || 0;
            values.VENCIDO += Number(cells[4].textContent.trim()) || 0;
        });
        return [values.PROGRAMADO, values.REPROGRAMADO, values['VALIDADO Y CERRADO'], values.VENCIDO];
    }

    function agenciesFromTable() {
        var out = [];
        document.querySelectorAll('#exec .exec-semaforo tbody tr').forEach(function (tr) {
            var cells = tr.children;
            if (!cells || cells.length < 8) return;
            var name = cells[1].textContent.trim();
            if (!name || /no hay dependencias/i.test(name)) return;
            var pct = Number((cells[6].textContent || '').replace('%', '').replace(',', '.')) || 0;
            var close = Number((cells[7].textContent || '').replace('%', '').replace(',', '.')) || 0;
            out.push({ name: name, realized: pct, closed: close });
        });
        return out;
    }

    function monthsFallback() {
        var out = [];
        var d = new Date();
        for (var i = 5; i >= 0; i--) {
            var x = new Date(d.getFullYear(), d.getMonth() - i, 1);
            out.push(x.toLocaleDateString('es-MX', { month: 'short', year: '2-digit' }));
        }
        return out;
    }

    function resizeCanvas(canvas) {
        var rect = canvas.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;
        var w = Math.max(320, Math.floor(rect.width));
        var h = Math.max(220, Math.floor(rect.height));
        canvas.width = Math.floor(w * dpr);
        canvas.height = Math.floor(h * dpr);
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        var ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx: ctx, w: w, h: h };
    }

    function themeColors() {
        var dark = document.documentElement.dataset.bsTheme === 'dark';
        return {
            text: dark ? '#d9e2ec' : '#475467',
            grid: dark ? '#3a3e46' : '#e4e8ef',
            base: dark ? '#667085' : '#98a2b3',
            fills: ['#0db8c9', '#f59e0b', '#22a06b', '#d64550'],
            blue: '#3b82f6',
            green: '#22a06b'
        };
    }

    function label(ctx, text, x, y, align, color, size) {
        ctx.fillStyle = color; ctx.font = (size || 11) + 'px Arial'; ctx.textAlign = align || 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(text, x, y);
    }

    function animate(duration, draw) {
        var start = null;
        function frame(ts) {
            if (!start) start = ts;
            var p = Math.min(1, (ts - start) / duration);
            var ease = 1 - Math.pow(1 - p, 3);
            draw(ease);
            if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    function drawStatus(canvas) {
        var info = resizeCanvas(canvas), ctx = info.ctx, w = info.w, h = info.h, colors = themeColors();
        var chart = parseChart('sigetReportStatus');
        var data = chart && chart.data && Array.isArray(chart.data.datasets) && chart.data.datasets[0] ? chart.data.datasets[0].data.map(Number) : [];
        if (!data.length || data.every(function (v) { return !isFinite(v); })) data = statusFromTables();
        if (data.length !== 4) data = [0, 0, 0, 0];
        var names = ['PROGRAMADO', 'REPROGRAMADO', 'VALIDADO Y CERRADO', 'VENCIDO'];
        var max = Math.max(1, Math.max.apply(Math, data));
        var left = 145, right = 55, top = 28, bottom = 26, plotW = w - left - right, rowH = (h - top - bottom) / 4;
        ctx.clearRect(0, 0, w, h);
        ctx.strokeStyle = colors.grid; ctx.lineWidth = 1;
        for (var i = 0; i <= 4; i++) { var gx = left + plotW * i / 4; ctx.beginPath(); ctx.moveTo(gx, top); ctx.lineTo(gx, h - bottom); ctx.stroke(); label(ctx, String(Math.round(max * i / 4)), gx, h - 10, 'center', colors.base, 9); }
        var target = data.slice();
        animate(1200, function (p) {
            ctx.clearRect(0, 0, w, h);
            ctx.strokeStyle = colors.grid; ctx.lineWidth = 1;
            for (var j = 0; j <= 4; j++) { var x = left + plotW * j / 4; ctx.beginPath(); ctx.moveTo(x, top); ctx.lineTo(x, h - bottom); ctx.stroke(); label(ctx, String(Math.round(max * j / 4)), x, h - 10, 'center', colors.base, 9); }
            target.forEach(function (v, k) {
                var y = top + rowH * k + 8, bh = rowH - 16, bw = plotW * (v / max) * p;
                label(ctx, names[k], left - 10, y + bh / 2, 'right', colors.text, 10);
                ctx.fillStyle = colors.fills[k]; ctx.globalAlpha = .92; ctx.fillRect(left, y, Math.max(2, bw), bh); ctx.globalAlpha = 1;
                label(ctx, String(Math.round(v * p)), left + Math.max(6, bw) + 8, y + bh / 2, 'left', colors.text, 10);
            });
        });
    }

    function drawAgency(canvas) {
        var info = resizeCanvas(canvas), ctx = info.ctx, w = info.w, h = info.h, colors = themeColors();
        var chart = parseChart('sigetReportAgency');
        var rows = agenciesFromTable();
        var labels = chart && chart.data && chart.data.labels || [];
        var a = chart && chart.data && chart.data.datasets && chart.data.datasets[0] ? chart.data.datasets[0].data.map(Number) : [];
        var c = chart && chart.data && chart.data.datasets && chart.data.datasets[1] ? chart.data.datasets[1].data.map(Number) : [];
        if (rows.length) { labels = rows.map(function (r) { return r.name; }); a = rows.map(function (r) { return r.realized; }); c = rows.map(function (r) { return r.closed; }); }
        if (!labels.length) { labels = ['SIN DATOS']; a = [0]; c = [0]; }
        var max = Math.max(100, Math.max.apply(Math, a.concat(c).map(function (v) { return isFinite(v) ? v : 0; })));
        var left = Math.min(165, Math.max(115, w * .28)), right = 45, top = 24, bottom = 20, plotW = w - left - right, visible = Math.min(10, labels.length);
        labels = labels.slice(0, visible); a = a.slice(0, visible); c = c.slice(0, visible);
        var rowH = Math.max(28, (h - top - bottom) / Math.max(1, labels.length));
        ctx.clearRect(0,0,w,h);
        ctx.strokeStyle=colors.grid; ctx.lineWidth=1;
        for(var i=0;i<=4;i++){var x=left+plotW*i/4;ctx.beginPath();ctx.moveTo(x,top);ctx.lineTo(x,h-bottom);ctx.stroke();label(ctx,Math.round(max*i/4)+'%',x,h-8,'center',colors.base,9);}
        animate(1300,function(p){
            ctx.clearRect(0,0,w,h);
            ctx.strokeStyle=colors.grid;
            for(var j=0;j<=4;j++){var gx=left+plotW*j/4;ctx.beginPath();ctx.moveTo(gx,top);ctx.lineTo(gx,h-bottom);ctx.stroke();label(ctx,Math.round(max*j/4)+'%',gx,h-8,'center',colors.base,9);}
            labels.forEach(function(name,k){var y=top+k*rowH+5;label(ctx,name,left-8,y+17,'right',colors.text,9);ctx.fillStyle=colors.blue;ctx.globalAlpha=.9;ctx.fillRect(left,y,plotW*(Math.max(0,a[k]||0)/max)*p,12);ctx.globalAlpha=1;ctx.fillStyle=colors.green;ctx.globalAlpha=.9;ctx.fillRect(left,y+15,plotW*(Math.max(0,c[k]||0)/max)*p,12);ctx.globalAlpha=1;label(ctx,(Number(a[k]||0)).toFixed(1)+'%',left+plotW*(Math.max(0,a[k]||0)/max)*p+5,y+11,'left',colors.text,8);});
            label(ctx,'Avance realizado',left,16,'left',colors.blue,9); label(ctx,'Cierre validado',left+115,16,'left',colors.green,9);
        });
    }

    function drawTrend(canvas) {
        var info = resizeCanvas(canvas), ctx = info.ctx, w = info.w, h = info.h, colors = themeColors();
        var chart = parseChart('sigetReportTrend');
        var labels = chart && chart.data && chart.data.labels || [];
        var values = chart && chart.data && chart.data.datasets && chart.data.datasets[0] ? chart.data.datasets[0].data.map(Number) : [];
        if (!labels.length) labels = monthsFallback();
        if (!values.length) values = labels.map(function(){return 0;});
        var n=Math.min(labels.length,12); labels=labels.slice(-n); values=values.slice(-n);
        var left=42,right=18,top=18,bottom=35,plotW=w-left-right,plotH=h-top-bottom;
        var target=values.map(function(v){return isFinite(v)?Math.max(0,Math.min(100,v)):0;});
        animate(1400,function(p){
            ctx.clearRect(0,0,w,h);
            for(var g=0;g<=4;g++){var gy=top+plotH*g/4;ctx.strokeStyle=colors.grid;ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(left,gy);ctx.lineTo(w-right,gy);ctx.stroke();label(ctx,Math.round(100-g*25)+'%',left-8,gy,'right',colors.base,9);}
            var pts=[];
            for(var i=0;i<n;i++){var x=left+(n===1?plotW/2:plotW*i/(n-1));var y=top+plotH-(plotH*(target[i]*p/100));pts.push([x,y]);label(ctx,String(labels[i]).replace(/\s+/g,' '),x,h-15,'center',colors.base,8);}
            if(pts.length){ctx.strokeStyle=colors.green;ctx.lineWidth=3;ctx.beginPath();pts.forEach(function(pt,i){if(i===0)ctx.moveTo(pt[0],pt[1]);else ctx.lineTo(pt[0],pt[1]);});ctx.stroke();pts.forEach(function(pt){ctx.fillStyle=colors.green;ctx.beginPath();ctx.arc(pt[0],pt[1],4,0,Math.PI*2);ctx.fill();});}
        });
    }

    function drawAll() {
        var ids = ['sigetReportStatus','sigetReportAgency','sigetReportTrend'];
        if (window.Chart) ids.forEach(function(id){ var c=document.getElementById(id); if(c && window.Chart.getChart(c)) window.Chart.getChart(c).destroy(); });
        var s=document.getElementById(ids[0]), a=document.getElementById(ids[1]), t=document.getElementById(ids[2]);
        if(s) drawStatus(s); if(a) drawAgency(a); if(t) drawTrend(t);
    }

    function boot() {
        if (!document.getElementById('sigetReportStatus')) return;
        setTimeout(drawAll, 250);
        window.addEventListener('resize', function(){ setTimeout(drawAll, 100); });
        document.querySelectorAll('[data-theme-value]').forEach(function(el){el.addEventListener('click',function(){setTimeout(drawAll,150);});});
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
