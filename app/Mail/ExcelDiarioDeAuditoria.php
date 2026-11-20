<?php

namespace App\Mail;

use App\Exports\SurveyAuditoria\SurveyAuditoriaExport;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo diario con el Excel de la auditoría de suspensiones. Si ese día no hubo registros, se avisa y no se adjunta nada.
 */
class ExcelDiarioDeAuditoria extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $mensaje,
        public readonly string $empresa,
        public readonly string $desde,
        public readonly string $hasta,
        public readonly int $registros,
        public readonly int $conErrores,
        public readonly int $conAlertas,
        public readonly ?string $contenidoExcel,
        public readonly string $nombreDelArchivo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.excel-diario');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->contenidoExcel === null) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $this->contenidoExcel, $this->nombreDelArchivo)
                ->withMime(SurveyAuditoriaExport::TIPO_MIME),
        ];
    }
}
