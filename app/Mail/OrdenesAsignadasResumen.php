<?php

namespace App\Mail;

use App\Models\personals;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrdenesAsignadasResumen extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public personals $investigador,
        public Collection $ordenes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->ordenes->count()} Órdenes Asignadas — Revisión de Medidores",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ordenes-asignadas-resumen',
            with: [
                'investigador' => $this->investigador,
                'ordenes' => $this->ordenes,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
