<!doctype html>
<html lang="es">
<head>
    @include('partials.page-head', ['title' => 'Dashboard', 'viteEntry' => 'resources/js/dashboard.jsx'])
</head>
<body>
@include('partials.page-data')
<div id="app">
    @include('partials.app-skeleton')
</div>
</body>
</html>
