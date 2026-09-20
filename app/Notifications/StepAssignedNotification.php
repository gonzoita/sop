<?php

namespace App\Notifications;

use App\Models\SopRun;
use App\Models\SopRunStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StepAssignedNotification extends Notification implements ShouldQueue
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
        $dueDate = $this->step->due_at ? $this->step->due_at->format('d/m/Y H:i') : 'Sin fecha límite definida';

        return (new MailMessage)
            ->subject("Nuevo paso asignado en: {$this->run->title}")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Se te ha asignado un nuevo paso en el procedimiento \"{$this->run->title}\".")
            ->line("Tipo de bloque: {$this->step->block_type}")
            ->line("Fecha límite: {$dueDate}")
            ->action('Ver y Completar Paso', $url)
            ->line('Por favor revisa los requerimientos e ingresa la información solicitada.');
    }
}
