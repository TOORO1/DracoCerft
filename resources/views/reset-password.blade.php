<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva contraseña — DracoCert</title>
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
            box-shadow: 0 8px 40px rgba(0,0,0,.18); width: 100%; max-width: 420px;
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
        .alert-custom { background:#fdecea; color:#c62828; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px; text-align:left; }
        .req-list { font-size: 12px; color: #aaa; text-align: left; margin: -8px 0 12px; padding-left: 4px; }
        .req-list li { list-style: none; }
    </style>
</head>
<body>
<div class="login-hero">
    <div class="login-card">
        <img src="/images/logo.png" alt="DracoCert" class="logo">
        <h5>NUEVA CONTRASEÑA</h5>
        <p class="subtitle">Elige una contraseña segura para tu cuenta.</p>

        @if($errors->has('token'))
            <div class="alert-custom">
                <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first('token') }}
                <br><a href="{{ route('password.forgot') }}" style="color:#c62828;font-weight:700">Solicitar nuevo enlace →</a>
            </div>
        @endif

        @if($errors->any() && !$errors->has('token'))
            <div class="alert-custom">
                <i class="fa fa-exclamation-circle me-1"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token"  value="{{ $token }}">
            <input type="hidden" name="correo" value="{{ $correo }}">

            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="Nueva contraseña"
                    required
                    autofocus
                />
            </div>
            <ul class="req-list">
                <li>• Mínimo 8 caracteres</li>
            </ul>

            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input
                    type="password"
                    name="password_confirmation"
                    class="form-control"
                    placeholder="Confirmar contraseña"
                    required
                />
            </div>

            <button class="btn btn-orange w-100" type="submit">
                <i class="fa fa-check me-2"></i> Guardar nueva contraseña
            </button>
        </form>

        <a href="{{ route('login') }}" class="small-link">
            <i class="fa fa-arrow-left me-1"></i> Volver al inicio de sesión
        </a>
    </div>
</div>
</body>
</html>
