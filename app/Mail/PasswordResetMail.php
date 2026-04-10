<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public string $nombreUsuario;
    public int    $expiraMinutos;

    public function __construct(string $resetUrl, string $nombreUsuario, int $expiraMinutos = 60)
    {
        $this->resetUrl      = $resetUrl;
        $this->nombreUsuario = $nombreUsuario;
        $this->expiraMinutos = $expiraMinutos;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new Address(config('mail.from.address'), 'DracoCert - Seguridad'),
            replyTo: [new Address(config('mail.from.address'), 'DracoCert')],
            subject: '[DracoCert] Restablecer contraseña',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Mailer'   => 'DracoCert v1.0',
                'X-Priority' => '1',   // Alta prioridad — es una acción de seguridad
            ],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.password_reset');
    }

    public function attachments(): array { return []; }
}
