<?php

if (! function_exists('currentTeamId')) {
    /**
     * Get the active team ID from the container, authenticated user, or session.
     */
    function currentTeamId(): ?int
    {
        if (app()->has('current_team_id')) {
            return app('current_team_id');
        }

        return auth()->user()?->current_team_id ?? session('current_team_id');
    }
}