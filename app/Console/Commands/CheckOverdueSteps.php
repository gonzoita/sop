<?php

namespace App\Console\Commands;

use App\Models\SopRunStep;
use App\Notifications\OverdueStepReminderNotification;
use Illuminate\Console\Command;

class CheckOverdueSteps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sop:check-overdue-steps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica pasos de SOP vencidos y envía recordatorios por correo a los responsables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $overdueSteps = SopRunStep::whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereIn('status', ['pending', 'running', 'awaiting_approval'])
            ->whereNotNull('assigned_to')
            ->where(function ($query) {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', now()->subHours(24));
            })
            ->with(['assignee', 'run'])
            ->get();

        if ($overdueSteps->isEmpty()) {
            $this->info('No se encontraron pasos vencidos pendientes de recordatorio.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($overdueSteps as $step) {
            $assignee = $step->assignee;
            $run = $step->run;

            if ($assignee && $run) {
                $assignee->notify(new OverdueStepReminderNotification($run, $step));
                $step->update(['last_reminded_at' => now()]);
                $count++;
            }
        }

        $this->info("Se enviaron {$count} recordatorios de pasos vencidos a la cola.");

        return self::SUCCESS;
    }
}
