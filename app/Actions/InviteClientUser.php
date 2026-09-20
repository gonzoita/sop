<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\ClientInvitation;
use App\Models\User;
use App\Notifications\ClientInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InviteClientUser
{
    /**
     * Create an invitation for an external client user and notify via email.
     */
    public function execute(Client $client, string $email, string $role = 'collaborator', ?User $inviter = null): ClientInvitation
    {
        $email = strtolower(trim($email));

        // Verificar si ya existe una invitación pendiente válida
        $existing = ClientInvitation::where('client_id', $client->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            // Renovar expiración y reenviar
            $existing->update([
                'expires_at' => now()->addHours(48),
                'role' => $role,
            ]);
            Notification::route('mail', $email)->notify(new ClientInvitationNotification($existing));
            return $existing;
        }

        $invitation = ClientInvitation::create([
            'team_id' => $client->team_id,
            'client_id' => $client->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(64),
            'invited_by' => $inviter?->id ?? auth()->id(),
            'expires_at' => now()->addHours(48),
        ]);

        Notification::route('mail', $email)->notify(new ClientInvitationNotification($invitation));

        return $invitation;
    }
}
