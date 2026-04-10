<?php
// File: app/Http/Controllers/ReporteController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DocumentosExport;
use App\Exports\HallazgosExport;
use App\Exports\CumplimientoExport;
use Illuminate\Support\Collection;
use App\Models\ActivityLog;

class ReporteController extends Controller
{
    /* ─────────────────────────────────────────────────────────────
     |  Helpers privados: recolección de datos
     ───────────────────────────────────────────────────────────── */

    /**
     * Resuelve la clave del config iso_clausulas a partir del código de norma.
     * Ej: "ISO 9001:2015" → 'ISO 9001'
     */
    private function resolveConfigKey(string $codigo): ?string
    {
        if (str_contains($codigo, '9001'))  return 'ISO 9001';
        if (str_contains($codigo, '14001')) return 'ISO 14001';
        if (str_contains($codigo, '27001')) return 'ISO 27001';
        return null;
    }

    /**
     * Calcula el cumplimiento por norma ISO.
     * Prioridad: evaluación de cláusulas (auditoria_evaluacion).
     * Fallback: vigencia de documentos vinculados.
     */
    private function getComplianceData(): array
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();
        $normas = DB::table('norma')->orderBy('Codigo_norma')->get();

        $isoData = [];

        foreach ($normas as $norma) {
            // ── Intentar con la auditoría más reciente que tenga evaluaciones ──
            $ultimaAuditoria = DB::table('auditoria_evaluacion')
                ->where('norma_id', $norma->idNorma)
                ->join('auditorias', 'auditorias.id', '=', 'auditoria_evaluacion.auditoria_id')
                ->orderBy('auditorias.created_at', 'desc')
                ->value('auditoria_evaluacion.auditoria_id');

            if ($ultimaAuditoria) {
                // Fuente primaria: evaluación de cláusulas ISO
                $configKey   = $this->resolveConfigKey($norma->Codigo_norma);
                $totalConfig = $configKey ? count(config('iso_clausulas.' . $configKey, [])) : 0;

                $evaluadas   = DB::table('auditoria_evaluacion')
                    ->where('auditoria_id', $ultimaAuditoria)
                    ->where('norma_id', $norma->idNorma)
                    ->get();

                // Denominador = total config; si la norma no tiene config usar evaluadas
                $totalCl     = $totalConfig > 0 ? $totalConfig : $evaluadas->count();
                $conformes   = $evaluadas->where('estado', 'conforme')->count();
                $noConformes = $evaluadas->where('estado', 'no_conforme')->count();
                $obs         = $evaluadas->where('estado', 'observacion')->count();
                $compliance  = $totalCl > 0 ? round(($conformes / $totalCl) * 100) : 0;

                $isoData[] = [
                    'norma'         => $norma->Codigo_norma,
                    'fuente'        => 'evaluacion',
                    'total_cl'      => $totalCl,
                    'conformes'     => $conformes,
                    'no_conformes'  => $noConformes,
                    'observaciones' => $obs,
                    'na'            => max(0, $totalCl - $conformes - $noConformes - $obs),
                    'compliance'    => $compliance,
                    // Campos de docs (vacíos cuando la fuente es evaluación)
                    'total'         => 0,
                    'vigente'       => 0,
                    'por_vencer'    => 0,
                    'caducado'      => 0,
                ];
            } else {
                // Fallback: documentos vigentes vinculados a esta norma
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

                $isoData[] = [
                    'norma'         => $norma->Codigo_norma,
                    'fuente'        => 'documentos',
                    'total'         => $total,
                    'vigente'       => $vigente,
                    'por_vencer'    => $porVencer,
                    'caducado'      => max(0, $total - $vigente),
                    'compliance'    => $total > 0 ? round(($vigente / $total) * 100) : 0,
                    // Campos de cláusulas (vacíos cuando la fuente es documentos)
                    'total_cl'      => 0,
                    'conformes'     => 0,
                    'no_conformes'  => 0,
                    'observaciones' => 0,
                    'na'            => 0,
                ];
            }
        }

