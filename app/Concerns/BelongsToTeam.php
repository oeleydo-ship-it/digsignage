<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $team_id
 * @property-read Team $team
 *
 * @method static Builder<static> forTeam(Team $team)
 */
trait BelongsToTeam
{
    /**
     * Get the team that owns the model.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to a single team.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam(Builder $query, Team $team): Builder
    {
        return $query->where($this->qualifyColumn('team_id'), $team->id);
    }
}
