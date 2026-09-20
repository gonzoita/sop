<?php

namespace App\Notifications;

use App\Models\SopRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RunCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SopRun $run)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('runs.show', ['run' => $this->run->id]);
        $clientName = $this->run->client->name ?? 'Interno';

        return (new MailMessage)
            ->subject("Procedimiento completado: {$this->run->title}")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Todos los pasos del procedimiento operativo \"{$this->run->title}\" han sido finalizados exitosamente.")
            ->line("Cliente / Organización: {$clientName}")
            ->action('Ver Resultados del Procedimiento', $url)
            ->line('Puedes consultar el resumen completo y las salidas generadas directamente en la plataforma.');
    }
}
