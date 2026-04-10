<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AuditoriaResultadoAlert extends Mailable
{
    use Queueable, SerializesModels;

    public object $auditoria;
    public array  $resumen;
    public int    $pctGlobal;
    public string $appUrl;

    public function __construct(object $auditoria, array $resumen, int $pctGlobal)
    {
        $this->auditoria = $auditoria;
        $this->resumen   = $resumen;
        $this->pctGlobal = $pctGlobal;
        $this->appUrl    = config('app.url');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address(config('mail.from.address'), 'DracoCert - Gestión ISO'),
            replyTo: [new Address(config('mail.from.address'), 'DracoCert')],
            subject: "[DracoCert] Auditoría completada: {$this->auditoria->titulo} — {$this->pctGlobal}% cumplimiento",
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
        return new Content(markdown: 'emails.auditoria_resultado');
    }

    public function attachments(): array { return []; }
}
