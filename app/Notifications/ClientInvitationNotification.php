<?php

namespace App\Notifications;

use App\Models\ClientInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClientInvitation $invitation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('portal.invitations.accept', ['token' => $this->invitation->token]);
        $clientName = $this->invitation->client->name ?? 'su organización';
        $inviterName = $this->invitation->inviter->name ?? 'El equipo';

        return (new MailMessage)
            ->subject("Invitación para acceder al portal de {$clientName}")
            ->greeting("¡Hola!")
            ->line("{$inviterName} te ha invitado a colaborar en el portal de clientes para {$clientName}.")
            ->line("Desde este portal podrás revisar y completar la información de los procedimientos y proyectos asignados.")
            ->action('Aceptar invitación y acceder', $url)
            ->line('Este enlace es de un solo uso y expirará en 48 horas.')
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
