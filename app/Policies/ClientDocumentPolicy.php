<?php

namespace App\Policies;

use App\Models\ClientDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientDocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the client document.
     */
    public function view(User $user, ClientDocument $document): bool
    {
        if ($user->hasRole('cliente')) {
            if ($document->isRevoked()) {
                return false;
            }

            return $user->clients()->where('clients.id', $document->client_id)->exists();
        }

        if ((int) $user->current_team_id !== (int) $document->team_id) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops') || $user->hasPermissionTo('manage-sops');
    }

    /**
     * Determine whether the user can create/publish a client document.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops') || $user->hasPermissionTo('manage-sops');
    }

    /**
     * Determine whether the user can revoke the client document.
     */
    public function revoke(User $user, ClientDocument $document): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $document->team_id) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('editor') || $user->hasPermissionTo('manage-sops');
    }
}
