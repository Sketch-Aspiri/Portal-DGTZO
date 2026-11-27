<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#06534D">
@php
    // Título propio de cada pantalla: la ranura "title" o el primer <h1> de la cabecera o del contenido.
    $tituloPagina = trim((string) ($title ?? ''));
    if ($tituloPagina === '' && preg_match('~<h1\b[^>]*>(.*?)</h1>~s', (string) ($header ?? '').(string) ($slot ?? ''), $coincidencia)) {
        $tituloPagina = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($coincidencia[1]), ENT_QUOTES | ENT_HTML5)));
    }
@endphp
<title>{{ $tituloPagina !== '' && $tituloPagina !== config('app.name') ? $tituloPagina.' · ' : '' }}{{ config('app.name', 'Portal') }}</title>
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Zona Oriente TM">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
@if (filled(config('webpush.vapid.public_key')))
    <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
@endif
@include('layouts.partials.tema')
<script src="{{ asset('js/accesibilidad-inicial.js') }}?v=1"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
