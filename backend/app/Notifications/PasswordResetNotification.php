<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
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
            ->subject('Codigo de recuperacion de contrasena')
            ->line('Se solicito un codigo de verificacion para restablecer la contrasena de tu cuenta.')
            ->line('Codigo de recuperacion: ' . $this->code)
            ->line('Este codigo expirara en ' . $this->expiresMinutes . ' minuto(s).')
            ->line('Si no reconoces esta solicitud, puedes ignorar este correo de forma segura.');
    }
}
