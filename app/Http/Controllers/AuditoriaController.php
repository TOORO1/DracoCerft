<?php
// File: app/Http/Controllers/AuditoriaController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\ActivityLog;
use App\Mail\AuditoriaResultadoAlert;

class AuditoriaController extends Controller
{
    /* ─────────────────────────────────────────────────────────────
     |  Vista principal
     ───────────────────────────────────────────────────────────── */
    public function index()
    {
        return view('Auditoria');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Normas
     ───────────────────────────────────────────────────────────── */
    public function listNormas()
    {
        try {
            $normas = DB::table('norma')->orderBy('Codigo_norma')->get();
            return response()->json(['ok' => true, 'data' => $normas]);
        } catch (\Exception $e) {
            Log::error('listNormas: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar normas'], 500);
        }
    }

    public function storeNorma(Request $request)
    {
        try {
            $validated = $request->validate([
                'codigo'      => 'required|string|max:100',
                'descripcion' => 'required|string|max:500',
            ]);

            $id = DB::table('norma')->insertGetId([
                'Codigo_norma'           => $validated['codigo'],
                'Descripcion'            => $validated['descripcion'],
                'Requisitos_idRequisitos'=> null,
            ]);

            $norma = DB::table('norma')->where('idNorma', $id)->first();
            ActivityLog::record('crear', 'auditoria', "Norma creada: {$validated['codigo']}");

            return response()->json(['ok' => true, 'norma' => $norma], 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('storeNorma: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al crear la norma'], 500);
        }
    }

