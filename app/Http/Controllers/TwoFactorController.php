<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Models\ActivityLog;

class TwoFactorController extends Controller
{
    /* Estado 2FA del usuario autenticado (para el Header) */
    public function status()
    {
        $user = Auth::user();
        return response()->json([
            'enabled' => (bool) $user->google2fa_enabled,
        ]);
    }

    /* ─────────────────────────────────────────────────────────────
     |  Mostrar página de configuración 2FA (con QR)
     ───────────────────────────────────────────────────────────── */
    public function showSetup()
    {
        $user   = Auth::user();
        $google = app('pragmarx.google2fa');

        // Generar secreto temporal (aún no se guarda hasta confirmar)
        if (!session('2fa_temp_secret')) {
            session(['2fa_temp_secret' => $google->generateSecretKey()]);
        }

        $secret  = session('2fa_temp_secret');
        $appName = config('app.name', 'DracoCert');
        $qrUrl   = $google->getQRCodeUrl($appName, $user->Correo, $secret);

        // Generar SVG del QR
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrSvg  = $writer->writeString($qrUrl);

        return view('2fa-setup', compact('secret', 'qrSvg', 'user'));
    }

    /* ─────────────────────────────────────────────────────────────
     |  Activar 2FA: verificar primer código y guardar secreto
     ───────────────────────────────────────────────────────────── */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ], ['code.required' => 'Ingresa el código de 6 dígitos.', 'code.digits' => 'El código debe tener 6 dígitos.']);

        $secret = session('2fa_temp_secret');
        if (!$secret) {
            return back()->withErrors(['code' => 'Sesión expirada. Inicia el proceso nuevamente.']);
        }

        $google = app('pragmarx.google2fa');
        $valid  = $google->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Código incorrecto. Verifica que la hora de tu dispositivo esté sincronizada.']);
        }

        $user = Auth::user();
        $user->google2fa_secret  = $secret;
        $user->google2fa_enabled = true;
        $user->save();

        session()->forget('2fa_temp_secret');

        ActivityLog::record('2fa_activado', 'auth', "2FA activado para {$user->Nombre_Usuario}");

        return redirect()->route('dashboard')->with('2fa_status', '✅ Autenticación de dos factores activada correctamente.');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Desactivar 2FA
     ───────────────────────────────────────────────────────────── */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ], ['password.required' => 'Confirma tu contraseña para desactivar 2FA.']);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.']);
        }

        $user->google2fa_secret  = null;
        $user->google2fa_enabled = false;
        $user->save();

        ActivityLog::record('2fa_desactivado', 'auth', "2FA desactivado para {$user->Nombre_Usuario}");

        return redirect()->route('dashboard')->with('2fa_status', '2FA desactivado.');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Mostrar formulario de verificación 2FA (durante el login)
     ───────────────────────────────────────────────────────────── */
    public function showVerify()
    {
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }
        return view('2fa-verify');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Verificar código 2FA durante el login
     ───────────────────────────────────────────────────────────── */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ], ['code.required' => 'Ingresa el código.', 'code.digits' => 'El código debe tener 6 dígitos.']);

        $userId = session('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')->withErrors(['general' => 'Sesión expirada.']);
        }

        $user = \App\Models\Usuario::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $google = app('pragmarx.google2fa');
        $valid  = $google->verifyKey($user->google2fa_secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'Código incorrecto o expirado. Intenta de nuevo.']);
        }

        // Completar el login
        Auth::login($user);
        $request->session()->regenerate();
        session()->forget('2fa_user_id');

        ActivityLog::record('login_2fa', 'auth', "Login con 2FA exitoso: {$user->Nombre_Usuario}");

        return redirect()->route('dashboard');
    }
}
