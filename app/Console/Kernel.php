<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Alerta diaria de documentos por vencer / caducados — 8:00 AM
        $schedule->command('dracocert:alertar-vencimientos')
                 ->dailyAt('08:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/alertas_vencimiento.log'));

        // Resumen semanal — todos los lunes a las 8:00 AM
        $schedule->command('dracocert:resumen-semanal')
                 ->weeklyOn(1, '08:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/resumen_semanal.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
