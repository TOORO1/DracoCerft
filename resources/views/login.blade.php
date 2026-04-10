<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bienvenido a DracoCerf</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @if(session('status'))
    <script>window.loginStatus = @json(session('status'));</script>
    @endif

    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body>
<div id="app"></div>
</body>
</html>
