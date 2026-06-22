<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $expiresMinutes
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Codigo MFA para acceso administrativo')
            ->line('Se solicito un codigo de verificacion para ingresar al sistema.')
            ->line('Codigo: ' . $this->code)
            ->line('Este codigo expirara en ' . $this->expiresMinutes . ' minuto(s).')
            ->line('Si no reconoces esta solicitud, cambia tu contrasena y contacta al equipo administrador.');
    }
}
