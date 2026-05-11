<?php

namespace App\Mail;

use App\Models\OrdenJob;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Alerta enviada a los correos de CorreoAlerta cuando un técnico detecta fuga de gas o exceso de capacidad
 * durante una inspección de Fraudes.
 */
class IncidenciaDetectada extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, string> $incidencias */
    public function __construct(
        public OrdenJob $orden,
        public array $incidencias,
        public ?string $fotoDetectorFuga = null,
        public ?string $fotoDetectorData = null,
        public ?string $fotoDetectorMimeType = null,
        public ?string $fotoDetectorNombre = null,
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
                'fotoDetectorUrl' => $this->fotoDetectorFuga ? Storage::disk('s3')->url($this->fotoDetectorFuga) : null,
                'fotoDetectorData' => $this->fotoDetectorData,
                'fotoDetectorMimeType' => $this->fotoDetectorMimeType,
                'fotoDetectorNombre' => $this->fotoDetectorNombre,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
