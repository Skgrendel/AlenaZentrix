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

/**
 * Notificación enviada al técnico cuando el coordinador aprueba el reporte y marca la orden como completada.
 */
class OrdenCompletada extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  personals|null  $tecnico  Puede ser null en órdenes de Vigilancia sin asignado fijo.
     */
    public function __construct(
        public OrdenJob $orden,
        public ?personals $tecnico,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Orden Completada: '.$this->orden->numero_orden,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orden-completada',
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
