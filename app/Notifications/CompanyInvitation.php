<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitation extends Notification
{
    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject('Activa tu cuenta de NexusERP')
            ->greeting('Bienvenido a NexusERP')
            ->line('Se creó una cuenta para ti. Establece tu contraseña para poder iniciar sesión.')
            ->action('Establecer contraseña', $url)
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
