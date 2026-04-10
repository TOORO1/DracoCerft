<?php
// File: app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        return view('Dashboard');
    }

    /* ─────────────────────────────────────────────────────────────
     |  Stats completas para el Dashboard
     ───────────────────────────────────────────────────────────── */
    public function stats()
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();

        /* ── KPIs ────────────────────────────────────────────── */
        $totalDocumentos     = DB::table('documento')->count();
        $totalUsuarios       = DB::table('usuario')->where('Estado_idEstado', '!=', 3)->count();
        $totalCapacitaciones = DB::table('capacitacion')->count();
        $hallazgosPendientes = DB::table('hallazgos')->count();

        $docsVigentes  = DB::table('documento')
            ->where(function ($q) use ($today) {
                $q->whereNull('Fecha_Caducidad')->orWhere('Fecha_Caducidad', '>', $today);
            })->count();

        $docsPorVencer = DB::table('documento')
            ->whereBetween('Fecha_Caducidad', [$today, $in30])
            ->count();

        $docsCaducados = DB::table('documento')
            ->whereNotNull('Fecha_Caducidad')
            ->where('Fecha_Caducidad', '<', $today)
            ->count();

        /* ── Cumplimiento ISO real por norma ─────────────────── */
        // Prioridad: última auditoría evaluada por norma (auditoria_evaluacion)
        // Fallback: % documentos vigentes (documento_has_norma) si no hay evaluaciones
        $normas  = DB::table('norma')->orderBy('Codigo_norma')->get();
        $isoData = [];

        foreach ($normas as $norma) {
            /* ── Intentar obtener evaluación de cláusulas ─── */
            // Auditoría más reciente que tenga evaluaciones para esta norma
            $ultimaAuditoria = DB::table('auditoria_evaluacion')
                ->where('norma_id', $norma->idNorma)
                ->join('auditorias', 'auditorias.id', '=', 'auditoria_evaluacion.auditoria_id')
                ->orderBy('auditorias.created_at', 'desc')
                ->value('auditoria_evaluacion.auditoria_id');

            if ($ultimaAuditoria) {
                $clausulas   = DB::table('auditoria_evaluacion')
                    ->where('auditoria_id', $ultimaAuditoria)
                    ->where('norma_id', $norma->idNorma)
                    ->get();

                // Total real = cláusulas del config (misma lógica que el modal de evaluación)
                $configKey  = null;
                $codigo     = $norma->Codigo_norma;
                if (str_contains($codigo, '9001'))  $configKey = 'ISO 9001';
                elseif (str_contains($codigo, '14001')) $configKey = 'ISO 14001';
                elseif (str_contains($codigo, '27001')) $configKey = 'ISO 27001';

                $totalConfig = $configKey ? count(config('iso_clausulas.' . $configKey, [])) : 0;
                $totalCl    = $totalConfig > 0 ? $totalConfig : $clausulas->count();

                $conformes  = $clausulas->where('estado', 'conforme')->count();
                $noConf     = $clausulas->where('estado', 'no_conforme')->count();
                $obs        = $clausulas->where('estado', 'observacion')->count();
                $naCount    = $totalCl - $conformes - $noConf - $obs; // no guardadas = NA
                $pct        = $totalCl > 0 ? round(($conformes / $totalCl) * 100) : 0;

                // Datos de documentos (para mostrar como info adicional)
                $totalDocs = DB::table('documento_has_norma')
                    ->where('Norma_idNorma', $norma->idNorma)->count();
                $vigenteDocs = DB::table('documento_has_norma')
                    ->join('documento', 'documento.idDocumento', '=', 'documento_has_norma.Documento_idDocumento')
                    ->where('documento_has_norma.Norma_idNorma', $norma->idNorma)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('documento.Fecha_Caducidad')
                          ->orWhere('documento.Fecha_Caducidad', '>', $today);
                    })->count();

                $isoData[] = [
                    'norma'        => $norma->Codigo_norma,
                    'compliance'   => $pct,
                    'fuente'       => 'evaluacion',
                    'auditoria_id' => $ultimaAuditoria,
                    // cláusulas (total = config completo, no solo guardadas)
                    'total_cl'    => $totalCl,
                    'conformes'   => $conformes,
                    'no_conformes'=> $noConf,
                    'observaciones'=> $obs,
                    'na'          => $naCount,  // incluye no-evaluadas
                    // docs (info extra)
                    'total'       => $totalDocs,
                    'vigente'     => $vigenteDocs,
                    'por_vencer'  => 0,
                    'caducado'    => max(0, $totalDocs - $vigenteDocs),
                ];
            } else {
                /* ── Fallback: documentos vigentes ────────── */
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
                    'norma'      => $norma->Codigo_norma,
                    'compliance' => $total > 0 ? round(($vigente / $total) * 100) : 0,
                    'fuente'     => 'documentos',
                    'total'      => $total,
                    'vigente'    => $vigente,
                    'por_vencer' => $porVencer,
                    'caducado'   => max(0, $total - $vigente),
                ];
            }
        }

        // Cumplimiento global = promedio de todas las normas con datos
        $normasConDatos = array_filter($isoData, fn($n) => ($n['total'] ?? 0) > 0 || ($n['total_cl'] ?? 0) > 0);
        $globalCompliance = count($normasConDatos) > 0
            ? round(array_sum(array_column($normasConDatos, 'compliance')) / count($normasConDatos))
            : ($totalDocumentos > 0 ? round(($docsVigentes / $totalDocumentos) * 100) : 0);

        /* ── Tendencia de actividad (últimos 7 días) ─────────── */
        // Una sola query GROUP BY en vez de 7 COUNT individuales
        $trendRaw = DB::table('activity_logs')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('fecha');

        $activityTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d       = now()->subDays($i);
            $dateStr = $d->toDateString();
            $activityTrend[] = [
                'dia'   => $d->locale('es')->isoFormat('dd'),
                'fecha' => $dateStr,
                'count' => (int) ($trendRaw->get($dateStr)?->total ?? 0),
            ];
        }

        /* ── Documentos por vencer (próximos 30 días) ────────── */
        $docsExpiring = DB::table('documento')
            ->whereBetween('Fecha_Caducidad', [$today, $in30])
            ->select('idDocumento', 'Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->limit(8)
            ->get()
            ->map(fn ($d) => [
                'id'     => $d->idDocumento,
                'nombre' => $d->Nombre_Doc,
                'vence'  => $d->Fecha_Caducidad,
                'dias'   => (int) now()->diffInDays($d->Fecha_Caducidad, false),
            ]);

        /* ── Actividad reciente ───────────────────────────────── */
        $logsRecientes = DB::table('activity_logs')
            ->leftJoin('usuario', 'usuario.idUsuario', '=', 'activity_logs.usuario_id')
            ->select(
                'activity_logs.*',
                DB::raw("COALESCE(activity_logs.nombre_usuario, usuario.Nombre_Usuario, 'Sistema') as nombre_usuario")
            )
            ->orderBy('activity_logs.created_at', 'desc')
            ->limit(8)
            ->get();

        /* ── Documentos recientes ─────────────────────────────── */
        $docsRecientes = DB::table('documento')
            ->leftJoin('version', 'documento.Version_idVersion', '=', 'version.idVersion')
            ->select(
                'documento.idDocumento', 'documento.Nombre_Doc',
                'documento.Fecha_creacion', 'documento.Ruta',
                'documento.Fecha_Caducidad', 'version.numero_Version'
            )
            ->orderBy('documento.idDocumento', 'desc')
            ->limit(6)
            ->get()
            ->map(fn ($d) => [
                'id'      => $d->idDocumento,
                'nombre'  => $d->Nombre_Doc,
                'fecha'   => $d->Fecha_creacion,
                'ruta'    => $d->Ruta,
                'vence'   => $d->Fecha_Caducidad,
                'version' => $d->numero_Version ? number_format($d->numero_Version, 1) : '1.0',
            ]);

        return response()->json([
            'kpis' => [
                'documentos'     => $totalDocumentos,
                'usuarios'       => $totalUsuarios,
                'capacitaciones' => $totalCapacitaciones,
                'hallazgos'      => $hallazgosPendientes,
                'vigentes'       => $docsVigentes,
                'por_vencer'     => $docsPorVencer,
                'caducados'      => $docsCaducados,
                'compliance'     => $globalCompliance,
            ],
            'iso_data'       => $isoData,
            'activity_trend' => $activityTrend,
            'docs_expiring'  => $docsExpiring,
            'logs_recientes' => $logsRecientes,
            'docs_recientes' => $docsRecientes,
        ]);
    }

    /* ─────────────────────────────────────────────────────────────
     |  Notificaciones — bell count para el Header
     ───────────────────────────────────────────────────────────── */
    /* ─────────────────────────────────────────────────────────────
     |  Disparar alertas de vencimiento manualmente (desde UI)
     ───────────────────────────────────────────────────────────── */
    public function enviarAlertasVencimiento()
    {
        try {
            $exitCode = Artisan::call('dracocert:alertar-vencimientos', ['--force' => true]);
            $output   = Artisan::output();
            return response()->json([
                'ok'      => $exitCode === 0,
                'message' => trim($output) ?: 'Proceso ejecutado.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function notifications()
    {
        // Cache de 5 minutos — el Header llama este endpoint en CADA página cargada
        $userId = auth()->id() ?? 0;
        $data   = Cache::remember("notifications_user_{$userId}", 300, function () {
            $today = now()->toDateString();
            $in30  = now()->addDays(30)->toDateString();
            $week  = now()->subDays(7)->toDateString();

            // Todos los conteos en paralelo (una sola ida a la BD por bloque)
            $expiring  = DB::table('documento')
                ->whereBetween('Fecha_Caducidad', [$today, $in30])
                ->count();
            $caducados = DB::table('documento')
                ->whereNotNull('Fecha_Caducidad')
                ->where('Fecha_Caducidad', '<', $today)
                ->count();
            $hallazgosAlta = DB::table('hallazgos')
                ->where('prioridad', 'alta')
                ->where('created_at', '>=', $week)
                ->count();
            $auditoriasSemana = DB::table('auditorias')
                ->where('created_at', '>=', $week)
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['id', 'titulo', 'auditor', 'fecha', 'created_at']);

            return [
                'ok'                => true,
                'total'             => $expiring + $caducados + $hallazgosAlta + $auditoriasSemana->count(),
                'expiring'          => $expiring,
                'caducados'         => $caducados,
                'hallazgos_alta'    => $hallazgosAlta,
                'auditorias_semana' => $auditoriasSemana,
            ];
        });

        return response()->json($data);
    }

    /**
     * Invalida el cache de notificaciones del usuario actual.
     * Llamar tras crear/eliminar documentos, hallazgos o auditorías.
     */
    public static function clearNotificationsCache(int $userId): void
    {
        Cache::forget("notifications_user_{$userId}");
    }
}
