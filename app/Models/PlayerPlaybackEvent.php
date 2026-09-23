<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PlaybackStatus;
use Database\Factories\PlayerPlaybackEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $screen_id
 * @property int|null $location_id
 * @property int|null $channel_id
 * @property int|null $playlist_id
 * @property int|null $schedule_id
 * @property string|null $item_key
 * @property string|null $asset_key
 * @property string|null $content_id
 * @property string|null $title
 * @property int|null $duration_ms
 * @property Carbon $played_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property PlaybackStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Screen $screen
 * @property-read Location|null $location
 * @property-read Channel|null $channel
 * @property-read Playlist|null $playlist
 */
#[Fillable([
    'team_id',
    'screen_id',
    'location_id',
    'channel_id',
    'playlist_id',
    'schedule_id',
    'item_key',
    'asset_key',
    'content_id',
    'title',
    'duration_ms',
    'played_at',
    'started_at',
    'ended_at',
    'status',
])]
class PlayerPlaybackEvent extends Model
{
    /** @use HasFactory<PlayerPlaybackEventFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

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
            'duration_ms' => 'integer',
            'played_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => PlaybackStatus::class,
        ];
    }
}
