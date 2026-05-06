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

class OrdenRechazada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrdenJob $orden,
        public personals $tecnico,
        public string $comentarios,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte Rechazado: '.$this->orden->numero_orden,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orden-rechazada',
            with: [
                'orden' => $this->orden,
                'tecnico' => $this->tecnico,
                'comentarios' => $this->comentarios,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
