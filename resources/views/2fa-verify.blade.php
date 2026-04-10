<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verificación 2FA — DracoCert</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/sass/app.scss'])
    <style>
        body { margin:0; font-family:'Segoe UI',sans-serif; background:#f4f6fb; }
        .login-hero {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#ff8a00 0%,#ffb347 100%);
        }
        .login-card {
            background:#fff; border-radius:18px; padding:40px 36px;
            box-shadow:0 8px 40px rgba(0,0,0,.18); width:100%; max-width:400px; text-align:center;
        }
        .login-card .logo { width:80px; margin-bottom:16px; }
        .login-card h5 { font-weight:800; color:#333; margin-bottom:6px; font-size:17px; }
        .login-card .subtitle { font-size:13px; color:#888; margin-bottom:24px; line-height:1.5; }
        .shield-icon { font-size:48px; color:#ff8a00; margin-bottom:12px; }
        .code-input {
            text-align:center; font-size:28px; font-weight:800; letter-spacing:10px;
            border:2px solid #ff8a00; border-radius:10px; padding:12px;
            color:#333; width:100%;
        }
        .code-input:focus { outline:none; box-shadow:0 0 0 3px rgba(255,138,0,.2); }
        .btn-orange { background:#ff8a00; color:#fff; border:none; border-radius:8px;
            font-weight:700; padding:12px; font-size:15px; transition:background .2s; width:100%; margin-top:16px; }
        .btn-orange:hover { background:#e07800; }
        .small-link { display:block; margin-top:18px; font-size:13px; color:#ff8a00; text-decoration:none; }
        .small-link:hover { text-decoration:underline; }
        .alert-custom { background:#fdecea; color:#c62828; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px; text-align:left; }
        .hint { font-size:12px; color:#aaa; margin-top:8px; }
    </style>
</head>
<body>
<div class="login-hero">
    <div class="login-card">
        <img src="/images/logo.png" alt="DracoCert" class="logo">
        <div class="shield-icon"><i class="fa fa-shield-halved"></i></div>
        <h5>VERIFICACIÓN EN DOS PASOS</h5>
        <p class="subtitle">
            Abre <strong>Google Authenticator</strong> en tu dispositivo<br>
            e ingresa el código de 6 dígitos.
        </p>

        @if($errors->has('code'))
            <div class="alert-custom">
                <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first('code') }}
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.verify.post') }}">
            @csrf
            <input
                type="text"
                name="code"
                class="code-input"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                autocomplete="one-time-code"
                autofocus
                required
            />
            <p class="hint">El código cambia cada 30 segundos</p>
            <button class="btn btn-orange" type="submit">
                <i class="fa fa-check me-2"></i> Verificar código
            </button>
        </form>

        <a href="{{ route('login') }}" class="small-link">
            <i class="fa fa-arrow-left me-1"></i> Volver al inicio de sesión
        </a>
    </div>
</div>
</body>
</html>
