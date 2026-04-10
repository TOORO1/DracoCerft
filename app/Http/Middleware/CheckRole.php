<?php
// File: app/Http/Middleware/CheckRole.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Verifica que el usuario autenticado tenga al menos uno de los roles indicados.
     *
     * Uso en rutas:
     *   ->middleware('role:Administrador')
     *   ->middleware('role:Administrador,Auditor')
     *
     * @param  string  ...$roles  Nombres de roles permitidos (Nombre_rol)
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['error' => 'No autenticado'], 401)
                : redirect()->route('login');
        }

        // Obtener el nombre del primer rol del usuario
        $userRole = $user->roles()->first()?->Nombre_rol ?? null;

        if (! $userRole || ! in_array($userRole, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'Acceso denegado',
                    'message' => 'No tienes permisos para realizar esta acción. Rol requerido: ' . implode(' o ', $roles),
                ], 403);
            }

            // Redirigir al dashboard con mensaje de error
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permisos para acceder a esa sección. Se requiere: ' . implode(' o ', $roles));
        }

        return $next($request);
    }
}
