<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ZipReportesListoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $url,
        public int $cantidad,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('ZIP de Reportes Listo — Revisión de Medidores')
            ->line("Tu archivo ZIP con {$this->cantidad} reporte(s) de Revisión de Medidores está listo.")
            ->action('Descargar ZIP', $this->url)
            ->line('El enlace expira en 60 minutos.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'zip_reportes_listo',
            'url' => $this->url,
            'cantidad' => $this->cantidad,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
