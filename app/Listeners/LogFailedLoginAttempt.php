<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;

class LogFailedLoginAttempt
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $user = $event->user;

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        $teamId = $user ? $user->current_team_id : currentTeamId();

        $activity = activity('auth')
            ->withProperties([
                'email' => $email ?? 'desconocido',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

        if ($user) {
            $activity->performedOn($user);
        }

        if ($teamId) {
            $activity->tap(function ($act) use ($teamId) {
                $act->team_id = $teamId;
            });
        }

        $displayEmail = $email ?? 'desconocido';
        $activity->log("Intento fallido de inicio de sesión para: {$displayEmail}");
    }
}