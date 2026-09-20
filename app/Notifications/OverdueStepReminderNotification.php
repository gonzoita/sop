<?php

namespace App\Notifications;

use App\Models\SopRun;
use App\Models\SopRunStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueStepReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SopRun $run, public SopRunStep $step)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('runs.show', ['run' => $this->run->id]);
        $dueDate = $this->step->due_at ? $this->step->due_at->format('d/m/Y H:i') : 'Vencido';

        return (new MailMessage)
            ->subject("[Recordatorio] Paso vencido en: {$this->run->title}")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Te recordamos que tienes un paso pendiente cuya fecha límite ha expirado en el procedimiento \"{$this->run->title}\".")
            ->line("Tipo de bloque: {$this->step->block_type}")
            ->line("Fecha límite establecida: {$dueDate}")
            ->action('Completar Paso Vencido', $url)
            ->line('Por favor atiende este paso para no retrasar la entrega general del procedimiento.');
    }
}
