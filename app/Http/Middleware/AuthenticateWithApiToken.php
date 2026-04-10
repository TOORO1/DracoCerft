<?php
// php
// Archivo: app/Http/Middleware/AuthenticateWithApiToken.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Usuario;

class AuthenticateWithApiToken
{
    public function handle(Request $request, Closure $next)
    {
        // Obtener token desde Authorization Bearer o parametro api_token
        $plainToken = $request->bearerToken() ?: $request->input('api_token');

        if (! $plainToken) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        $hashed = hash('sha256', $plainToken);
        $user = Usuario::where('api_token', $hashed)->first();

        if (! $user) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        // Establecer usuario autenticado para la petición
        Auth::login($user);

        return $next($request);
    }
}

