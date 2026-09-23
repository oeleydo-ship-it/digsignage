<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\PlaylistRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $playlist_id
 * @property int|null $created_by
 * @property int $version
 * @property list<array<string, mixed>> $items
 * @property bool $loop
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Playlist $playlist
 */
#[Fillable([
    'team_id',
    'playlist_id',
    'created_by',
    'version',
    'items',
    'loop',
])]
class PlaylistRevision extends Model
{
    /** @use HasFactory<PlaylistRevisionFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'items' => 'array',
            'loop' => 'boolean',
        ];
    }
}
