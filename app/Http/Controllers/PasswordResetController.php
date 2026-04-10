<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\PasswordResetMail;

class PasswordResetController extends Controller
{
    private const EXPIRA_MINUTOS = 60;

    /* ─────────────────────────────────────────────────────────────
     |  1. Mostrar formulario "Olvidé mi contraseña"
     ───────────────────────────────────────────────────────────── */
    public function showForgotForm()
    {
        return view('forgot-password');
    }

    /* ─────────────────────────────────────────────────────────────
     |  2. Procesar email y enviar enlace de reset
     ───────────────────────────────────────────────────────────── */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
        ], [
            'correo.required' => 'El correo es obligatorio.',
            'correo.email'    => 'Ingresa un correo válido.',
        ]);

        $correo  = strtolower(trim($request->correo));
        $usuario = DB::table('usuario')
            ->whereRaw('LOWER(Correo) = ?', [$correo])
            ->select('idUsuario', 'Nombre_Usuario', 'Correo')
            ->first();

        // Siempre mostramos éxito para no revelar si el correo existe (seguridad)
        if (!$usuario) {
            return back()->with('status', 'Si ese correo está registrado, recibirás un enlace en breve.');
        }

        // Eliminar tokens anteriores del mismo correo
        DB::table('password_reset_tokens')->where('correo', $correo)->delete();

        // Generar token seguro y hashearlo
        $token     = Str::random(64);
        $tokenHash = Hash::make($token);

        DB::table('password_reset_tokens')->insert([
            'correo'     => $correo,
            'token'      => $tokenHash,
            'created_at' => now(),
        ]);

        $resetUrl = url("/password/reset/{$token}?correo=" . urlencode($correo));

        try {
            Mail::to($usuario->Correo)->send(
                new PasswordResetMail($resetUrl, $usuario->Nombre_Usuario, self::EXPIRA_MINUTOS)
            );
        } catch (\Exception $e) {
            Log::error('PasswordReset email fallo: ' . $e->getMessage());
            return back()->withErrors(['correo' => 'No se pudo enviar el correo. Verifica la configuración SMTP.']);
        }

        return back()->with('status', 'Te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.');
    }

    /* ─────────────────────────────────────────────────────────────
     |  3. Mostrar formulario de nueva contraseña
     ───────────────────────────────────────────────────────────── */
    public function showResetForm(Request $request, string $token)
    {
        $correo = $request->query('correo', '');
        return view('reset-password', compact('token', 'correo'));
    }

    /* ─────────────────────────────────────────────────────────────
     |  4. Procesar nueva contraseña
     ───────────────────────────────────────────────────────────── */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'correo'                => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'password.required'  => 'La contraseña es obligatoria.',
            'password.min'       => 'Mínimo 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $correo   = strtolower(trim($request->correo));
        $registro = DB::table('password_reset_tokens')->where('correo', $correo)->first();

        if (!$registro) {
            return back()->withErrors(['token' => 'Enlace inválido o expirado. Solicita uno nuevo.']);
        }

        // Verificar expiración
        if (Carbon::parse($registro->created_at)->addMinutes(self::EXPIRA_MINUTOS)->isPast()) {
            DB::table('password_reset_tokens')->where('correo', $correo)->delete();
            return back()->withErrors(['token' => 'El enlace ha expirado. Solicita uno nuevo.']);
        }

        // Verificar hash del token
        if (!Hash::check($request->token, $registro->token)) {
            return back()->withErrors(['token' => 'Enlace inválido. Solicita uno nuevo.']);
        }

        // Actualizar contraseña
        $usuario = DB::table('usuario')->whereRaw('LOWER(Correo) = ?', [$correo])->first();
        if (!$usuario) {
            return back()->withErrors(['correo' => 'Usuario no encontrado.']);
        }

        DB::table('usuario')
            ->where('idUsuario', $usuario->idUsuario)
            ->update(['password' => Hash::make($request->password)]);

        // Limpiar token usado
        DB::table('password_reset_tokens')->where('correo', $correo)->delete();

        return redirect()->route('login')->with('status', '✅ Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
