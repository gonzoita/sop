<?php

namespace App\Listeners;

use App\Actions\Sop\StartSopRun;
use App\Events\ClientCreated;
use App\Events\RunCompleted;
use App\Events\RunStepApproved;
use App\Models\AutomationTrigger;
use App\Models\Client;
use App\Models\SopRun;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessAutomationTriggers implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected StartSopRun $startSopRun)
    {
    }

    /**
     * Handle the domain event.
     */
    public function handle(object $event): void
    {
        $eventName = null;
        $teamId = null;
        $client = null;
        $triggerUser = null;

        if ($event instanceof ClientCreated) {
            $eventName = 'client.created';
            $teamId = $event->client->team_id;
            $client = $event->client;
            $triggerUser = auth()->user() ?? User::find($client->created_by);
        } elseif ($event instanceof RunCompleted) {
            $eventName = 'run.completed';
            $teamId = $event->run->team_id;
            $client = $event->run->client;
            $triggerUser = auth()->user() ?? User::find($event->run->started_by);
        } elseif ($event instanceof RunStepApproved) {
            $eventName = 'run.step.approved';
            $teamId = $event->run->team_id;
            $client = $event->run->client;
            $triggerUser = auth()->user() ?? User::find($event->run->started_by);
        } elseif (method_exists($event, 'getEventName')) {
            $eventName = $event->getEventName();
        }

        if (! $eventName || ! $teamId) {
            return;
        }

        // Buscar disparadores activos para este evento en el equipo
        $triggers = AutomationTrigger::where('team_id', $teamId)
            ->where('event', $eventName)
            ->where('is_active', true)
            ->with(['sop.currentVersion', 'creator'])
            ->get();

        foreach ($triggers as $trigger) {
            $sop = $trigger->sop;
            if (! $sop) {
                continue;
            }

            $version = $sop->currentVersion;
            if (! $version) {
                Log::warning("AutomationTrigger #{$trigger->id} omitido: SOP #{$sop->id} no tiene versión publicada.");
                continue;
            }

            // Evaluar condiciones si están definidas
            if (! empty($trigger->conditions)) {
                if (! $this->matchesConditions($trigger->conditions, $event)) {
                    continue;
                }
            }

            // Fallback para el usuario iniciador (creador del trigger o primer admin del equipo)
            $user = $triggerUser ?? $trigger->creator;
            if (! $user) {
                $user = User::whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))->first();
            }

            if (! $user) {
                Log::error("AutomationTrigger #{$trigger->id} falló: No se encontró usuario para ejecutar el SOP.");
                continue;
            }

            $title = "[Automático] {$sop->title}" . ($client ? " - {$client->name}" : '');

            // Instanciar corrida del SOP
            $this->startSopRun->execute($user, $sop, [
                'sop_version_id' => $version->id,
                'client_id' => $client?->id,
                'title' => $title,
            ]);

            activity('automation_triggers')
                ->performedOn($trigger)
                ->causedBy($user)
                ->log("SOP '{$sop->title}' instanciado automáticamente por evento '{$eventName}'");
        }
    }

    /**
     * Check if event satisfies trigger conditions.
     */
    protected function matchesConditions(array $conditions, object $event): bool
    {
        foreach ($conditions as $key => $expectedValue) {
            if ($event instanceof ClientCreated) {
                if (($event->client->{$key} ?? null) !== $expectedValue) {
                    return false;
                }
            } elseif ($event instanceof RunCompleted || $event instanceof RunStepApproved) {
                if (($event->run->{$key} ?? null) !== $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }
}
