<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ResumenSemanalAlert extends Mailable
{
    use Queueable, SerializesModels;

    public array  $kpis;
    public array  $auditorias;
    public array  $hallazgos;
    public array  $porVencer;
    public array  $caducados;
    public int    $pctGlobal;
    public string $semana;
    public string $appUrl;

    public function __construct(
        array $kpis,
        array $auditorias,
        array $hallazgos,
        array $porVencer,
        array $caducados,
        int   $pctGlobal
    ) {
        $this->kpis       = $kpis;
        $this->auditorias = $auditorias;
        $this->hallazgos  = $hallazgos;
        $this->porVencer  = $porVencer;
        $this->caducados  = $caducados;
        $this->pctGlobal  = $pctGlobal;
        $this->semana     = now()->subWeek()->format('d/m/Y') . ' – ' . now()->format('d/m/Y');
        $this->appUrl     = config('app.url');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address(config('mail.from.address'), 'DracoCert - Gestión ISO'),
            replyTo: [new Address(config('mail.from.address'), 'DracoCert')],
            subject: "[DracoCert] Resumen semanal — " . now()->format('d/m/Y'),
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailer'   => 'DracoCert v1.0',
                'X-Priority' => '3',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.resumen_semanal');
    }

    public function attachments(): array { return []; }
}