        return $isoData;
    }

    private function getDocumentosData(): \Illuminate\Support\Collection
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();

        return DB::table('documento')
            ->leftJoin('version', 'documento.Version_idVersion', '=', 'version.idVersion')
            ->leftJoin('tipo_documento', 'tipo_documento.idTipo_Documento', '=', 'documento.Tipo_Documento_idTipo_Documento')
            ->leftJoin('documento_has_norma', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
            ->leftJoin('norma', 'norma.idNorma', '=', 'documento_has_norma.Norma_idNorma')
            ->select(
                'documento.idDocumento',
                'documento.Nombre_Doc',
                DB::raw("COALESCE(tipo_documento.Nombre_Tipo, 'General') as tipo"),
                'version.numero_Version',
                'documento.Fecha_creacion',
                'documento.Fecha_Caducidad',
                DB::raw("COALESCE(norma.Codigo_norma, 'General') as norma"),
                DB::raw("
                    CASE
                        WHEN documento.Fecha_Caducidad IS NULL THEN 'Vigente'
                        WHEN documento.Fecha_Caducidad > '{$in30}' THEN 'Vigente'
                        WHEN documento.Fecha_Caducidad BETWEEN '{$today}' AND '{$in30}' THEN 'Por Vencer'
                        ELSE 'Caducado'
                    END as estado
                ")
            )
            ->orderBy('documento.idDocumento', 'desc')
            ->get();
    }

    private function getHallazgosData(): \Illuminate\Support\Collection
    {
        // hallazgos table: id, titulo, descripcion, prioridad, created_by, created_at, updated_at
        return DB::table('hallazgos')
            ->select('id', 'titulo', 'descripcion', 'prioridad', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /* ─────────────────────────────────────────────────────────────
     |  PDF: Reporte de Cumplimiento ISO
     ───────────────────────────────────────────────────────────── */
    public function pdfCumplimiento()
    {
        $isoData    = $this->getComplianceData();
        $documentos = $this->getDocumentosData();
        $today      = now()->toDateString();
        $in30       = now()->addDays(30)->toDateString();

        $totalDocs     = DB::table('documento')->count();
        $docsVigentes  = DB::table('documento')
            ->where(function ($q) use ($today) {
                $q->whereNull('Fecha_Caducidad')->orWhere('Fecha_Caducidad', '>', $today);
            })->count();
        $docsPorVencer = DB::table('documento')->whereBetween('Fecha_Caducidad', [$today, $in30])->count();
        $docsCaducados = DB::table('documento')
            ->whereNotNull('Fecha_Caducidad')->where('Fecha_Caducidad', '<', $today)->count();

        // Cumplimiento global: promedio de normas con evaluación ISO; si no hay, usa vigencia de docs
        $normasConEval = array_filter($isoData, fn($n) => $n['fuente'] === 'evaluacion');
        if (count($normasConEval) > 0) {
            $globalCompliance = (int) round(
                array_sum(array_column($normasConEval, 'compliance')) / count($normasConEval)
            );
        } else {
            $globalCompliance = $totalDocs > 0 ? round(($docsVigentes / $totalDocs) * 100) : 0;
        }

        $docsExpiring = DB::table('documento')
            ->whereBetween('Fecha_Caducidad', [$today, $in30])
            ->select('Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->limit(10)
            ->get();

        ActivityLog::record('Exportar PDF', 'Reportes', 'Generó reporte PDF de cumplimiento ISO');

        $pdf = Pdf::loadView('reports.compliance_pdf', compact(
            'isoData', 'documentos', 'docsExpiring',
            'totalDocs', 'docsVigentes', 'docsPorVencer', 'docsCaducados', 'globalCompliance'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('Reporte_Cumplimiento_ISO_' . now()->format('Y-m-d') . '.pdf');
    }

    /* ─────────────────────────────────────────────────────────────
     |  PDF: Reporte de Hallazgos de Auditoría
     ───────────────────────────────────────────────────────────── */
    public function pdfHallazgos()
    {
        $hallazgos   = $this->getHallazgosData();
        $auditorias  = DB::table('auditorias')->orderBy('fecha', 'desc')->get();

        $porPrioridad = [
            'Alta'  => $hallazgos->filter(fn($h) => strtolower($h->prioridad ?? '') === 'alta')->count(),
            'Media' => $hallazgos->filter(fn($h) => strtolower($h->prioridad ?? '') === 'media')->count(),
            'Baja'  => $hallazgos->filter(fn($h) => strtolower($h->prioridad ?? '') === 'baja')->count(),
        ];

        ActivityLog::record('Exportar PDF', 'Reportes', 'Generó reporte PDF de hallazgos de auditoría');

        $pdf = Pdf::loadView('reports.hallazgos_pdf', compact(
            'hallazgos', 'auditorias', 'porPrioridad'
        ))->setPaper('a4', 'portrait');

        return $pdf->download('Reporte_Hallazgos_' . now()->format('Y-m-d') . '.pdf');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Excel: Inventario de Documentos
     ───────────────────────────────────────────────────────────── */
    public function excelDocumentos()
    {
        ActivityLog::record('Exportar Excel', 'Reportes', 'Exportó inventario de documentos a Excel');
        return Excel::download(new DocumentosExport, 'Inventario_Documentos_' . now()->format('Y-m-d') . '.xlsx');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Excel: Hallazgos de Auditoría
     ───────────────────────────────────────────────────────────── */
    public function excelHallazgos()
    {
        ActivityLog::record('Exportar Excel', 'Reportes', 'Exportó hallazgos de auditoría a Excel');
        return Excel::download(new HallazgosExport, 'Hallazgos_Auditoria_' . now()->format('Y-m-d') . '.xlsx');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Excel: Cumplimiento por Norma ISO
     ───────────────────────────────────────────────────────────── */
    public function excelCumplimiento()
    {
        $isoData = collect($this->getComplianceData());
        ActivityLog::record('Exportar Excel', 'Reportes', 'Exportó resumen de cumplimiento ISO a Excel');
        return Excel::download(new CumplimientoExport($isoData), 'Cumplimiento_ISO_' . now()->format('Y-m-d') . '.xlsx');
    }
}
