<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recuperar contraseña — DracoCert</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/sass/app.scss'])
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #f4f6fb; }
        .login-hero {
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; background: linear-gradient(135deg,#ff8a00 0%,#ffb347 100%);
        }
        .login-card {
            background: #fff; border-radius: 18px; padding: 40px 36px;
            box-shadow: 0 8px 40px rgba(0,0,0,.18); width: 100%; max-width: 400px;
            text-align: center;
        }
        .login-card .logo { width: 80px; margin-bottom: 16px; }
        .login-card h5 { font-weight: 800; color: #333; margin-bottom: 6px; font-size: 17px; letter-spacing: .5px; }
        .login-card .subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }
        .input-group-text { background: #fff8f0; border-right: none; color: #ff8a00; }
        .form-control { border-left: none; }
        .form-control:focus { box-shadow: none; border-color: #ff8a00; }
        .btn-orange { background: #ff8a00; color: #fff; border: none; border-radius: 8px;
            font-weight: 700; padding: 10px; font-size: 15px; transition: background .2s; }
        .btn-orange:hover { background: #e07800; color: #fff; }
        .small-link { display: block; margin-top: 18px; font-size: 13px; color: #ff8a00;
            text-decoration: none; }
        .small-link:hover { text-decoration: underline; }
        .alert-success-custom { background:#e8f5e9; color:#2e7d32; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px; text-align:left; }
        .alert-custom { background:#fdecea; color:#c62828; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px; text-align:left; }
    </style>
</head>
<body>
<div class="login-hero">
    <div class="login-card">
        <img src="/images/logo.png" alt="DracoCert" class="logo">
        <h5>RECUPERAR CONTRASEÑA</h5>
        <p class="subtitle">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p>

        @if(session('status'))
            <div class="alert-success-custom">
                <i class="fa fa-check-circle me-1"></i> {{ session('status') }}
            </div>
        @endif

        @if($errors->has('correo'))
            <div class="alert-custom">
                <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first('correo') }}
            </div>
        @endif

        @if(!session('status'))
        <form method="POST" action="{{ route('password.send') }}">
            @csrf
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                <input
                    type="email"
                    name="correo"
                    class="form-control @error('correo') is-invalid @enderror"
                    placeholder="Tu correo registrado"
                    value="{{ old('correo') }}"
                    autofocus
                    required
                />
            </div>
            <button class="btn btn-orange w-100" type="submit">
                <i class="fa fa-paper-plane me-2"></i> Enviar enlace de recuperación
            </button>
        </form>
        @endif

        <a href="{{ route('login') }}" class="small-link">
            <i class="fa fa-arrow-left me-1"></i> Volver al inicio de sesión
        </a>
    </div>
</div>
</body>
</html>
