<?php
// File: app/Http/Middleware/TokenOrSessionAuth.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario;

class TokenOrSessionAuth
{
    /**
     * Permite acceso si hay sesión activa o token Bearer válido.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1) Ya autenticado por sesión
        if (Auth::check()) {
            return $next($request);
        }

        // 2) Intentar token Bearer
        $token = $request->bearerToken();
        if ($token) {
            $hashed = hash('sha256', $token);
            $user = Usuario::where('api_token', $hashed)->first();
            if ($user) {
                Auth::login($user);
                return $next($request);
            }
        }

        // 3) No autenticado
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
