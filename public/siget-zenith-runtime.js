(()=> {
  const palettes = {
    default: {primary:'#0f9d9a', foreground:'#ffffff', accent:'#e8f7f6', ring:'#0f9d9a'},
    ocean:   {primary:'#3577df', foreground:'#ffffff', accent:'#edf4ff', ring:'#3577df'},
    sunset:  {primary:'#d96b2b', foreground:'#ffffff', accent:'#fff2e9', ring:'#d96b2b'},
    forest:  {primary:'#3f8f68', foreground:'#ffffff', accent:'#edf8f1', ring:'#3f8f68'},
    berry:   {primary:'#9a4fa8', foreground:'#ffffff', accent:'#f8effa', ring:'#9a4fa8'},
    slate:   {primary:'#4b5563', foreground:'#ffffff', accent:'#f0f2f4', ring:'#64748b'}
  };
  const root=document.documentElement;
  const get=(k,d)=>localStorage.getItem(k)||d;
  const setAttr=(k,v)=>root.dataset[k]=v;
  const apply=()=>{
    const p=get('zenith-preset','default');
    const d=get('zenith-density','default');
    const c=get('zenith-container','fluid');
    const r=get('zenith-radius','default');
    setAttr('zenithPreset',p); setAttr('zenithDensity',d); setAttr('zenithContainer',c); setAttr('zenithRadius',r);
    const pal=palettes[p]||palettes.default;
    root.style.setProperty('--primary-direct',pal.primary);
    root.style.setProperty('--ring-direct',pal.ring);
    root.style.setProperty('--accent-direct',pal.accent);
    document.querySelectorAll('[data-zenith-preset]').forEach(x=>x.classList.toggle('active',x.dataset.zenithPreset===p));
    document.querySelectorAll('[data-zenith-density]').forEach(x=>x.classList.toggle('active',x.dataset.zenithDensity===d));
    document.querySelectorAll('[data-zenith-container]').forEach(x=>x.classList.toggle('active',x.dataset.zenithContainer===c));
    document.querySelectorAll('[data-zenith-radius]').forEach(x=>x.classList.toggle('active',x.dataset.zenithRadius===r));
    if(window.Chart?.getChart){
      document.querySelectorAll('canvas').forEach(cv=>{
        const ch=window.Chart.getChart(cv); if(!ch) return;
        (ch.data.datasets||[]).forEach((ds,i)=>{
          const colors=palettes[p] ? [palettes[p].primary,'#3b82f6','#15803d','#b45309','#b42318','#7c5ce7','#0ea5e9','#64748b'] : [];
          const col=colors[i%colors.length]||pal.primary;
          if(ds.type==='line'||ch.config.type==='line'){ds.borderColor=col;ds.backgroundColor=col;ds.pointBackgroundColor=col;}
          else {ds.backgroundColor=col;ds.borderColor=col;}
        });
        ch.update();
      });
    }
  };
  const open=()=>{
    const panel=document.querySelector('[data-zenith-customizer]');
    if(!panel) return;
    panel.classList.add('is-open'); panel.setAttribute('aria-hidden','false');
  };
  const close=()=>{
    const panel=document.querySelector('[data-zenith-customizer]');
    if(!panel) return;
    panel.classList.remove('is-open'); panel.setAttribute('aria-hidden','true');
  };
  const bind=()=>{
    apply();
    document.querySelector('[data-zenith-customizer-toggle]')?.addEventListener('click',open);
    document.querySelectorAll('[data-zenith-customizer-close]').forEach(x=>x.addEventListener('click',close));
    document.querySelectorAll('[data-zenith-preset]').forEach(x=>x.addEventListener('click',()=>{localStorage.setItem('zenith-preset',x.dataset.zenithPreset);apply();}));
    document.querySelectorAll('[data-zenith-density]').forEach(x=>x.addEventListener('click',()=>{localStorage.setItem('zenith-density',x.dataset.zenithDensity);apply();}));
    document.querySelectorAll('[data-zenith-container]').forEach(x=>x.addEventListener('click',()=>{localStorage.setItem('zenith-container',x.dataset.zenithContainer);apply();}));
    document.querySelectorAll('[data-zenith-radius]').forEach(x=>x.addEventListener('click',()=>{localStorage.setItem('zenith-radius',x.dataset.zenithRadius);apply();}));
    document.querySelector('[data-zenith-reset]')?.addEventListener('click',()=>{['zenith-preset','zenith-density','zenith-container','zenith-radius'].forEach(k=>localStorage.removeItem(k));apply();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
  };
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',bind,{once:true}); else bind();
})();