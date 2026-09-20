<?php

namespace App\Actions;

use App\Models\ClientInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AcceptClientInvitation
{
    /**
     * Process accepting a client portal invitation.
     */
    public function execute(string $token, string $name, string $password): User
    {
        $invitation = ClientInvitation::where('token', $token)->first();

        if (! $invitation) {
            throw new InvalidArgumentException('La invitación proporcionada no es válida.');
        }

        if ($invitation->isAccepted()) {
            throw new InvalidArgumentException('Esta invitación ya ha sido utilizada anteriormente.');
        }

        if ($invitation->isExpired()) {
            throw new InvalidArgumentException('Esta invitación ha expirado. Por favor, solicita un nuevo enlace.');
        }

        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::where('email', $invitation->email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => Hash::make($password),
                ]);
            } else {
                $user->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                ]);
            }

            // Asignar rol cliente de Spatie
            if (! $user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }

            // Asociar en client_user si no existe
            $client = $invitation->client;
            if (! $client->users()->where('users.id', $user->id)->exists()) {
                $client->users()->attach($user->id, [
                    'role' => $invitation->role,
                    'invited_at' => $invitation->created_at,
                    'accepted_at' => now(),
                ]);
            } else {
                $client->users()->updateExistingPivot($user->id, [
                    'role' => $invitation->role,
                    'accepted_at' => now(),
                ]);
            }

            // Marcar invitación como aceptada
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
