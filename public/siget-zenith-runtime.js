/* Single owner of SIGET appearance and shell interactions, with or without Vite. */
(() => {
  const root = document.documentElement;
  const prefix = `siget-ui:${root.dataset.sigetUser || 'guest'}:`;
  const allowed = {
    theme: ['light', 'dark', 'auto'], preset: ['default', 'ocean', 'sunset', 'forest', 'berry', 'slate'],
    density: ['compact', 'default', 'comfortable'], container: ['fluid', 'boxed'],
    radius: ['compact', 'default', 'soft'], layout: ['sidebar', 'topnav'], fileview: ['grid', 'list'], collapsed: ['0', '1']
  };
  const defaults = { theme: root.dataset.sigetTheme || 'auto', preset: 'default', density: 'default', container: 'fluid', radius: 'default', layout: 'sidebar', fileview: 'grid', collapsed: '0' };
  const read = kind => { try { const v = localStorage.getItem(prefix + kind); return allowed[kind].includes(v) ? v : defaults[kind]; } catch { return defaults[kind]; } };
  const state = Object.fromEntries(Object.keys(allowed).map(kind => [kind, read(kind)]));
  const media = matchMedia('(prefers-color-scheme: dark)');
  const colors = { default: '#2563eb', ocean: '#2563eb', sunset: '#ea580c', forest: '#059669', berry: '#7c3aed', slate: '#64748b' };
  const apply = () => {
    root.dataset.sigetTheme = state.theme;
    root.dataset.bsTheme = state.theme === 'auto' ? (media.matches ? 'dark' : 'light') : state.theme;
    for (const kind of ['preset', 'density', 'container', 'radius', 'layout']) root.dataset['zenith' + kind[0].toUpperCase() + kind.slice(1)] = state[kind];
    document.body?.classList.toggle('siget-sidebar-collapsed', state.collapsed === '1');
    document.querySelectorAll('[data-theme-value]').forEach(el => { const active = el.dataset.themeValue === state.theme; el.classList.toggle('active', active); el.setAttribute('aria-pressed', String(active)); });
    for (const kind of ['preset', 'density', 'container', 'radius', 'layout']) document.querySelectorAll(`[data-zenith-${kind}]`).forEach(el => { const active = el.dataset['zenith' + kind[0].toUpperCase() + kind.slice(1)] === state[kind]; el.classList.toggle('active', active); el.setAttribute('aria-pressed', String(active)); });
    document.querySelectorAll('[data-theme-icon]').forEach(el => { el.className = `bi ${state.theme === 'light' ? 'bi-sun' : state.theme === 'dark' ? 'bi-moon-stars' : 'bi-circle-half'}`; });
    document.querySelectorAll('[data-siget-sidebar-toggle]').forEach(el => el.setAttribute('aria-expanded', String(matchMedia('(max-width: 991px)').matches ? document.body?.classList.contains('siget-sidebar-open') : state.collapsed !== '1')));
    document.dispatchEvent(new CustomEvent('siget:appearance', { detail: { ...state, resolvedTheme: root.dataset.bsTheme } }));
  };
  const set = (kind, value) => {
    if (!allowed[kind]?.includes(value)) return;
    state[kind] = value;
    try { localStorage.setItem(prefix + kind, value); } catch { /* Private browsing must not break controls. */ }
    apply();
  };
  window.SigetAppearance = { get: kind => state[kind], set, palette: () => [colors[state.preset], '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#a855f7', '#0ea5e9', '#64748b'] };
  apply();
  media.addEventListener('change', () => { if (state.theme === 'auto') apply(); });
  const bind = () => {
    const customizer = document.querySelector('[data-zenith-customizer]');
    const trigger = document.querySelector('[data-zenith-customizer-toggle]');
    let previousFocus;
    const togglePanel = open => {
      if (!customizer) return;
      customizer.classList.toggle('is-open', open);
      customizer.setAttribute('aria-hidden', String(!open));
      customizer.inert = !open;
      trigger?.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('siget-customizer-open', open);
      if (open) { previousFocus = document.activeElement; customizer.querySelector('button[data-zenith-customizer-close]')?.focus(); }
      else previousFocus?.focus();
    };
    trigger?.addEventListener('click', () => togglePanel(!customizer?.classList.contains('is-open')));
    document.querySelectorAll('[data-zenith-customizer-close]').forEach(el => el.addEventListener('click', () => togglePanel(false)));
    document.querySelectorAll('[data-theme-value]').forEach(el => el.addEventListener('click', () => set('theme', el.dataset.themeValue)));
    for (const kind of ['preset', 'density', 'container', 'radius', 'layout']) document.querySelectorAll(`[data-zenith-${kind}]`).forEach(el => el.addEventListener('click', () => set(kind, el.dataset['zenith' + kind[0].toUpperCase() + kind.slice(1)])));
    document.querySelector('[data-zenith-reset]')?.addEventListener('click', () => { Object.assign(state, defaults); for (const kind of Object.keys(allowed)) { try { localStorage.removeItem(prefix + kind); } catch {} } apply(); });
    document.querySelectorAll('[data-siget-sidebar-toggle]').forEach(el => el.addEventListener('click', () => {
      if (matchMedia('(max-width: 991px)').matches) { document.body.classList.toggle('siget-sidebar-open'); apply(); }
      else set('collapsed', state.collapsed === '1' ? '0' : '1');
    }));
    const closeSidebar = () => { document.body.classList.remove('siget-sidebar-open'); apply(); };
    document.querySelectorAll('[data-siget-sidebar-close]').forEach(el => el.addEventListener('click', closeSidebar));
    document.querySelectorAll('.siget-nav a').forEach(el => el.addEventListener('click', closeSidebar));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') { if (customizer?.classList.contains('is-open')) togglePanel(false); closeSidebar(); }
      if (e.key === 'Tab' && customizer?.classList.contains('is-open')) {
        const items = [...customizer.querySelectorAll('button, a, input, select')].filter(el => !el.disabled && el.getClientRects().length);
        const first = items[0], last = items.at(-1);
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus(); }
        if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
      }
    });
    apply();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, { once: true }); else bind();
})();
