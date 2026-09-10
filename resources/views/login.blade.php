<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión | DracoCert</title>

    {{-- Font Awesome non-render-blocking --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          media="print"
          onload="this.media='all'"
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
    <noscript>
        <link rel="stylesheet"
              href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
              crossorigin="anonymous">
    </noscript>

    {{-- CSS crítico inline para el skeleton del login --}}
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif}
    @keyframes sk-shine{
        0%{background-position:200% 0}100%{background-position:-200% 0}
    }
    .sk-shimmer{
        background:linear-gradient(90deg,rgba(255,255,255,.15) 25%,rgba(255,255,255,.3) 50%,rgba(255,255,255,.15) 75%);
        background-size:200% 100%;
        animation:sk-shine 1.4s infinite;
        border-radius:6px;
    }
    .login-skeleton-hero{
        min-height:100vh;display:flex;align-items:center;justify-content:center;
        background:linear-gradient(135deg,#ff8a00 0%,#ffb347 100%);
    }
    .login-skeleton-card{
        background:#fff;border-radius:18px;padding:40px 36px;
        box-shadow:0 8px 40px rgba(0,0,0,.18);
        width:100%;max-width:400px;
        display:flex;flex-direction:column;align-items:center;gap:16px;
    }
    </style>

    @if(session('status'))
    <script>window.loginStatus = @json(session('status'));</script>
    @endif

    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body>
<div id="app">
    {{-- Skeleton del login: invisible 200ms (rápido en localhost), visible en red lenta --}}
    <style>#login-skeleton{animation:sk-appear .15s ease .2s both}@keyframes sk-appear{from{opacity:0}to{opacity:1}}</style>
    <div class="login-skeleton-hero" id="login-skeleton">
        <div class="login-skeleton-card">
            {{-- Logo --}}
            <div style="width:80px;height:80px;border-radius:50%;background:#f0f0f0" class="sk-shimmer"></div>
            {{-- Título --}}
            <div style="height:20px;width:180px;background:#eee" class="sk-shimmer"></div>
            {{-- Email input --}}
            <div style="height:44px;width:100%;background:#eee;border-radius:8px" class="sk-shimmer"></div>
            {{-- Password input --}}
            <div style="height:44px;width:100%;background:#eee;border-radius:8px" class="sk-shimmer"></div>
            {{-- Botón --}}
            <div style="height:46px;width:100%;background:#ff8a00;border-radius:8px;opacity:.7" class="sk-shimmer"></div>
        </div>
    </div>
</div>
</body>
</html>
