{{--
    Partial: partials/page-head.blade.php
    Uso: @include('partials.page-head', ['title' => 'Título', 'viteEntry' => 'resources/js/xxx.jsx'])
    Variables opcionales: $extraHead (HTML adicional para el <head>)
--}}
<meta charset="utf-8">
<link rel="icon" type="image/png" href="/images/logo.png">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'DracoCert' }} | DracoCert</title>

{{-- Preconexión a CDNs usados — elimina latencia de DNS+TCP+TLS en primer uso --}}
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
<link rel="dns-prefetch" href="https://res.cloudinary.com">

{{--
    Font Awesome NON-RENDER-BLOCKING:
    Cargamos con media="print" → el navegador lo descarga sin bloquear el render.
    onload lo cambia a media="all" una vez descargado.
    <noscript> como fallback para JS desactivado.
--}}
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
      media="print"
      onload="this.media='all'"
      crossorigin="anonymous"
      referrerpolicy="no-referrer">
<noscript>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
</noscript>

{{-- CSS crítico inline: renderiza skeleton visible ANTES de que cargue cualquier archivo externo --}}
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f4f6fb}
#app{min-height:100vh}
/* Skeleton shimmer */
@keyframes sk-shine{
    0%{background-position:200% 0}
    100%{background-position:-200% 0}
}
/* sk-appear: el skeleton empieza invisible y aparece tras 200ms.
   En localhost (rápido), React monta antes y el skeleton nunca se ve.
   En red lenta, aparece suavemente para evitar pantalla en blanco. */
@keyframes sk-appear{from{opacity:0}to{opacity:1}}
#app-skeleton{
    animation:sk-appear .15s ease .2s both; /* delay 200ms, fill:both = invisible antes */
}
.sk-shimmer{
    background:linear-gradient(90deg,#ececec 25%,#f5f5f5 50%,#ececec 75%);
    background-size:200% 100%;
    animation:sk-shine 1.4s infinite;
    border-radius:6px;
}
</style>

@if(isset($extraHead))
    {!! $extraHead !!}
@endif

@viteReactRefresh
@vite([$viteEntry ?? 'resources/js/app.jsx'])
