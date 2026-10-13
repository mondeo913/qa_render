@php
    $manifestPath = public_path('build/manifest.json');
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
    $uiCss = $manifest['resources/css/app.scss']['file'] ?? null;
    $uiJs = $manifest['resources/js/app.js']['file'] ?? null;
@endphp
<script src="{{ asset('siget-zenith-runtime.js') }}?v={{ @filemtime(public_path('siget-zenith-runtime.js')) ?: '1' }}"></script>
@if($uiCss)<link rel="stylesheet" href="{{ '/build/'.ltrim($uiCss, '/') }}">
@else<link rel="stylesheet" href="{{ asset('siget-zenith-runtime.css') }}?v={{ @filemtime(public_path('siget-zenith-runtime.css')) ?: '1' }}">@endif
@if($uiJs)<script type="module" src="{{ '/build/'.ltrim($uiJs, '/') }}"></script>
@else<script defer src="{{ asset('report-executive-fallback.js') }}"></script>@endif
