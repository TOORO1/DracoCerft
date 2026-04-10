<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResumenSemanalAlert;
use App\Models\ActivityLog;

class ResumenSemanal extends Command
{
    protected $signature   = 'dracocert:resumen-semanal {--dry-run : Solo mostrar sin enviar}';
    protected $description = 'Envía resumen semanal ISO a administradores (cada lunes 8 AM)';

    public function handle(): int
    {
        $today     = now()->toDateString();
        $in30      = now()->addDays(30)->toDateString();
        $weekStart = now()->subWeek()->toDateString();

        // ── KPIs generales ──────────────────────────────────────────
        $kpis = [
            'total_docs'  => DB::table('documento')->count(),
            'vigentes'    => DB::table('documento')
                ->where(fn($q) => $q->whereNull('Fecha_Caducidad')->orWhere('Fecha_Caducidad', '>=', $today))
                ->count(),
            'usuarios'    => DB::table('usuario')->where('Estado_idEstado', '!=', 3)->count(),
        ];

        // ── Auditorías de la semana ──────────────────────────────────
        $auditorias = DB::table('auditorias')
            ->where('created_at', '>=', $weekStart)
            ->orderBy('created_at', 'desc')
            ->get();

        // ── Hallazgos nuevos de la semana ───────────────────────────
        $hallazgos = DB::table('hallazgos')
            ->where('created_at', '>=', $weekStart)
            ->orderBy('prioridad')
            ->get();

        // ── Documentos caducados y por vencer ───────────────────────
        $caducados = DB::table('documento')
            ->whereNotNull('Fecha_Caducidad')
            ->where('Fecha_Caducidad', '<', $today)
            ->select('Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->get();

        $porVencer = DB::table('documento')
            ->whereBetween('Fecha_Caducidad', [$today, $in30])
            ->select('Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->get();

        // ── Cumplimiento global (promedio de normas con evaluación) ──
        $normas     = DB::table('norma')->get();
        $porcentajes = [];
        foreach ($normas as $norma) {
            $total     = DB::table('auditoria_evaluacion')->where('norma_id', $norma->idNorma)->count();
            $conformes = DB::table('auditoria_evaluacion')->where('norma_id', $norma->idNorma)->where('estado', 'conforme')->count();
            if ($total > 0) $porcentajes[] = round(($conformes / $total) * 100);
        }
        $pctGlobal = count($porcentajes) > 0 ? (int) round(array_sum($porcentajes) / count($porcentajes)) : 0;

        // ── Mostrar resumen en consola ───────────────────────────────
        $this->info("Auditorías esta semana : {$auditorias->count()}");
        $this->info("Hallazgos esta semana  : {$hallazgos->count()}");
        $this->info("Docs caducados         : {$caducados->count()}");
        $this->info("Docs por vencer        : {$porVencer->count()}");
        $this->info("Cumplimiento global    : {$pctGlobal}%");

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Email NO enviado.');
            return 0;
        }

        // ── Destinatarios: Administradores activos con correo ────────
        $admins = DB::table('usuario')
            ->join('usuario_has_rol', 'usuario.idUsuario', '=', 'usuario_has_rol.Usuario_idUsuario')
            ->join('rol', 'rol.idRol', '=', 'usuario_has_rol.Rol_idRol')
            ->where('rol.Nombre_rol', 'Administrador')
            ->where('usuario.Estado_idEstado', '!=', 3)
            ->whereNotNull('usuario.Correo')
            ->pluck('usuario.Correo')
            ->unique();

        if ($admins->isEmpty()) {
            $this->warn('No se encontraron administradores con correo.');
            return 1;
        }

        $mailable = new ResumenSemanalAlert(
            $kpis,
            $auditorias->toArray(),
            $hallazgos->toArray(),
            $porVencer->toArray(),
            $caducados->toArray(),
            $pctGlobal
        );

        $enviados = 0;
        foreach ($admins as $correo) {
            try {
                Mail::to($correo)->send($mailable);
                $this->info("Resumen enviado a: {$correo}");
                $enviados++;
            } catch (\Exception $e) {
                $this->error("Fallo {$correo}: {$e->getMessage()}");
            }
        }

        if ($enviados > 0) {
            ActivityLog::record('resumen_semanal', 'sistema', "Resumen semanal enviado a {$enviados} admin(s)");
        }

        return 0;
    }
}
