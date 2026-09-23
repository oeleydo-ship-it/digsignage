<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ChannelZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $channel_id
 * @property int|null $playlist_id
 * @property string $name
 * @property int $x
 * @property int $y
 * @property int $width
 * @property int $height
 * @property int $z_index
 * @property int $position
 * @property-read Channel $channel
 * @property-read Playlist|null $playlist
 */
#[Fillable([
    'team_id',
    'channel_id',
    'playlist_id',
    'name',
    'x',
    'y',
    'width',
    'height',
    'z_index',
    'position',
])]
class ChannelZone extends Model
{
    /** @use HasFactory<ChannelZoneFactory> */
    use BelongsToTeam, HasFactory;

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
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'playlist_id' => $this->playlist_id,
            'playlist_name' => $this->playlist?->name,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'z_index' => $this->z_index,
            'position' => $this->position,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'z_index' => 'integer',
            'position' => 'integer',
        ];
    }
}
