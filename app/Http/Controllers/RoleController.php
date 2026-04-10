<?php
namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    // API: lista roles
    public function index()
    {
        return response()->json(Rol::all());
    }

    public function show($id)
    {
        return response()->json(Rol::findOrFail($id));
    }

    // Nuevo: crear rol vía POST /api/roles
    public function store(Request $request)
    {
        $data = $request->validate([
            'Nombre_rol' => 'required|string|max:120|unique:rol,Nombre_rol',
            'Descripcion' => 'nullable|string|max:255'
        ]);

        $role = Rol::create([
            'Nombre_rol' => $data['Nombre_rol'],
            'Descripcion' => $data['Descripcion'] ?? null
        ]);

        return response()->json($role, 201);
    }
}
