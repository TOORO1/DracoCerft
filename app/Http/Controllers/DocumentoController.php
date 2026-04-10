<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ActivityLog;
use App\Http\Controllers\DashboardController;
use App\Models\SystemConfig;
use Carbon\Carbon;
use Cloudinary\Cloudinary as CloudinaryClient;

class DocumentoController extends Controller
{
    // ─── Carpetas ISO fijas ───────────────────────────────────────────────────

    private static function folderList(): array
    {
        return [
            ['id' => 'iso9001',  'Nombre' => 'ISO 9001',  'descripcion' => 'Sistemas de Gestión de Calidad',  'icon' => 'fa-award',      'color' => '#ff8a00'],
            ['id' => 'iso14001', 'Nombre' => 'ISO 14001', 'descripcion' => 'Gestión Ambiental',               'icon' => 'fa-leaf',       'color' => '#26a69a'],
            ['id' => 'iso27001', 'Nombre' => 'ISO 27001', 'descripcion' => 'Seguridad de la Información',     'icon' => 'fa-shield-alt', 'color' => '#5c6bc0'],
            ['id' => 'general',  'Nombre' => 'General',   'descripcion' => 'Documentos Generales',            'icon' => 'fa-folder',     'color' => '#78909c'],
        ];
    }

    // ─── API: Listado con búsqueda y filtro de carpeta ────────────────────────

