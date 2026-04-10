<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class DocumentosVencimientoAlert extends Mailable
{
    use Queueable, SerializesModels;

    public array $porVencer;
    public array $caducados;
    public string $appUrl;

    public function __construct(array $porVencer, array $caducados)
    {
        $this->porVencer = $porVencer;
        $this->caducados = $caducados;
        $this->appUrl    = config('app.url');
    }

    public function envelope(): Envelope
    {
        $total = count($this->porVencer) + count($this->caducados);
        return new Envelope(
            from:    new Address(config('mail.from.address'), 'DracoCert - Gestión ISO'),
            replyTo: [new Address(config('mail.from.address'), 'DracoCert')],
            subject: "[DracoCert] Alerta: {$total} documento(s) requieren atención",
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
        return new Content(markdown: 'emails.documentos_vencimiento');
    }

    public function attachments(): array { return []; }
}
