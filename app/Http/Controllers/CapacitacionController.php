<?php
// File: app/Http/Controllers/CapacitacionController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Capacitacion;
use App\Models\SystemConfig;
use Cloudinary\Cloudinary as CloudinaryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CapacitacionController extends Controller
{
    // ─── Subida a Cloudinary ──────────────────────────────────────────────────
    // IMPORTANTE:
    // 1. NO usar use_filename: PHP guarda el temp como phpXXXX.tmp
    // 2. NO incluir extensión en el public_id: Cloudinary para resource_type=raw
    //    AÑADE la extensión del archivo subido al public_id, generando doble extensión
    //    (ej: archivo.xlsx.tmp). El public_id debe ser solo base+uniqid, sin extensión.
    // 3. El nombre real del archivo se guarda en original_name y se usa en la descarga.
    private function uploadToCloudinary($file, string $folder): array
    {
        $mime         = $file->getMimeType() ?? 'application/octet-stream';
        $resourceType = str_starts_with($mime, 'video/') ? 'video' : 'raw';

        $originalName = $file->getClientOriginalName();
        $extension    = strtolower($file->getClientOriginalExtension());
        $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase     = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);

        // SIN extensión en el public_id para evitar que Cloudinary duplique la extensión
        $publicId = $safeBase . '_' . uniqid();

        $cloudinary = new CloudinaryClient(config('cloudinary.cloud_url'));
        $upload = $cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder'        => $folder,
            'resource_type' => $resourceType,
            'public_id'     => $publicId,
        ]);

        if (empty($upload['secure_url'])) {
            throw new \RuntimeException('Cloudinary no retornó URL segura.');
        }

        return [
            'url'           => $upload['secure_url'],
            'public_id'     => $upload['public_id'],
            'resource_type' => $resourceType,
            'extension'     => $extension,
            'original_name' => $originalName,   // nombre real para Content-Disposition
        ];
    }

    // ─── GET /api/capacitaciones ──────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $q     = $request->query('q');
        $query = Capacitacion::query();
        if ($q) $query->where('Nombre_curso', 'like', "%{$q}%");
        $items = $query->orderByDesc('idCapacitacion')->get();
        return response()->json($items);
    }

    // ─── POST /api/capacitaciones ─────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $maxKB = SystemConfig::get('cap_max_size_mb', 100) * 1024;

        $request->validate([
            'Nombre_curso'      => 'required|string|max:500',
            'Fecha_Vencimiento' => 'nullable|date',
            'Fecha_Realizacion' => 'nullable|date',
            'Puntaje'           => 'nullable|numeric',
            'archivo'           => "nullable|file|max:{$maxKB}",
        ]);

        try {
            $data = $request->only(['Nombre_curso', 'Fecha_Vencimiento', 'Fecha_Realizacion', 'Puntaje']);
            $diasVigencia = SystemConfig::get('cap_dias_vigencia_default', 365);
            $data['Fecha_Vencimiento'] = $data['Fecha_Vencimiento'] ?? Carbon::now()->addDays($diasVigencia)->toDateString();
            $data['Fecha_Realizacion'] = $data['Fecha_Realizacion'] ?? 'Sin realizar';

            if ($file = $request->file('archivo')) {
                $upload = $this->uploadToCloudinary($file, 'capacitaciones_dracocerf');
                $data['Ruta']          = $upload['url'];
                $data['public_id']     = $upload['public_id'];
                $data['resource_type'] = $upload['resource_type'];
                $data['tipo_archivo']  = $upload['extension'];
                $data['original_name'] = $upload['original_name'];  // nombre real para descarga
            }

            $cap = Capacitacion::create($data);
            return response()->json($cap, 201);

        } catch (\Throwable $e) {
            Log::error('Capacitacion store error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear la capacitación: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── GET /api/capacitaciones/{id} ────────────────────────────────────────
    public function show($id): JsonResponse
    {
        $cap = Capacitacion::find($id);
        if (!$cap) return response()->json(['error' => 'No encontrado'], 404);
        return response()->json($cap);
    }

    // ─── DELETE /api/capacitaciones/{id} ─────────────────────────────────────
    public function destroy($id): JsonResponse
    {
        $cap = Capacitacion::find($id);
        if (!$cap) return response()->json(['error' => 'No encontrado'], 404);

        try {
            if ($cap->public_id) {
                $cloudinary = new CloudinaryClient(config('cloudinary.cloud_url'));
                $cloudinary->uploadApi()->destroy(
                    $cap->public_id,
                    ['resource_type' => $cap->resource_type ?? 'raw']
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete warning: ' . $e->getMessage());
        }

        $cap->delete();
        return response()->json(['ok' => true]);
    }

    // ─── GET /api/capacitaciones/{id}/recursos ────────────────────────────────
    public function recursos($id): JsonResponse
    {
        $recursos = DB::table('capacitacion_recurso')
            ->where('capacitacion_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($r) {
                $meta = $r->meta ? json_decode($r->meta, true) : [];
                return [
                    'id'            => $r->id,
                    'titulo'        => $r->titulo,
                    'tipo'          => $r->tipo,
                    'descripcion'   => $r->descripcion ?? '',
                    'ruta'          => $r->ruta ?? null,
                    'original_name' => $meta['original_name'] ?? null,
                    'formulario'    => $meta['formulario']    ?? null,  // estructura del form de evaluación
                    'fecha'         => $r->created_at ?? null,
                ];
            });

        return response()->json($recursos);
    }

    // ─── POST /api/capacitaciones/{id}/recursos ───────────────────────────────
    public function storeRecurso(Request $request, $id): JsonResponse
    {
        $maxKB = SystemConfig::get('cap_max_size_mb', 100) * 1024;

        $request->validate([
            'titulo'  => 'required|string|max:500',
            'tipo'    => 'required|string',
            'archivo' => "nullable|file|max:{$maxKB}",
        ]);

        try {
            $data = [
                'capacitacion_id' => $id,              // columna real: capacitacion_id
                'titulo'          => $request->titulo, // columna real: titulo
                'tipo'            => $request->tipo,   // columna real: tipo
                'descripcion'     => $request->descripcion ?? '',
            ];

            if ($request->tipo === 'formulario' && $request->has('formulario_data')) {
                // Formulario de evaluación: guardar estructura JSON, sin archivo
                $formulario = json_decode($request->formulario_data, true);
                $data['meta'] = json_encode(['formulario' => $formulario]);
            } elseif ($file = $request->file('archivo')) {
                $upload         = $this->uploadToCloudinary($file, 'capacitaciones_recursos');
                $data['ruta']   = $upload['url'];
                $data['meta']   = json_encode(['original_name' => $upload['original_name']]);
            }

            $newId = DB::table('capacitacion_recurso')->insertGetId($data);
            return response()->json(['ok' => true, 'id' => $newId], 201);

        } catch (\Throwable $e) {
            Log::error('Recurso store error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al crear recurso: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─── GET /api/capacitaciones/{id}/descargar ───────────────────────────────
    public function download($id)
    {
        $cap = Capacitacion::find($id);
        if (!$cap || !$cap->Ruta) {
            return response()->json(['error' => 'Archivo no disponible'], 404);
        }

        $filename = $cap->original_name
            ?? ($cap->Nombre_curso . ($cap->tipo_archivo ? '.' . $cap->tipo_archivo : ''));

        return $this->streamDownload($cap->Ruta, $filename);
    }

    // ─── GET /api/capacitaciones/{id}/recursos/{recursoId}/descargar ──────────
    public function downloadRecurso($id, $recursoId)
    {
        $recurso = DB::table('capacitacion_recurso')
            ->where('id', $recursoId)
            ->where('capacitacion_id', $id)
            ->first();

        if (!$recurso || !$recurso->ruta) {
            return response()->json(['error' => 'Archivo no disponible'], 404);
        }

        $meta     = $recurso->meta ? json_decode($recurso->meta, true) : [];
        $filename = $meta['original_name'] ?? ($recurso->titulo ?? 'recurso');

        return $this->streamDownload($recurso->ruta, $filename);
    }

    // ─── Helper: streaming seguro desde URL remota (igual que DocumentoController) ──
    private function streamDownload(string $url, string $filename)
    {
        return response()->streamDownload(function () use ($url) {
            $context = stream_context_create([
                'http' => ['timeout' => 60],
                'ssl'  => ['verify_peer' => false],
            ]);
            $stream = @fopen($url, 'r', false, $context);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $filename);
    }

    // ─── DELETE /api/capacitaciones/{id}/recursos/{recursoId} ────────────────
    public function destroyRecurso($id, $recursoId): JsonResponse
    {
        $deleted = DB::table('capacitacion_recurso')
            ->where('id', $recursoId)                 // columna real: id
            ->where('capacitacion_id', $id)           // columna real: capacitacion_id
            ->delete();

        if (!$deleted) return response()->json(['error' => 'No encontrado'], 404);
        return response()->json(['ok' => true]);
    }
}
