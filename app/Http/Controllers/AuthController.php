<?php
// php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Usuario;
use App\Models\ActivityLog;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        Log::debug('Login attempt start', ['email' => $email, 'ip' => $request->ip()]);

        $user = Usuario::where('Correo', $email)->first();

        if (! $user) {
            ActivityLog::record('login_fallido', 'auth', "Intento fallido con correo: {$email}");
            return $request->expectsJson()
                ? response()->json(['error' => 'Usuario no encontrado'], 401)
                : redirect()->back()->withErrors(['email' => 'Usuario no encontrado'])->withInput();
        }

        if (! Hash::check($password, $user->password)) {
            ActivityLog::record('login_fallido', 'auth', "Contraseña incorrecta para: {$email}");
            return $request->expectsJson()
                ? response()->json(['error' => 'Contraseña inválida'], 401)
                : redirect()->back()->withErrors(['password' => 'Contraseña inválida'])->withInput();
        }

        // Generar token plano, guardar hash en BD (los middleware esperan SHA256)
        $plainToken = Str::random(60);
        $user->api_token = hash('sha256', $plainToken);
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('api_token', $plainToken);

        Log::info('Login exitoso', ['user_id' => $user->getKey(), 'via' => $request->expectsJson() ? 'api' : 'web']);
        ActivityLog::record('login', 'auth', "Inicio de sesión exitoso: {$user->Nombre_Usuario}");

        // ── 2FA: si está habilitado, pausar login y pedir código ──
        if ($user->google2fa_enabled) {
            // Deshacer la sesión de auth hasta que el código sea verificado
            Auth::logout();
            session(['2fa_user_id'  => $user->getKey()]);
            session(['2fa_api_token' => $plainToken]);

            if ($request->expectsJson()) {
                return response()->json(['2fa_required' => true], 200);
            }
            return redirect()->route('2fa.verify');
        }

        if ($request->expectsJson()) {
            return response()->json(['user' => $user, 'token' => $plainToken]);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        ActivityLog::record('logout', 'auth', 'Cierre de sesión');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
