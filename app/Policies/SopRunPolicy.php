<?php

namespace App\Policies;

use App\Models\SopRun;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SopRunPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any SOP runs.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops');
    }

    /**
     * Determine whether the user can view the specific SOP run.
     */
    public function view(User $user, SopRun $run): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $run->team_id) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops');
    }

    /**
     * Determine whether the user can start an SOP run.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops');
    }

    /**
     * Determine whether the user can update/advance an SOP run.
     */
    public function update(User $user, SopRun $run): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $run->team_id) {
            return false;
        }

        return $user->hasPermissionTo('execute-sops');
    }

    /**
     * Determine whether the user can delete an SOP run.
     */
    public function delete(User $user, SopRun $run): bool
    {
        if ($user->hasRole('cliente')) {
            return false;
        }

        if ((int) $user->current_team_id !== (int) $run->team_id) {
            return false;
        }

        return $user->hasPermissionTo('manage-sops');
    }
}
