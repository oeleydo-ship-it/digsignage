<?php

namespace App\Support;

use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Team;
use App\Models\Template;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ResolveApprovable
{
    /**
     * Resolve a team-owned content record from the approval route.
     */
    public static function handle(Team $team, string $type, int $id): Model
    {
        $model = match ($type) {
            'playlist' => Playlist::query()->forTeam($team)->find($id),
            'design' => Design::query()->forTeam($team)->find($id),
            'template' => Template::query()->where('team_id', $team->id)->find($id),
            'channel' => Channel::query()->forTeam($team)->find($id),
            default => null,
        };

        if (! $model instanceof Model) {
            throw new NotFoundHttpException;
        }

        return $model;
    }
}
