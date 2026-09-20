<?php

namespace App\Policies;

use App\Models\Sop;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SopPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        return $user->hasAnyPermission(['manage-sops', 'execute-sops']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sop $sop): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $sop->team_id) {
            return false;
        }

        return $user->hasAnyPermission(['manage-sops', 'execute-sops']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        return $user->hasPermissionTo('manage-sops');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sop $sop): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $sop->team_id) {
            return false;
        }

        return $user->hasPermissionTo('manage-sops');
    }

    /**
     * Determine whether the user can publish versions.
     */
    public function publish(User $user, Sop $sop): bool
    {
        return $this->update($user, $sop);
    }

    /**
     * Determine whether the user can duplicate the model.
     */
    public function duplicate(User $user, Sop $sop): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sop $sop): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $sop->team_id) {
            return false;
        }

        return $user->hasPermissionTo('manage-sops');
    }
}
