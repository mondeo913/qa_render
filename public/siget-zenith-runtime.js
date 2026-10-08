(()=> {
  const palettes = {
    default: {primary:'#0f7f7c', foreground:'#ffffff', accent:'#e8f7f6', ring:'#0f7f7c', success:'#3f7f55', warning:'#9b6a1d', danger:'#a94747', info:'#4d76b8', sidebar:'#eaf5f4'},
    ocean: {primary:'#3b6fc4', foreground:'#ffffff', accent:'#edf4ff', ring:'#3b6fc4', success:'#3f7f74', warning:'#9d7423', danger:'#a54d5a', info:'#4d76b8', sidebar:'#edf3fc'},
    sunset: {primary:'#b96531', foreground:'#ffffff', accent:'#fff1e9', ring:'#b96531', success:'#527854', warning:'#9a7025', danger:'#a95050', info:'#5d79a3', sidebar:'#fbf1eb'},
    forest: {primary:'#3f7d5a', foreground:'#ffffff', accent:'#edf7f0', ring:'#3f7d5a', success:'#3f7a55', warning:'#917026', danger:'#9f4d45', info:'#55759b', sidebar:'#edf5ef'},
    berry: {primary:'#87509a', foreground:'#ffffff', accent:'#f7eff9', ring:'#87509a', success:'#4c7d69', warning:'#96702b', danger:'#a24767', info:'#6474a8', sidebar:'#f7eff8'},
    slate: {primary:'#596474', foreground:'#ffffff', accent:'#eef1f4', ring:'#697688', success:'#5c7a69', warning:'#8c7636', danger:'#8b5a5d', info:'#64778e', sidebar:'#eef1f4'}
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
    root.style.setProperty('--primary',pal.primary);
    root.style.setProperty('--primary-foreground',pal.foreground);
    root.style.setProperty('--ring',pal.ring);
    root.style.setProperty('--accent',pal.accent);
    root.style.setProperty('--success',pal.success);
    root.style.setProperty('--warning',pal.warning);
    root.style.setProperty('--danger',pal.danger);
    root.style.setProperty('--info',pal.info);
    root.style.setProperty('--sidebar-active',pal.sidebar);
    document.querySelectorAll('[data-zenith-preset]').forEach(x=>x.classList.toggle('active',x.dataset.zenithPreset===p));
    document.querySelectorAll('[data-zenith-density]').forEach(x=>x.classList.toggle('active',x.dataset.zenithDensity===d));
    document.querySelectorAll('[data-zenith-container]').forEach(x=>x.classList.toggle('active',x.dataset.zenithContainer===c));
    document.querySelectorAll('[data-zenith-radius]').forEach(x=>x.classList.toggle('active',x.dataset.zenithRadius===r));
    if(window.Chart?.getChart){
      document.querySelectorAll('canvas').forEach(cv=>{
        const ch=window.Chart.getChart(cv); if(!ch) return;
        (ch.data.datasets||[]).forEach((ds,i)=>{
          const colors=palettes[p] ? [palettes[p].primary,palettes[p].info,palettes[p].success,palettes[p].warning,palettes[p].danger,palettes[p].primary,palettes[p].info,palettes[p].success] : [];
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