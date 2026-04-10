<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CapacitacionRecurso; // crea el modelo Eloquent correspondiente

class CapacitacionRecursoController extends Controller
{
    public function index($capacitacionId)
    {
        $recursos = CapacitacionRecurso::where('capacitacion_id', $capacitacionId)->orderBy('created_at','desc')->get();
        return response()->json($recursos);
    }

    public function store(Request $request, $capacitacionId)
    {
        $request->validate([
            'tipo' => 'required|in:archivo,formulario,actualizacion',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:5000', // Validación para el nuevo textarea
            'archivo' => 'nullable|file|max:10240', // 10MB
            'requiere_formulario' => 'nullable|in:0,1' // Nuevo campo
        ]);

        $ruta = null;
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            // Nota: Aquí se está usando el disco local. Para archivos grandes o más robustez, se recomienda Cloudinary o S3 (como en CapacitacionController).
            $path = $file->store("capacitaciones/{$capacitacionId}", 'public');
            $ruta = Storage::url($path);
        }

        // Preparar el campo 'meta' para almacenar datos adicionales como Moodle.
        $metaData = [];
        if ($request->input('requiere_formulario') === '1') {
            $metaData['requiere_formulario'] = '1';
            // Podrías añadir más lógica aquí en el futuro (p. ej. 'puntaje_maximo', 'id_formulario_externo').
        }

        // El campo `meta` es text en la base de datos y se almacena como JSON.
        $metaJson = count($metaData) > 0 ? json_encode($metaData) : null;

        $recurso = CapacitacionRecurso::create([
            'capacitacion_id' => $capacitacionId,
            'tipo' => $request->input('tipo'),
            'titulo' => $request->input('titulo'),
            'descripcion' => $request->input('descripcion'), // Ahora se almacena la descripción
            'ruta' => $ruta,
            'meta' => $metaJson, // Almacena el JSON
            'usuario_id' => auth()->id() ?? null
        ]);

        return response()->json($recurso, 201);
    }

    public function destroy($capacitacionId, $id)
    {
        $r = CapacitacionRecurso::where('capacitacion_id', $capacitacionId)->where('id', $id)->firstOrFail();
        // opcional: borrar archivo físico
        if ($r->ruta) {
            $relative = str_replace('/storage/', '', $r->ruta); // si usas Storage::url
            Storage::disk('public')->delete($relative);
        }
        $r->delete();
        return response()->json(['ok' => true]);
    }
}
