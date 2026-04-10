<?php
// app/Http/Controllers/UsuarioController.php
namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Rol;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index()
    {
        return view('gestor_usuarios');
    }

    // GET /api/usuarios
    public function indexApi()
    {
        $usuarios = Usuario::with(['estado', 'roles'])
            ->orderBy('idUsuario', 'desc')
            ->get();

        $data = $usuarios->map(function($u) {
            return [
                'idUsuario' => $u->idUsuario,
                'Cedula' => $u->Cedula,
                'Nombre_Usuario' => $u->Nombre_Usuario,
                'Correo' => $u->Correo,
                'Estado_idEstado' => $u->Estado_idEstado,
                'Nombre_estado' => $u->estado ? $u->estado->Nombre_estado : 'Sin Estado',
                'Rol' => $u->roles->first() ? $u->roles->first()->Nombre_rol : 'Sin Rol',
                'Rol_Nombre' => $u->roles->first() ? $u->roles->first()->Nombre_rol : '',
                'Rol_id' => $u->roles->first() ? $u->roles->first()->idRol : null,
            ];
        });

        return response()->json($data);
    }

    // POST /api/usuarios
    public function store(Request $request)
    {
        // Validación: contraseña obligatoria y patrón fuerte
        $request->validate([
            'Nombre_Usuario' => ['required','string'],
            'Correo' => ['required','email'],
            'Estado_idEstado' => ['required','integer'],
            'password' => ['required','string','min:8','regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/'],
        ], [
            'password.regex' => 'La contraseña debe tener mínimo 8 caracteres, al menos una mayúscula, un número y un carácter especial.',
        ]);

        return DB::transaction(function () use ($request) {
            $usuario = new Usuario();
            // Cedula es VARCHAR nullable; si llega vacío guardamos null
            $cedula = $request->Cedula;
            $usuario->Cedula = ($cedula !== null && $cedula !== '') ? (string)$cedula : null;
            $usuario->Nombre_Usuario = $request->Nombre_Usuario;
            $usuario->Correo = $request->Correo;
            $usuario->Estado_idEstado = $request->Estado_idEstado;
            $usuario->password = Hash::make($request->password);

            // Fechas obligatorias
            $usuario->Fecha_Creacion = now();
            $usuario->Fecha_Registro = now();
            $usuario->Fecha_Ultima = now();

            $usuario->save();

            // Asignar Rol en la tabla intermedia
            if ($request->Rol) {
                $rol = Rol::where('Nombre_rol', $request->Rol)->first();
                if ($rol) {
                    $usuario->roles()->attach($rol->idRol, ['Fecha_Asignacion' => now()]);
                }
            }

            ActivityLog::record('crear_usuario', 'usuarios', "Usuario creado: {$request->Nombre_Usuario}");
            return response()->json(['message' => 'Usuario creado correctamente']);
        });
    }

    // PUT /api/usuarios/{id}
    public function update(Request $request, $id)
    {
        // Reglas básicas; la contraseña es opcional al editar, pero si viene debe cumplir la regla
        $rules = [
            'Nombre_Usuario' => ['required','string'],
            'Correo' => ['required','email'],
            'Estado_idEstado' => ['required','integer'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['string','min:8','regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/'];
        }

        $messages = [
            'password.regex' => 'La contraseña debe tener mínimo 8 caracteres, al menos una mayúscula, un número y un carácter especial.',
        ];

        $request->validate($rules, $messages);

        return DB::transaction(function () use ($request, $id) {
            $usuario = Usuario::findOrFail($id);

            // Cedula opcional en edición; si no viene, conservar valor actual
            if ($request->has('Cedula')) {
                $cedula = $request->Cedula;
                $usuario->Cedula = ($cedula !== null && $cedula !== '') ? (string)$cedula : null;
            }
            $usuario->Nombre_Usuario = $request->Nombre_Usuario;
            $usuario->Correo = $request->Correo;
            $usuario->Estado_idEstado = $request->Estado_idEstado;

            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
            }

            $usuario->Fecha_Ultima = now();
            $usuario->save();

            // Sincronizar Rol
            if ($request->Rol) {
                $rol = Rol::where('Nombre_rol', $request->Rol)->first();
                if ($rol) {
                    $usuario->roles()->sync([$rol->idRol => ['Fecha_Asignacion' => now()]]);
                }
            }

            ActivityLog::record('editar_usuario', 'usuarios', "Usuario actualizado: {$request->Nombre_Usuario}");
            return response()->json(['message' => 'Usuario actualizado']);
        });
    }

    // PATCH /api/usuarios/{id}/rol  — solo cambia el rol
    public function changeRol(Request $request, $id)
    {
        $request->validate(['rol_id' => 'required|integer']);

        return DB::transaction(function () use ($request, $id) {
            $usuario = Usuario::findOrFail($id);
            $rol     = Rol::findOrFail($request->rol_id);

            $usuario->roles()->sync([$rol->idRol => ['Fecha_Asignacion' => now()]]);

            ActivityLog::record('cambiar_rol', 'usuarios', "Rol de {$usuario->Nombre_Usuario} cambiado a {$rol->Nombre_rol}");

            return response()->json([
                'ok'      => true,
                'message' => "Rol cambiado a {$rol->Nombre_rol}",
                'rol'     => $rol->Nombre_rol,
            ]);
        });
    }

    public function destroy($id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->Estado_idEstado = 3; // Eliminación lógica (3 = Eliminado)
        $usuario->save();
        ActivityLog::record('eliminar_usuario', 'usuarios', "Usuario eliminado: {$usuario->Nombre_Usuario}");
        return response()->json(['message' => 'Usuario eliminado lógicamente']);
    }
}