    public function updateNorma(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'codigo'      => 'required|string|max:100',
                'descripcion' => 'nullable|string|max:500',
            ]);

            $affected = DB::table('norma')->where('idNorma', $id)->update([
                'Codigo_norma' => $validated['codigo'],
                'Descripcion'  => $validated['descripcion'] ?? null,
            ]);

            if (!$affected) {
                return response()->json(['ok' => false, 'message' => 'Norma no encontrada'], 404);
            }

            $norma = DB::table('norma')->where('idNorma', $id)->first();
            ActivityLog::record('editar', 'auditoria', "Norma actualizada: {$validated['codigo']}");

            return response()->json(['ok' => true, 'norma' => $norma]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('updateNorma: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al actualizar la norma'], 500);
        }
    }

    public function destroyNorma($id)
    {
        try {
            $norma = DB::table('norma')->where('idNorma', $id)->first();
            if (!$norma) {
                return response()->json(['ok' => false, 'message' => 'Norma no encontrada'], 404);
            }

            // Desvincular documentos y evaluaciones primero
            DB::table('documento_has_norma')->where('Norma_idNorma', $id)->delete();
            DB::table('auditoria_evaluacion')->where('norma_id', $id)->delete();
            DB::table('norma')->where('idNorma', $id)->delete();

            ActivityLog::record('eliminar', 'auditoria', "Norma eliminada: {$norma->Codigo_norma}");

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('destroyNorma: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al eliminar la norma'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Vinculación Documento ↔ Norma  (tabla: documento_has_norma)
     ───────────────────────────────────────────────────────────── */
    public function unassignNorma($documentoId, $normaId)
    {
        try {
            DB::table('documento_has_norma')
                ->where('Documento_idDocumento', $documentoId)
                ->where('Norma_idNorma', $normaId)
                ->delete();

            $doc   = DB::table('documento')->where('idDocumento', $documentoId)->value('Nombre_Doc');
            $norma = DB::table('norma')->where('idNorma', $normaId)->value('Codigo_norma');
            ActivityLog::record('desvincular', 'auditoria', "Documento «{$doc}» desvinculado de {$norma}");

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('unassignNorma: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al desvincular'], 500);
        }
    }

    public function assignNorma(Request $request, $documentoId)
    {
        try {
            $data = $request->validate(['norma_id' => 'required|integer']);

            $exists = DB::table('documento_has_norma')
                ->where('Documento_idDocumento', $documentoId)
                ->where('Norma_idNorma', $data['norma_id'])
                ->exists();

            if ($exists) {
                return response()->json(['ok' => false, 'message' => 'Este documento ya está vinculado a esa norma'], 409);
            }

            DB::table('documento_has_norma')->insert([
                'Documento_idDocumento' => $documentoId,
                'Norma_idNorma'         => $data['norma_id'],
            ]);

            // Fetch document and norma names for the log
            $doc   = DB::table('documento')->where('idDocumento', $documentoId)->value('Nombre_Doc');
            $norma = DB::table('norma')->where('idNorma', $data['norma_id'])->value('Codigo_norma');
            ActivityLog::record('asignar', 'auditoria', "Documento «{$doc}» vinculado a {$norma}");

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('assignNorma: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al vincular documento a norma'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Estadísticas de cumplimiento por norma ISO
     ───────────────────────────────────────────────────────────── */
    public function complianceStats()
    {
        try {
            $today = now()->toDateString();
            $in30  = now()->addDays(30)->toDateString();

            $normas = DB::table('norma')->orderBy('Codigo_norma')->get();
            $stats  = [];

            foreach ($normas as $norma) {
                // ── Prioridad: última auditoría con evaluaciones para esta norma ──
                $ultimaAuditoria = DB::table('auditoria_evaluacion')
                    ->where('norma_id', $norma->idNorma)
                    ->join('auditorias', 'auditorias.id', '=', 'auditoria_evaluacion.auditoria_id')
                    ->orderBy('auditorias.created_at', 'desc')
                    ->value('auditoria_evaluacion.auditoria_id');

                if ($ultimaAuditoria) {
                    $clausulas  = DB::table('auditoria_evaluacion')
                        ->where('auditoria_id', $ultimaAuditoria)
                        ->where('norma_id', $norma->idNorma)
                        ->get();

                    // Total real = cláusulas del config (igual que el modal de evaluación)
                    $configKey2 = $this->resolveConfigKey($norma->Codigo_norma);
                    $totalConfig = $configKey2 ? count(config('iso_clausulas.' . $configKey2, [])) : 0;
                    $totalCl    = $totalConfig > 0 ? $totalConfig : $clausulas->count();

                    $conformes = $clausulas->where('estado', 'conforme')->count();
                    $noConf    = $clausulas->where('estado', 'no_conforme')->count();
                    $obs       = $clausulas->where('estado', 'observacion')->count();
                    $compliance = $totalCl > 0 ? round(($conformes / $totalCl) * 100) : 0;

                    // Datos de documentos (info adicional)
                    $totalDocs  = DB::table('documento_has_norma')
                        ->where('Norma_idNorma', $norma->idNorma)->count();
                    $vigenteDocs = DB::table('documento_has_norma')
                        ->join('documento', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
                        ->where('documento_has_norma.Norma_idNorma', $norma->idNorma)
                        ->where(function ($q) use ($today) {
                            $q->whereNull('documento.Fecha_Caducidad')
                              ->orWhere('documento.Fecha_Caducidad', '>', $today);
                        })->count();

                    $stats[] = [
                        'id'           => $norma->idNorma,
                        'codigo'       => $norma->Codigo_norma,
                        'descripcion'  => $norma->Descripcion,
                        'compliance'   => $compliance,
                        'fuente'       => 'evaluacion',
                        'auditoria_id' => $ultimaAuditoria,
                        'total_cl'     => $totalCl,
                        'conformes'    => $conformes,
                        'no_conformes' => $noConf,
                        'observaciones'=> $obs,
                        'na'           => max(0, $totalCl - $conformes - $noConf - $obs), // incluye no evaluadas
                        // docs como info extra
                        'total'        => $totalDocs,
                        'vigente'      => $vigenteDocs,
                        'por_vencer'   => 0,
                        'caducado'     => max(0, $totalDocs - $vigenteDocs),
                    ];
                } else {
                    // ── Fallback: documentos vinculados ──
                    $total = DB::table('documento_has_norma')
                        ->join('documento', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
                        ->where('documento_has_norma.Norma_idNorma', $norma->idNorma)
                        ->count();

                    $vigente = DB::table('documento_has_norma')
                        ->join('documento', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
                        ->where('documento_has_norma.Norma_idNorma', $norma->idNorma)
                        ->where(function ($q) use ($today) {
                            $q->whereNull('documento.Fecha_Caducidad')
                              ->orWhere('documento.Fecha_Caducidad', '>', $today);
                        })->count();

                    $porVencer = DB::table('documento_has_norma')
                        ->join('documento', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
                        ->where('documento_has_norma.Norma_idNorma', $norma->idNorma)
                        ->whereBetween('documento.Fecha_Caducidad', [$today, $in30])
                        ->count();

                    $stats[] = [
                        'id'          => $norma->idNorma,
                        'codigo'      => $norma->Codigo_norma,
                        'descripcion' => $norma->Descripcion,
                        'compliance'  => $total > 0 ? round(($vigente / $total) * 100) : 0,
                        'fuente'      => 'documentos',
                        'total'       => $total,
                        'vigente'     => $vigente,
                        'por_vencer'  => $porVencer,
                        'caducado'    => max(0, $total - $vigente),
                    ];
                }
            }

            // Cumplimiento global = promedio de normas con datos
            $normasConDatos   = array_filter($stats, fn($n) => ($n['total'] ?? 0) > 0 || ($n['total_cl'] ?? 0) > 0);
            $globalCompliance = count($normasConDatos) > 0
                ? round(array_sum(array_column($normasConDatos, 'compliance')) / count($normasConDatos))
                : 0;

            $totalDocsGlobal   = DB::table('documento')->count();
            $vigenteDocsGlobal = DB::table('documento')
                ->where(function ($q) use ($today) {
                    $q->whereNull('Fecha_Caducidad')->orWhere('Fecha_Caducidad', '>', $today);
                })->count();

            return response()->json([
                'ok'     => true,
                'normas' => $stats,
                'global' => [
                    'compliance' => $globalCompliance,
                    'total'      => $totalDocsGlobal,
                    'vigente'    => $vigenteDocsGlobal,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('complianceStats: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al calcular cumplimiento'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Auditorías CRUD
     ───────────────────────────────────────────────────────────── */
    public function listAuditorias()
    {
        try {
            $auditorias = DB::table('auditorias')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($auditorias as $a) {
                $a->documentos = DB::table('auditoria_documento')
                    ->join('documento', 'documento.idDocumento', '=', 'auditoria_documento.documento_id')
                    ->where('auditoria_documento.auditoria_id', $a->id)
                    ->select('documento.idDocumento', 'documento.Nombre_Doc')
                    ->get();
            }

            return response()->json(['ok' => true, 'data' => $auditorias]);
        } catch (\Exception $e) {
            Log::error('listAuditorias: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar auditorías'], 500);
        }
    }

    public function storeAuditoria(Request $request)
    {
        try {
            $data = $request->validate([
                'titulo'      => 'required|string|max:255',
                'auditor'     => 'nullable|string|max:255',
                'fecha'       => 'nullable|date',
                'descripcion' => 'nullable|string',
            ]);

            $id = DB::table('auditorias')->insertGetId(array_merge($data, [
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            ActivityLog::record('crear', 'auditoria', "Auditoría creada: {$data['titulo']}");

            $a = DB::table('auditorias')->where('id', $id)->first();
            return response()->json(['ok' => true, 'auditoria' => $a], 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('storeAuditoria: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al crear auditoría'], 500);
        }
    }

    public function destroyAuditoria($id)
    {
        try {
            DB::table('auditoria_documento')->where('auditoria_id', $id)->delete();
            DB::table('auditorias')->where('id', $id)->delete();
            ActivityLog::record('eliminar', 'auditoria', "Auditoría eliminada ID: $id");
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('destroyAuditoria: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al eliminar auditoría'], 500);
        }
    }

    public function attachDocumentoToAuditoria(Request $request, $auditoriaId)
    {
        try {
            $request->validate(['documento_id' => 'required|integer']);

            $exists = DB::table('auditoria_documento')
                ->where('auditoria_id', $auditoriaId)
                ->where('documento_id', $request->documento_id)
                ->exists();

            if ($exists) {
                return response()->json(['ok' => false, 'message' => 'El documento ya está en esta auditoría'], 409);
            }

            DB::table('auditoria_documento')->insert([
                'auditoria_id' => $auditoriaId,
                'documento_id' => $request->documento_id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('attachDocumentoToAuditoria: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al adjuntar documento'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Hallazgos
     ───────────────────────────────────────────────────────────── */
    public function listHallazgos()
    {
        try {
            $hallazgos = DB::table('hallazgos')->orderBy('created_at', 'desc')->get();
            return response()->json(['ok' => true, 'data' => $hallazgos]);
        } catch (\Exception $e) {
            Log::error('listHallazgos: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al listar hallazgos'], 500);
        }
    }

    public function storeHallazgo(Request $request)
    {
        try {
            $data = $request->validate([
                'titulo'      => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'prioridad'   => 'nullable|in:alta,media,baja',
            ]);

            $id = DB::table('hallazgos')->insertGetId(array_merge($data, [
                'created_by' => auth()->id() ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            ActivityLog::record('crear', 'auditoria', "Hallazgo creado: {$data['titulo']}");

            $h = DB::table('hallazgos')->where('id', $id)->first();
            return response()->json(['ok' => true, 'hallazgo' => $h]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('storeHallazgo: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al guardar hallazgo'], 500);
        }
    }

    public function updateHallazgo(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'titulo'      => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'prioridad'   => 'nullable|in:alta,media,baja',
            ]);

            DB::table('hallazgos')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            ActivityLog::record('actualizar', 'auditoria', "Hallazgo actualizado ID: $id");
            $h = DB::table('hallazgos')->where('id', $id)->first();
            return response()->json(['ok' => true, 'hallazgo' => $h]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('updateHallazgo: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al actualizar hallazgo'], 500);
        }
    }

    public function deleteHallazgo($id)
    {
        try {
            DB::table('hallazgos')->where('id', $id)->delete();
            ActivityLog::record('eliminar', 'auditoria', "Hallazgo eliminado ID: $id");
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('deleteHallazgo: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al eliminar hallazgo'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Evaluación de cláusulas ISO por auditoría
     ───────────────────────────────────────────────────────────── */

    /**
     * Mapea cualquier código de norma (ej. "ISO 9001:2015", "ISO 27001:NORMA 1")
     * a la clave del config iso_clausulas ('ISO 9001', 'ISO 14001', 'ISO 27001').
     */
    private function resolveConfigKey(string $codigoNorma): ?string
    {
        if (str_contains($codigoNorma, '9001'))  return 'ISO 9001';
        if (str_contains($codigoNorma, '14001')) return 'ISO 14001';
        if (str_contains($codigoNorma, '27001')) return 'ISO 27001';
        return null;
    }

    /**
     * Devuelve las cláusulas de la norma indicada con el estado
     * evaluado para la auditoría dada (o 'na' si aún no evaluada).
     */
    public function getEvaluacion($auditoriaId, $normaId)
    {
        try {
            // Obtener nombre de norma para buscar en el config
            $norma = DB::table('norma')->where('idNorma', $normaId)->first();
            if (!$norma) {
                return response()->json(['ok' => false, 'message' => 'Norma no encontrada'], 404);
            }

            // Mapeo flexible: "ISO 9001:2015" → 'ISO 9001'
            $configKey       = $this->resolveConfigKey($norma->Codigo_norma);
            $clausulasConfig = collect($configKey ? config('iso_clausulas.' . $configKey, []) : []);

            // Evaluaciones ya guardadas en BD
            $evaluadas = DB::table('auditoria_evaluacion')
                ->where('auditoria_id', $auditoriaId)
                ->where('norma_id', $normaId)
                ->get()
                ->keyBy('clausula_codigo');

            if ($clausulasConfig->isNotEmpty()) {
                // Norma reconocida: merge config + estados guardados
                $resultado = $clausulasConfig->map(function ($cl) use ($evaluadas) {
                    $ev = $evaluadas->get($cl['codigo']);
                    return [
                        'clausula_codigo'  => $cl['codigo'],
                        'clausula_titulo'  => $cl['titulo'],
                        'estado'           => $ev ? $ev->estado     : 'na',
                        'comentario'       => $ev ? $ev->comentario : '',
                        'id'               => $ev ? $ev->id         : null,
                    ];
                });
            } else {
                // Norma sin config predefinida: mostrar solo lo ya evaluado en BD
                $resultado = $evaluadas->map(function ($ev) {
                    return [
                        'clausula_codigo'  => $ev->clausula_codigo,
                        'clausula_titulo'  => $ev->clausula_titulo,
                        'estado'           => $ev->estado,
                        'comentario'       => $ev->comentario ?? '',
                        'id'               => $ev->id,
                    ];
                })->values();
            }

            // Calcular resumen de esta norma
            $total      = $resultado->count();
            $conformes  = $resultado->where('estado', 'conforme')->count();
            $noConformes= $resultado->where('estado', 'no_conforme')->count();
            $obs        = $resultado->where('estado', 'observacion')->count();
            $pct        = $total > 0 ? round(($conformes / $total) * 100) : 0;

            return response()->json([
                'ok'         => true,
                'norma'      => $norma->Codigo_norma,
                'has_config' => !is_null($configKey),
                'clausulas'  => $resultado->values(),
                'resumen'    => [
                    'total'        => $total,
                    'conformes'    => $conformes,
                    'no_conformes' => $noConformes,
                    'observaciones'=> $obs,
                    'na'           => $total - ($conformes + $noConformes + $obs),
                    'pct'          => $pct,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('getEvaluacion: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al obtener evaluación'], 500);
        }
    }

    /**
     * Guarda o actualiza la evaluación de UNA cláusula.
     */
    public function saveEvaluacion(Request $request, $auditoriaId)
    {
        try {
            $data = $request->validate([
                'norma_id'        => 'required|integer',
                'clausula_codigo' => 'required|string|max:20',
                'clausula_titulo' => 'required|string|max:255',
                'estado'          => 'required|in:conforme,no_conforme,observacion,na',
                'comentario'      => 'nullable|string|max:1000',
            ]);

            DB::table('auditoria_evaluacion')->updateOrInsert(
                [
                    'auditoria_id'    => $auditoriaId,
                    'norma_id'        => $data['norma_id'],
                    'clausula_codigo' => $data['clausula_codigo'],
                ],
                [
                    'clausula_titulo' => $data['clausula_titulo'],
                    'estado'          => $data['estado'],
                    'comentario'      => $data['comentario'] ?? null,
                    'updated_at'      => now(),
                    'created_at'      => now(),
                ]
            );

            ActivityLog::record(
                'evaluar',
                'auditoria',
                "Cláusula {$data['clausula_codigo']} → {$data['estado']} (auditoría #{$auditoriaId})"
            );

            return response()->json(['ok' => true]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => $ve->errors()], 422);
        } catch (\Exception $e) {
            Log::error('saveEvaluacion: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al guardar evaluación'], 500);
        }
    }

    /**
     * Devuelve el resumen de cumplimiento por cláusula de TODAS las normas
     * para una auditoría (para mostrar en la cabecera / badge).
     */
    public function resumenEvaluacion($auditoriaId)
    {
        try {
            $normas = DB::table('norma')->orderBy('Codigo_norma')->get();
            $resumen = [];

            foreach ($normas as $norma) {
                $configKey       = $this->resolveConfigKey($norma->Codigo_norma);
                $clausulasConfig = collect($configKey ? config('iso_clausulas.' . $configKey, []) : []);
                $total = $clausulasConfig->count();
                if ($total === 0) continue;

                $evaluadas = DB::table('auditoria_evaluacion')
                    ->where('auditoria_id', $auditoriaId)
                    ->where('norma_id', $norma->idNorma)
                    ->get();

                $conformes   = $evaluadas->where('estado', 'conforme')->count();
                $pct         = $total > 0 ? round(($conformes / $total) * 100) : 0;

                $resumen[] = [
                    'norma_id'  => $norma->idNorma,
                    'norma'     => $norma->Codigo_norma,
                    'total'     => $total,
                    'evaluadas' => $evaluadas->count(),
                    'conformes' => $conformes,
                    'pct'       => $pct,
                ];
            }

            return response()->json(['ok' => true, 'data' => $resumen]);
        } catch (\Exception $e) {
            Log::error('resumenEvaluacion: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al obtener resumen'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Finalizar auditoría y enviar email con resultados
     ───────────────────────────────────────────────────────────── */
    public function finalizarAuditoria($auditoriaId)
    {
        try {
            $auditoria = DB::table('auditorias')->where('id', $auditoriaId)->first();
            if (!$auditoria) {
                return response()->json(['ok' => false, 'message' => 'Auditoría no encontrada'], 404);
            }

            // Construir resumen por norma (igual que resumenEvaluacion)
            $normas  = DB::table('norma')->orderBy('Codigo_norma')->get();
            $resumen = [];
            $pctsGlobal = [];

            foreach ($normas as $norma) {
                $configKey       = $this->resolveConfigKey($norma->Codigo_norma);
                $clausulasConfig = collect($configKey ? config('iso_clausulas.' . $configKey, []) : []);
                $total = $clausulasConfig->count();
                if ($total === 0) continue;

                $evaluadas     = DB::table('auditoria_evaluacion')
                    ->where('auditoria_id', $auditoriaId)
                    ->where('norma_id', $norma->idNorma)
                    ->get();

                $conformes     = $evaluadas->where('estado', 'conforme')->count();
                $no_conformes  = $evaluadas->where('estado', 'no_conforme')->count();
                $observaciones = $evaluadas->where('estado', 'observacion')->count();
                $na            = $total - $evaluadas->count() + $evaluadas->where('estado', 'na')->count();
                $pct           = round(($conformes / $total) * 100);

                // IMPORTANTE: array con strings, no objetos (el blade no puede renderizar stdClass)
                $resumen[] = [
                    'norma'         => $norma->Codigo_norma,
                    'total'         => $total,
                    'conformes'     => $conformes,
                    'no_conformes'  => $no_conformes,
                    'observaciones' => $observaciones,
                    'na'            => $na,
                    'pct'           => $pct,
                ];
                $pctsGlobal[] = $pct;
            }

            $pctGlobal = count($pctsGlobal) > 0 ? (int) round(array_sum($pctsGlobal) / count($pctsGlobal)) : 0;

            // Enviar email a todos los administradores activos
            $admins = DB::table('usuario')
                ->join('usuario_has_rol', 'usuario.idUsuario', '=', 'usuario_has_rol.Usuario_idUsuario')
                ->join('rol', 'rol.idRol', '=', 'usuario_has_rol.Rol_idRol')
                ->where('rol.Nombre_rol', 'Administrador')
                ->where('usuario.Estado_idEstado', '!=', 3)
                ->whereNotNull('usuario.Correo')
                ->pluck('usuario.Correo')
                ->unique();

            $enviados = 0;
            $errores  = [];
            foreach ($admins as $correo) {
                try {
                    Mail::to($correo)->send(new AuditoriaResultadoAlert($auditoria, $resumen, $pctGlobal));
                    $enviados++;
                    Log::info("finalizarAuditoria: email enviado a {$correo}");
                } catch (\Exception $e) {
                    $errores[] = $correo . ': ' . $e->getMessage();
                    Log::error("finalizarAuditoria email fallo {$correo}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                }
            }

            ActivityLog::record(
                'finalizar',
                'auditoria',
                "Auditoría #{$auditoriaId} '{$auditoria->titulo}' finalizada — {$pctGlobal}% cumplimiento — notificado a {$enviados} admin(s)"
            );

            return response()->json([
                'ok'         => true,
                'pct_global' => $pctGlobal,
                'enviados'   => $enviados,
                'errores'    => $errores,
                'message'    => "Auditoría finalizada. Notificación enviada a {$enviados} administrador(es)." .
                                (count($errores) > 0 ? ' (' . count($errores) . ' fallo(s))' : ''),
            ]);
        } catch (\Exception $e) {
            Log::error('finalizarAuditoria: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al finalizar auditoría'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     |  Reporte de auditoría (datos para impresión del cliente)
     ───────────────────────────────────────────────────────────── */
    public function generatePdf($auditoriaId)
    {
        try {
            $auditoria = DB::table('auditorias')->where('id', $auditoriaId)->first();
            if (!$auditoria) {
                return response()->json(['ok' => false, 'message' => 'Auditoría no encontrada'], 404);
            }

            $documentos = DB::table('auditoria_documento')
                ->join('documento', 'documento.idDocumento', '=', 'auditoria_documento.documento_id')
                ->where('auditoria_documento.auditoria_id', $auditoriaId)
                ->select('documento.idDocumento', 'documento.Nombre_Doc', 'documento.Fecha_Caducidad')
                ->get();

            $hallazgos = DB::table('hallazgos')
                ->orderBy('prioridad')
                ->orderBy('created_at', 'desc')
                ->get();

            $compliance = $this->complianceStats()->getData(true);

            return response()->json([
                'ok'         => true,
                'auditoria'  => $auditoria,
                'documentos' => $documentos,
                'hallazgos'  => $hallazgos,
                'compliance' => $compliance,
            ]);
        } catch (\Exception $e) {
            Log::error('generatePdf: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Error al generar reporte'], 500);
        }
    }
}