    public function index(Request $request)
    {
        $q          = $request->query('q');
        $folder     = $request->query('folder');
        $fechaDesde = $request->query('fecha_desde');   // YYYY-MM-DD
        $fechaHasta = $request->query('fecha_hasta');   // YYYY-MM-DD
        $estado     = $request->query('estado');        // vigente | caducado
        $ordenar    = $request->query('ordenar', 'reciente'); // reciente | antiguo | nombre_az | nombre_za

        $query = DB::table('documento')
            ->leftJoin('version', 'documento.Version_idVersion', '=', 'version.idVersion')
            ->select('documento.*', 'version.numero_Version', 'version.nombre_archivo');

        // ── Búsqueda por texto (nombre del documento)
        if ($q) {
            $query->where('documento.Nombre_Doc', 'like', "%{$q}%");
        }

        // ── Filtro por carpeta ISO
        if ($folder && $folder !== 'all') {
            $query->where(function ($sub) use ($folder) {
                $sub->where('documento.Ruta', 'like', "%documentos/{$folder}/%")
                    ->orWhere('documento.Ruta', 'like', "%/{$folder}/%");
            });
        }

        // ── Filtro por rango de fecha de creación
        if ($fechaDesde) {
            $query->where('documento.Fecha_creacion', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->where('documento.Fecha_creacion', '<=', $fechaHasta);
        }

        // ── Filtro por estado de caducidad
        $hoy = Carbon::today()->toDateString();
        if ($estado === 'vigente') {
            $query->where(function ($sub) use ($hoy) {
                $sub->whereNull('documento.Fecha_Caducidad')
                    ->orWhere('documento.Fecha_Caducidad', '>=', $hoy);
            });
        } elseif ($estado === 'caducado') {
            $query->where('documento.Fecha_Caducidad', '<', $hoy);
        } elseif ($estado === 'por_vencer') {
            $diasAlerta = SystemConfig::get('doc_dias_alerta_vencimiento', 30);
            $enN = Carbon::today()->addDays($diasAlerta)->toDateString();
            $query->whereBetween('documento.Fecha_Caducidad', [$hoy, $enN]);
        }

        // ── Ordenamiento
        match ($ordenar) {
            'antiguo'    => $query->orderBy('documento.idDocumento', 'asc'),
            'nombre_az'  => $query->orderBy('documento.Nombre_Doc', 'asc'),
            'nombre_za'  => $query->orderBy('documento.Nombre_Doc', 'desc'),
            default      => $query->orderBy('documento.idDocumento', 'desc'),
        };

        $docs = $query->get()->map(function ($d) {
            $d->version = isset($d->numero_Version)
                ? number_format($d->numero_Version, 1, '.', '')
                : '1.0';
            $d->folder = $this->detectFolder($d->Ruta ?? '');
            return $d;
        });

        return response()->json($docs);
    }

    // ─── API: Carpetas ISO con conteo ─────────────────────────────────────────

    public function folders()
    {
        $folders = static::folderList();

        foreach ($folders as &$f) {
            $f['count'] = DB::table('documento')
                ->where(function ($q) use ($f) {
                    $q->where('Ruta', 'like', "%documentos/{$f['id']}/%")
                      ->orWhere('Ruta', 'like', "%/{$f['id']}/%");
                })
                ->count();
        }

        return response()->json($folders);
    }

    // ─── API: Detalle + historial de versiones ────────────────────────────────

    public function show($id)
    {
        $doc = DB::table('documento')
            ->leftJoin('version', 'documento.Version_idVersion', '=', 'version.idVersion')
            ->select('documento.*', 'version.numero_Version')
            ->where('documento.idDocumento', $id)
            ->first();

        if (! $doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $versiones = DB::table('version')
            ->leftJoin('usuario', 'version.Usuario_idUsuarioCambio', '=', 'usuario.idUsuario')
            ->select('version.*', 'usuario.Nombre_Usuario')
            ->where('version.documento_id', $id)
            ->orderBy('version.numero_Version', 'asc')
            ->get()
            ->map(fn($v) => [
                'idVersion'          => $v->idVersion,
                'numero_Version'     => $v->numero_Version,
                'Fecha_cambio'       => $v->Fecha_cambio,
                'Descripcion_Cambio' => $v->Descripcion_Cambio,
                'nombre_archivo'     => $v->nombre_archivo  ?? null,
                'public_id'          => $v->public_id       ?? null,
                'url'                => $v->url             ?? null,
                'resource_type'      => $v->resource_type   ?? 'raw',
                'created_at'         => $v->created_at      ?? null,
                'Nombre_Usuario'     => $v->Nombre_Usuario  ?? 'Sistema',
            ]);

        return response()->json([
            'documento' => $doc,
            'versiones' => $versiones,
        ]);
    }

    // ─── API: Subir nuevo documento ───────────────────────────────────────────

    public function store(Request $request)
    {
        $maxKB      = SystemConfig::get('doc_max_size_mb', 50) * 1024;
        $extensiones = SystemConfig::get('doc_tipos_permitidos', 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,txt,csv,zip,rar');

        $request->validate([
            'archivo'    => [
                'required', 'file', "max:{$maxKB}",
                "extensions:{$extensiones}",
            ],
            'Nombre_Doc' => 'required|string|max:200',
            'folder'     => 'nullable|string|in:iso9001,iso14001,iso27001,general',
        ]);

        try {
            $file = $request->file('archivo');
            if (! $file || ! $file->isValid()) {
                return response()->json(['error' => 'Archivo inválido o corrupto'], 422);
            }

            $folder     = trim($request->input('folder', 'general'));
            $folderPath = "documentos/{$folder}";

            $upload = $this->uploadToCloudinary($file, $folderPath);

            $userId = optional($request->user())->idUsuario
                   ?? optional($request->user())->getKey()
                   ?? 1;
            $now = Carbon::now()->toDateString();

            // Crear versión inicial
            $versionId = DB::table('version')->insertGetId([
                'numero_Version'          => 1,
                'Fecha_cambio'            => $now,
                'Descripcion_Cambio'      => 'Versión inicial',
                'Usuario_idUsuarioCambio' => $userId,
                'documento_id'            => null,
                'public_id'               => $upload['public_id'],
                'url'                     => $upload['url'],
                'nombre_archivo'          => $file->getClientOriginalName(),
                'resource_type'           => $upload['resource_type'],
                'created_at'              => now(),
            ]);

            // Crear documento
            $diasVigencia = SystemConfig::get('doc_dias_vigencia_default', 365);
            $docId = DB::table('documento')->insertGetId([
                'Nombre_Doc'                      => $request->input('Nombre_Doc'),
                'Ruta'                            => $upload['url'],
                'Fecha_creacion'                  => $now,
                'Fecha_Caducidad'                 => Carbon::now()->addDays($diasVigencia)->toDateString(),
                'Fecha_Revision'                  => $now,
                'Tipo_Documento_idTipo_Documento' => 1,
                'Version_idVersion'               => $versionId,
                'Usuario_idUsuarioCreador'        => $userId,
            ]);

            // Enlazar versión al documento
            DB::table('version')->where('idVersion', $versionId)->update(['documento_id' => $docId]);

            ActivityLog::record(
                'upload_doc',
                'documentos',
                "Documento subido: {$request->input('Nombre_Doc')} (carpeta: {$folder})"
            );

            // Invalidar cache de notificaciones para que el contador se actualice de inmediato
            if ($userId) {
                DashboardController::clearNotificationsCache((int) $userId);
            }

            $doc = DB::table('documento')->where('idDocumento', $docId)->first();
            return response()->json($doc, 201);

        } catch (\Throwable $e) {
            Log::error('DocumentoController@store: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al subir el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── API: Subir nueva versión ─────────────────────────────────────────────

    public function storeVersion(Request $request, $id)
    {
        $maxKB       = SystemConfig::get('doc_max_size_mb', 50) * 1024;
        $extensiones = SystemConfig::get('doc_tipos_permitidos', 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,txt,csv,zip,rar');

        $request->validate([
            'archivo'     => "required|file|max:{$maxKB}|extensions:{$extensiones}",
            'descripcion' => 'nullable|string|max:500',
        ]);

        try {
            $doc = DB::table('documento')->where('idDocumento', $id)->first();
            if (! $doc) {
                return response()->json(['error' => 'Documento no encontrado'], 404);
            }

            $file = $request->file('archivo');
            if (! $file || ! $file->isValid()) {
                return response()->json(['error' => 'Archivo inválido o corrupto'], 422);
            }

            $folder     = $this->detectFolder($doc->Ruta ?? 'general');
            $folderPath = "documentos/{$folder}";

            $upload = $this->uploadToCloudinary($file, $folderPath);

            $lastVersion = DB::table('version')
                ->where('documento_id', $id)
                ->max('numero_Version');
            $nextVersion = ($lastVersion ?? 1) + 1;

            // ── Aplicar límite de versiones configurado ───────────────────────
            $maxVersiones = SystemConfig::get('doc_max_versiones', 10);
            if ($maxVersiones > 0) {
                $totalVersiones = DB::table('version')->where('documento_id', $id)->count();
                if ($totalVersiones >= $maxVersiones) {
                    // Eliminar la versión más antigua de Cloudinary y la BD
                    $oldest = DB::table('version')
                        ->where('documento_id', $id)
                        ->orderBy('numero_Version', 'asc')
                        ->first();
                    if ($oldest && ! empty($oldest->public_id)) {
                        $this->deleteFromCloudinary($oldest->public_id, $oldest->resource_type ?? 'raw');
                    }
                    if ($oldest) {
                        DB::table('version')->where('idVersion', $oldest->idVersion)->delete();
                    }
                }
            }

            $userId = optional($request->user())->idUsuario
                   ?? optional($request->user())->getKey()
                   ?? 1;
            $now = Carbon::now()->toDateString();

            $versionId = DB::table('version')->insertGetId([
                'numero_Version'          => $nextVersion,
                'Fecha_cambio'            => $now,
                'Descripcion_Cambio'      => $request->input('descripcion', 'Nueva versión'),
                'Usuario_idUsuarioCambio' => $userId,
                'documento_id'            => $id,
                'public_id'               => $upload['public_id'],
                'url'                     => $upload['url'],
                'nombre_archivo'          => $file->getClientOriginalName(),
                'resource_type'           => $upload['resource_type'],
                'created_at'              => now(),
            ]);

            DB::table('documento')->where('idDocumento', $id)->update([
                'Ruta'              => $upload['url'],
                'Version_idVersion' => $versionId,
                'Fecha_Revision'    => $now,
            ]);

            ActivityLog::record(
                'nueva_version',
                'documentos',
                "Nueva versión v{$nextVersion} del documento: {$doc->Nombre_Doc}"
            );

            return response()->json([
                'message'    => 'Nueva versión registrada correctamente',
                'version'    => $nextVersion,
                'version_id' => $versionId,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('DocumentoController@storeVersion: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al registrar la versión: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── API: Eliminar documento ──────────────────────────────────────────────

    public function destroy($id)
    {
        try {
            $doc = DB::table('documento')->where('idDocumento', $id)->first();
            if (! $doc) {
                return response()->json(['error' => 'Documento no encontrado'], 404);
            }

            // Eliminar archivos de Cloudinary para cada versión
            $versiones = DB::table('version')->where('documento_id', $id)->get();
            foreach ($versiones as $v) {
                if (! empty($v->public_id)) {
                    $this->deleteFromCloudinary($v->public_id, $v->resource_type ?? 'raw');
                }
            }

            DB::table('documento')->where('idDocumento', $id)->delete();
            DB::table('version')->where('documento_id', $id)->delete();

            ActivityLog::record('delete_doc', 'documentos', "Documento eliminado: {$doc->Nombre_Doc}");

            return response()->json(['success' => true, 'message' => 'Documento eliminado correctamente.']);

        } catch (\Throwable $e) {
            Log::error('DocumentoController@destroy: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo eliminar el documento'], 500);
        }
    }

    // ─── API: Descargar versión más reciente ──────────────────────────────────

    public function download(int $id)
    {
        $version = DB::table('version')
            ->where('documento_id', $id)
            ->orderBy('numero_Version', 'desc')
            ->first();

        $url      = $version->url      ?? DB::table('documento')->where('idDocumento', $id)->value('Ruta');
        $filename = $version->nombre_archivo ?? 'documento';

        if (! $url) {
            return response()->json(['error' => 'Archivo no disponible'], 404);
        }

        return $this->streamDownload($url, $filename);
    }

    // ─── API: Descargar versión específica ────────────────────────────────────

    public function downloadVersion(int $docId, int $versionId)
    {
        $version = DB::table('version')
            ->where('idVersion', $versionId)
            ->where('documento_id', $docId)
            ->first();

        if (! $version || ! $version->url) {
            return response()->json(['error' => 'Versión no disponible'], 404);
        }

        return $this->streamDownload($version->url, $version->nombre_archivo ?? 'documento');
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Sube un archivo a Cloudinary con el resource_type correcto según MIME.
     * Retorna array ['url', 'public_id', 'resource_type'].
     */
    private function uploadToCloudinary($file, string $folderPath): array
    {
        $mime         = $file->getMimeType() ?? 'application/octet-stream';
        $resourceType = $this->detectResourceType($mime);

        // Construir public_id SIN extensión: para resource_type=raw, Cloudinary añade
        // automáticamente la extensión del archivo subido al public_id. Si ya incluimos
        // la extensión en el public_id, Cloudinary genera doble extensión (ej: .xlsx.tmp).
        $originalName = $file->getClientOriginalName();
        $extension    = strtolower($file->getClientOriginalExtension());
        $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase     = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $publicId     = $safeBase . '_' . uniqid();   // sin extensión

        $cloudinary = new CloudinaryClient(config('cloudinary.cloud_url'));

        $response = $cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder'        => $folderPath,
            'resource_type' => $resourceType,
            'public_id'     => $publicId,
        ]);

        if (empty($response['secure_url'])) {
            throw new \RuntimeException('Cloudinary no retornó URL segura.');
        }

        return [
            'url'           => $response['secure_url'],
            'public_id'     => $response['public_id'],
            'resource_type' => $resourceType,
            'original_name' => $originalName,
        ];
    }

    /**
     * Elimina un archivo de Cloudinary con su resource_type correcto.
     */
    private function deleteFromCloudinary(string $publicId, string $resourceType = 'raw'): void
    {
        try {
            $cloudinary = new CloudinaryClient(config('cloudinary.cloud_url'));
            $cloudinary->uploadApi()->destroy($publicId, ['resource_type' => $resourceType]);
        } catch (\Throwable $e) {
            Log::warning("No se pudo eliminar de Cloudinary [{$resourceType}] {$publicId}: " . $e->getMessage());
        }
    }

    /**
     * Descarga un archivo remoto (Cloudinary) con el nombre de archivo correcto.
     * Hace streaming a través de Laravel para garantizar headers de descarga.
     */
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

    /**
     * Detecta el resource_type de Cloudinary según el MIME del archivo.
     * - image/* → 'image'
     * - video/* → 'video'
     * - todo lo demás (PDF, DOCX, XLSX, ZIP…) → 'raw'
     */
    private function detectResourceType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        return 'raw';
    }

    /**
     * Extrae el nombre de la carpeta ISO desde la URL/ruta almacenada.
     */
    private function detectFolder(string $ruta): string
    {
        foreach (['iso9001', 'iso14001', 'iso27001'] as $f) {
            if (str_contains($ruta, $f)) return $f;
        }
        return 'general';
    }
}
