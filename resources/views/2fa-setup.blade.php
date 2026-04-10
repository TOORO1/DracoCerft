<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/images/logo.png">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configurar 2FA — DracoCert</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/sass/app.scss'])
    <style>
        body { margin:0; font-family:'Segoe UI',sans-serif; background:#f4f6fb; }
        .setup-hero {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background:linear-gradient(135deg,#ff8a00 0%,#ffb347 100%); padding:24px;
        }
        .setup-card {
            background:#fff; border-radius:18px; padding:36px 32px;
            box-shadow:0 8px 40px rgba(0,0,0,.18); width:100%; max-width:520px;
        }
        .setup-card .logo { width:64px; display:block; margin:0 auto 12px; }
        h4 { font-weight:800; color:#333; text-align:center; font-size:18px; margin-bottom:4px; }
        .subtitle { color:#888; font-size:13px; text-align:center; margin-bottom:28px; }
        .step { display:flex; gap:14px; align-items:flex-start; margin-bottom:20px; }
        .step-num {
            width:32px; height:32px; border-radius:50%; background:#ff8a00; color:#fff;
            font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center;
            flex-shrink:0; margin-top:2px;
        }
        .step-text strong { font-size:14px; color:#333; display:block; }
        .step-text span { font-size:12px; color:#888; }
        .qr-box {
            border:2px dashed #ff8a00; border-radius:12px; padding:20px;
            text-align:center; background:#fff8f0; margin:20px 0;
        }
        .qr-box svg { max-width:180px; height:auto; }
        .secret-box {
            background:#f5f5f5; border-radius:8px; padding:10px 14px;
            font-family:monospace; font-size:15px; letter-spacing:3px;
            text-align:center; color:#333; margin:8px 0 20px; word-break:break-all;
        }
        .code-input {
            text-align:center; font-size:28px; font-weight:800; letter-spacing:10px;
            border:2px solid #ff8a00; border-radius:10px; padding:12px;
            color:#333; width:100%;
        }
        .code-input:focus { outline:none; box-shadow:0 0 0 3px rgba(255,138,0,.2); }
        .btn-orange { background:#ff8a00; color:#fff; border:none; border-radius:8px;
            font-weight:700; padding:12px; font-size:15px; width:100%; margin-top:14px; }
        .btn-orange:hover { background:#e07800; }
        .btn-back { background:#f5f5f5; color:#666; border:none; border-radius:8px;
            font-weight:600; padding:10px; font-size:14px; width:100%; margin-top:8px; }
        .alert-custom { background:#fdecea; color:#c62828; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px; }
        .hint { font-size:12px; color:#aaa; text-align:center; margin-top:8px; }
    </style>
</head>
<body>
<div class="setup-hero">
    <div class="setup-card">
        <img src="/images/logo.png" alt="DracoCert" class="logo">
        <h4><i class="fa fa-shield-halved" style="color:#ff8a00;margin-right:8px;"></i>Configurar Autenticación 2FA</h4>
        <p class="subtitle">Protege tu cuenta con Google Authenticator</p>

        @if($errors->has('code'))
            <div class="alert-custom">
                <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first('code') }}
            </div>
        @endif

        {{-- Paso 1 --}}
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-text">
                <strong>Descarga Google Authenticator</strong>
                <span>Disponible en App Store y Google Play</span>
            </div>
        </div>

        {{-- Paso 2: QR --}}
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-text" style="width:100%">
                <strong>Escanea este código QR con la app</strong>
                <span>O ingresa la clave manualmente</span>
            </div>
        </div>

        <div class="qr-box">
            {!! $qrSvg !!}
            <p style="font-size:12px;color:#888;margin:8px 0 4px;">Clave manual:</p>
            <div class="secret-box">{{ chunk_split($secret, 4, ' ') }}</div>
        </div>

        {{-- Paso 3: Verificar --}}
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-text">
                <strong>Ingresa el código de 6 dígitos para confirmar</strong>
                <span>Esto activa la protección en tu cuenta</span>
            </div>
        </div>

        <form method="POST" action="{{ route('2fa.enable') }}">
            @csrf
            <input
                type="text"
                name="code"
                class="code-input"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                autofocus
                required
            />
            <p class="hint">El código cambia cada 30 segundos</p>
            <button class="btn btn-orange" type="submit">
                <i class="fa fa-lock me-2"></i> Activar autenticación de dos factores
            </button>
        </form>

        <form method="POST" action="{{ route('dashboard') }}" style="margin-top:0;">
            <a href="{{ route('dashboard') }}" class="btn btn-back d-block text-center text-decoration-none mt-2">
                Cancelar
            </a>
        </form>
    </div>
</div>
</body>
</html>
