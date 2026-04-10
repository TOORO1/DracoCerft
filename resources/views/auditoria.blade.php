<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Auditoría | DracoCert</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    @viteReactRefresh
    @vite(['resources/js/Auditoria_Reporte.jsx'])
    <script>
        window.currentUser   = @json(optional(Auth::user())->Nombre_Usuario ?? 'Usuario');
        window.currentUserId = @json(optional(Auth::user())->getKey());
        window.currentRole   = @json(optional(Auth::user()->roles()->first())->Nombre_rol ?? 'Usuario');
    </script>
</head>
<body>
<div id="app"></div>
</body>
</html>
