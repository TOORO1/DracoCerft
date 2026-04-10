<?php
// Language: php
// File: app/Http/Controllers/PermisosController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Permiso;

class PermisosController extends Controller
{
    // Vista principal que monta React
    public function index()
    {
        return view('Permisos');
    }

    // API: listar permisos
    public function listPermisos()
    {
        try {
            $permisos = Permiso::orderBy('nombre')->get();
            return response()->json(['ok' => true, 'data' => $permisos]);
        } catch (\Exception $e) {
            Log::error('listPermisos: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar permisos'], 500);
        }
    }

    // API: crear permiso
    public function storePermiso(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:191|unique:permiso,nombre',
            'descripcion' => 'nullable|string',
        ]);

        try {
            $permiso = Permiso::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion ?? null,
                'guard' => $request->guard ?? 'web',
            ]);
            return response()->json(['ok' => true, 'permiso' => $permiso], 201);
        } catch (\Exception $e) {
            Log::error('storePermiso: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al crear permiso'], 500);
        }
    }

    // API: actualizar permiso
    public function updatePermiso(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:191',
            'descripcion' => 'nullable|string',
        ]);

        try {
            $permiso = Permiso::findOrFail($id);
            $permiso->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion ?? null,
            ]);
            return response()->json(['ok' => true, 'permiso' => $permiso]);
        } catch (\Exception $e) {
            Log::error('updatePermiso: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al actualizar permiso'], 500);
        }
    }

    // API: eliminar permiso
    public function deletePermiso($id)
    {
        try {
            Permiso::where('id', $id)->delete();
            // limpiar pivots
            DB::table('rol_permiso')->where('permiso_id', $id)->delete();
            DB::table('usuario_permiso')->where('permiso_id', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('deletePermiso: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al eliminar permiso'], 500);
        }
    }

    // Listar permisos asignados a un rol
    public function listRolePermisos($rolId)
    {
        try {
            $permisos = DB::table('rol_permiso')
                ->where('rol_id', $rolId)
                ->join('permiso', 'permiso.id', '=', 'rol_permiso.permiso_id')
                ->select('permiso.*')
                ->get();
            return response()->json(['ok' => true, 'data' => $permisos]);
        } catch (\Exception $e) {
            Log::error('listRolePermisos: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar permisos del rol'], 500);
        }
    }

    // Asignar permiso a rol (evita duplicados)
    public function assignPermisoToRole(Request $request, $rolId)
    {
        $data = $request->validate(['permiso_id' => 'required|integer']);
        try {
            $exists = DB::table('rol_permiso')->where('rol_id', $rolId)->where('permiso_id', $data['permiso_id'])->exists();
            if ($exists) return response()->json(['ok' => false, 'message' => 'Ya asignado'], 409);
            DB::table('rol_permiso')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $data['permiso_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('assignPermisoToRole: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error asignando permiso al rol'], 500);
        }
    }

    // Listar permisos de usuario
    public function listUsuarioPermisos($usuarioId)
    {
        try {
            $permisos = DB::table('usuario_permiso')
                ->where('usuario_id', $usuarioId)
                ->join('permiso', 'permiso.id', '=', 'usuario_permiso.permiso_id')
                ->select('permiso.*')
                ->get();
            return response()->json(['ok' => true, 'data' => $permisos]);
        } catch (\Exception $e) {
            Log::error('listUsuarioPermisos: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar permisos del usuario'], 500);
        }
    }

    // Asignar permiso a usuario (evita duplicados)
    public function assignPermisoToUsuario(Request $request, $usuarioId)
    {
        $data = $request->validate(['permiso_id' => 'required|integer']);
        try {
            $exists = DB::table('usuario_permiso')->where('usuario_id', $usuarioId)->where('permiso_id', $data['permiso_id'])->exists();
            if ($exists) return response()->json(['ok' => false, 'message' => 'Ya asignado'], 409);
            DB::table('usuario_permiso')->insert([
                'usuario_id' => $usuarioId,
                'permiso_id' => $data['permiso_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('assignPermisoToUsuario: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error asignando permiso al usuario'], 500);
        }
    }

    // Quitar permiso de rol
    public function removePermisoFromRole($rolId, $permisoId)
    {
        try {
            DB::table('rol_permiso')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->delete();
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('removePermisoFromRole: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error removiendo permiso'], 500);
        }
    }

    // Quitar permiso de usuario
    public function removePermisoFromUsuario($usuarioId, $permisoId)
    {
        try {
            DB::table('usuario_permiso')->where('usuario_id', $usuarioId)->where('permiso_id', $permisoId)->delete();
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('removePermisoFromUsuario: '.$e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error removiendo permiso'], 500);
        }
    }
}
