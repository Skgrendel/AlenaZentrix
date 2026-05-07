<?php

namespace App\Mail;

use App\Models\OrdenJob;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncidenciaDetectada extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, string> $incidencias */
    public function __construct(
        public OrdenJob $orden,
        public array $incidencias,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Incidencia Detectada — Orden '.$this->orden->numero_orden,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidencia-detectada',
            with: [
                'orden' => $this->orden,
                'incidencias' => $this->incidencias,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
