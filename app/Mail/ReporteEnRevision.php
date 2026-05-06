<?php

namespace App\Mail;

use App\Models\OrdenJob;
use App\Models\personals;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReporteEnRevision extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrdenJob $orden,
        public personals $tecnico,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte Enviado para Revisión: '.$this->orden->numero_orden,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reporte-en-revision',
            with: [
                'orden' => $this->orden,
                'tecnico' => $this->tecnico,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
