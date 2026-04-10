<?php
namespace App\Http\Controllers;

use App\Models\Estado;
use Illuminate\Http\Request;

class EstadoController extends Controller
{
    // Para API: lista estados
    public function index()
    {
        return response()->json(Estado::all());
    }

    public function show($id)
    {
        return response()->json(Estado::findOrFail($id));
    }

    // Nuevo: crear estado vía POST /api/estados
    public function store(Request $request)
    {
        $data = $request->validate([
            'Nombre_estado' => 'required|string|max:120|unique:estado,Nombre_estado',
            'Descripcion' => 'nullable|string|max:255'
        ]);

        $estado = Estado::create([
            'Nombre_estado' => $data['Nombre_estado'],
            'Descripcion' => $data['Descripcion'] ?? null
        ]);

        return response()->json($estado, 201);
    }
}
