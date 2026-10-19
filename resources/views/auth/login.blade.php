<!doctype html>
<html lang="es-MX" data-siget-theme="dark" data-siget-user="guest">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#041021">
  <meta name="color-scheme" content="dark light">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="SIGET: Sistema de Gestión de Evidencias de Transmisión. Acceso institucional.">
  <title>Ingresar · {{ config('siget.name') }}</title>
  @include('partials.ui-assets')
  <link rel="stylesheet" href="{{ asset('siget-login.css') }}?v={{ @filemtime(public_path('siget-login.css')) ?: '1' }}">
  <script defer src="{{ asset('siget-login.js') }}?v={{ @filemtime(public_path('siget-login.js')) ?: '1' }}"></script>
</head>
<body class="siget-body siget-zenith siget-login" data-siget-ui="zenith">
<div class="sx-scene">
  <div class="sx-grid" aria-hidden="true"></div>
  <div class="sx-orbit" aria-hidden="true">
    <svg viewBox="0 0 720 720" role="presentation" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <radialGradient id="sx-halo"><stop stop-color="#0bc5df" stop-opacity=".22"/><stop offset=".62" stop-color="#1775c4" stop-opacity=".06"/><stop offset="1" stop-color="#031022" stop-opacity="0"/></radialGradient>
        <linearGradient id="sx-line" x1="65" y1="50" x2="675" y2="670"><stop stop-color="#207ad0" stop-opacity=".08"/><stop offset=".55" stop-color="#2cefeb" stop-opacity=".94"/><stop offset="1" stop-color="#438af7" stop-opacity=".12"/></linearGradient>
        <linearGradient id="sx-core" x1="330" y1="290" x2="420" y2="436"><stop stop-color="#2778c9"/><stop offset="1" stop-color="#052b58"/></linearGradient>
        <filter id="sx-glow"><feGaussianBlur stdDeviation="5"/></filter>
      </defs>
      <circle cx="360" cy="360" r="354" fill="url(#sx-halo)"/>
      <g class="sx-ring-one">
        <circle cx="360" cy="360" r="318" fill="none" stroke="#147aba" stroke-opacity=".28" stroke-width="1" stroke-dasharray="4 9"/>
        <circle cx="360" cy="360" r="271" fill="none" stroke="#2984cf" stroke-opacity=".24" stroke-width="1"/>
        <path d="M101 273A271 271 0 0 1 612 258" fill="none" stroke="url(#sx-line)" stroke-width="2"/>
        <circle cx="600" cy="235" r="10" fill="#35edef" opacity=".24" filter="url(#sx-glow)"/>
        <circle cx="600" cy="235" r="7" fill="#36eeee"/>
        <circle cx="107" cy="433" r="8" fill="#65bffc"/>
        <circle cx="546" cy="554" r="5" fill="#51cdff"/>
      </g>
      <g class="sx-ring-two">
        <circle cx="360" cy="360" r="222" fill="none" stroke="#178fc6" stroke-opacity=".38" stroke-width="1"/>
        <circle cx="360" cy="360" r="185" fill="none" stroke="#2a9fce" stroke-opacity=".32" stroke-width="1" stroke-dasharray="3 8"/>
        <circle cx="360" cy="360" r="145" fill="none" stroke="#1aa6dc" stroke-opacity=".65" stroke-width="1.5"/>
        <path d="M360 215A145 145 0 0 1 499 318" fill="none" stroke="#48f3ed" stroke-width="2.8" stroke-linecap="round"/>
        <path d="M235 432A145 145 0 0 1 233 290" fill="none" stroke="#54b7ff" stroke-width="2"/>
        <circle cx="360" cy="215" r="8" fill="#40efec"/><circle cx="499" cy="318" r="6" fill="#54e7f6"/>
        <circle cx="233" cy="290" r="7" fill="#7bc8ff"/><circle cx="427" cy="487" r="7" fill="#39eae7"/>
      </g>
      <path d="M360 43V677M43 360H677" stroke="#218ab6" stroke-opacity=".22" stroke-dasharray="5 9"/>
      <circle cx="360" cy="360" r="87" fill="none" stroke="#33e8e9" stroke-opacity=".72"/>
      <circle cx="360" cy="360" r="71" fill="#04152c" stroke="#225b98" stroke-opacity=".55"/>
      <circle cx="360" cy="360" r="54" fill="url(#sx-core)"/>
      <text x="360" y="378" font-size="58" font-family="Arial,sans-serif" font-weight="800" fill="#f0fbff" text-anchor="middle">S</text>
    </svg>
  </div>
  <main class="sx-layout">
    <section class="sx-hero" aria-labelledby="sx-title">
      <div class="sx-brand">
        <span class="sx-mark" aria-hidden="true">S</span>
        <div class="sx-wordmark"><strong>{{ config('siget.name') }}</strong><small>{{ config('siget.subtitle') }}</small></div>
      </div>
      <div class="sx-hero-body">
        <h1 id="sx-title">Cada evidencia.<span>Bajo control.</span></h1>
        <p class="sx-tagline">La evidencia en el centro de cada decisión.</p>
        <div class="sx-accent" aria-hidden="true"></div>
        <p class="sx-description">Administre pautas, calendario inteligente, evidencias, revisión institucional y cierre desde una sola plataforma.</p>
        <div class="sx-feature">
          <span class="sx-feature-icon" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 27h22"/><rect x="7" y="19" width="4" height="6" rx="1"/><rect x="14" y="12" width="4" height="13" rx="1"/><rect x="21" y="6" width="4" height="19" rx="1"/></svg>
          </span>
          <span><strong>Trazabilidad operativa</strong><small>Registro completo de acciones, auditoría y seguimiento.</small></span>
        </div>
      </div>
      <p class="sx-hero-footer">SIGET · Acceso institucional</p>
    </section>
    <section class="sx-auth" aria-labelledby="sx-login-title">
      <div class="sx-card">
        <header class="sx-card-header">
          <div class="sx-card-brand">
            <span class="sx-mark sx-mark-sm" aria-hidden="true">S</span>
            <div><strong>{{ config('siget.name') }}</strong><small>{{ config('siget.subtitle') }}</small></div>
          </div>
          <button id="sxTheme" class="sx-theme" type="button" aria-label="Cambiar apariencia" title="Cambiar apariencia">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 16A9 9 0 0 1 8 4a9 9 0 1 0 12 12Z"/><path d="M17 4v4m-2-2h4"/></svg>
          </button>
        </header>
        <div class="sx-card-content">
          <h2 id="sx-login-title">Bienvenido</h2>
          <p class="sx-subtitle">Ingrese con su cuenta institucional.</p>
          @if(session('status'))<div class="sx-message" role="status">{{ session('status') }}</div>@endif
          @if($errors->any())<div class="sx-message sx-message-error" role="alert">{{ $errors->first() }}</div>@endif
          <form id="sxLogin" method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="sx-field">
              <label for="sxEmail">Correo institucional</label>
              <div class="sx-input">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="7" r="3.6"/><path d="M4 21v-2c0-4 3.3-6.6 8-6.6s8 2.6 8 6.6v2"/></svg>
                <input id="sxEmail" name="email" type="email" inputmode="email" autocomplete="username" spellcheck="false" placeholder="usuario@institucion.gob.mx" value="{{ old('email') }}" maxlength="254" required autofocus @error('email') aria-invalid="true" @enderror>
              </div>
            </div>
            <div class="sx-field">
              <div class="sx-label-row"><label for="sxPassword">Contraseña</label><a href="{{ route('password.request') }}">¿Olvidó su contraseña?</a></div>
              <div class="sx-input">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4.5" y="10" width="15" height="11" rx="2"/><path d="M7.5 10V7a4.5 4.5 0 0 1 9 0v3"/></svg>
                <input id="sxPassword" name="password" type="password" autocomplete="current-password" placeholder="Ingrese su contraseña" required @error('password') aria-invalid="true" @enderror>
                <button id="sxReveal" class="sx-reveal" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                  <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.4 12s3.7-6.1 9.6-6.1 9.6 6.1 9.6 6.1-3.7 6.1-9.6 6.1S2.4 12 2.4 12Z"/><circle cx="12" cy="12" r="3.1"/></svg>
                </button>
              </div>
            </div>
            <label class="sx-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Mantener sesión</span></label>
            <button id="sxSubmit" class="sx-submit" type="submit">
              <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 6V5h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8v-1"/><path d="M3 12h12m-4-4 4 4-4 4"/></svg>
              <span id="sxSubmitLabel">Ingresar a SIGET</span>
              <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
            </button>
          </form>
        </div>
        <footer class="sx-card-footer">
          <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <span>El acceso y las operaciones relevantes quedan registrados.</span>
        </footer>
      </div>
    </section>
  </main>
</div>
</body>
</html>