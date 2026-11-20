<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso al celular cuando se asigna una orden de trabajo. Solo lleva el número de la OT: nada de contrato,
 * dirección ni cliente, porque el aviso se ve con el teléfono bloqueado.
 */
class OrdenAsignadaPush extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $texto  lo que dice el aviso, por ejemplo «OT 319898201»
     * @param  string  $url  a dónde lleva al tocarlo (ruta relativa)
     * @param  string  $etiqueta  agrupa avisos iguales para que no se repitan
     */
    public function __construct(
        public readonly string $texto,
        public readonly string $url,
        public readonly string $etiqueta,
    ) {}

    /** @return array<int, class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Orden de trabajo asignada')
            ->body($this->texto)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag($this->etiqueta)
            ->data(['url' => $this->url])
            ->options(['TTL' => 86400]);
    }
}
