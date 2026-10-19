/* SIGET: estética de login sin cambiar el controlador ni el protocolo Laravel. */
(() => {
  'use strict';
  const init = () => {
    const form = document.getElementById('sxLogin');
    if (!form) return;
    const password = document.getElementById('sxPassword');
    const reveal = document.getElementById('sxReveal');
    const theme = document.getElementById('sxTheme');
    const submit = document.getElementById('sxSubmit');
    const label = document.getElementById('sxSubmitLabel');

    reveal?.addEventListener('click', () => {
      const visible = password.type === 'password';
      password.type = visible ? 'text' : 'password';
      reveal.setAttribute('aria-pressed', String(visible));
      reveal.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
      password.focus({ preventScroll: true });
    });
    theme?.addEventListener('click', () => {
      const light = document.documentElement.dataset.bsTheme === 'light';
      window.SigetAppearance?.set('theme', light ? 'dark' : 'light');
    });
    const syncTheme = () => {
      const light = document.documentElement.dataset.bsTheme === 'light';
      theme?.setAttribute('aria-label', light ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');
    };
    document.addEventListener('siget:appearance', syncTheme);
    syncTheme();

    form.addEventListener('submit', e => {
      if (!form.checkValidity()) return;
      if (submit.disabled) { e.preventDefault(); return; }
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      label.textContent = 'Verificando acceso…';
    });
    // El navegador puede restaurar la página desde bfcache tras regresar.
    window.addEventListener('pageshow', () => {
      submit.disabled = false;
      submit.removeAttribute('aria-busy');
      label.textContent = 'Ingresar a SIGET';
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();