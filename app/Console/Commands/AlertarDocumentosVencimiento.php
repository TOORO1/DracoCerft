<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\DocumentosVencimientoAlert;
use App\Models\ActivityLog;

class AlertarDocumentosVencimiento extends Command
{
    protected $signature   = 'dracocert:alertar-vencimientos
                                {--force : Enviar aunque no haya documentos que alertar}
                                {--dry-run : Solo mostrar resultados sin enviar email}';

    protected $description = 'Envía alertas por email sobre documentos ISO por vencer o caducados';

    public function handle(): int
    {
        $today = now()->toDateString();
        $in30  = now()->addDays(30)->toDateString();

        // Documentos ya caducados
        $caducados = DB::table('documento')
            ->whereNotNull('Fecha_Caducidad')
            ->where('Fecha_Caducidad', '<', $today)
            ->select('idDocumento', 'Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->get();

        // Documentos por vencer en los próximos 30 días
        $porVencer = DB::table('documento')
            ->whereBetween('Fecha_Caducidad', [$today, $in30])
            ->select('idDocumento', 'Nombre_Doc', 'Fecha_Caducidad')
            ->orderBy('Fecha_Caducidad')
            ->get();

        $totalAlerta = $caducados->count() + $porVencer->count();

        $this->info("Documentos caducados   : {$caducados->count()}");
        $this->info("Documentos por vencer  : {$porVencer->count()}");

        if ($totalAlerta === 0 && !$this->option('force')) {
            $this->info('Sin alertas que enviar. Usa --force para enviar de todos modos.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Email NO enviado.');
            return 0;
        }

        // Destinatarios: todos los administradores activos
        $admins = DB::table('usuario')
            ->join('usuario_has_rol', 'usuario.idUsuario', '=', 'usuario_has_rol.Usuario_idUsuario')
            ->join('rol', 'rol.idRol', '=', 'usuario_has_rol.Rol_idRol')
            ->where('rol.Nombre_rol', 'Administrador')
            ->where('usuario.Estado_idEstado', '!=', 3)  // No eliminados
            ->whereNotNull('usuario.Correo')
            ->pluck('usuario.Correo')
            ->unique();

        if ($admins->isEmpty()) {
            $this->warn('No se encontraron administradores con correo configurado.');
            return 1;
        }

        $mailable = new DocumentosVencimientoAlert(
            $porVencer->toArray(),
            $caducados->toArray()
        );

        $enviados = 0;
        foreach ($admins as $correo) {
            try {
                Mail::to($correo)->send($mailable);
                $this->info("Email enviado a: {$correo}");
                $enviados++;
            } catch (\Exception $e) {
                $this->error("Fallo al enviar a {$correo}: {$e->getMessage()}");
            }
        }

        if ($enviados > 0) {
            ActivityLog::record(
                'alerta_email',
                'documentos',
                "Alerta de vencimiento enviada: {$caducados->count()} caducados, {$porVencer->count()} por vencer → {$enviados} admin(s)"
            );
            $this->info("Alerta enviada exitosamente a {$enviados} administrador(es).");
        }

        return 0;
    }
}
