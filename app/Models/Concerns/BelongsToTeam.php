<?php

namespace App\Models\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTeam
{
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $query) {
            if ($teamId = currentTeamId()) {
                $query->where($query->getModel()->getTable().'.team_id', $teamId);
            }
        });

        static::creating(function ($model) {
            $model->team_id ??= currentTeamId();
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}