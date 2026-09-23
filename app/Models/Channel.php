<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasContentApproval;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\LiveStreamProtocol;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $playlist_id
 * @property string $name
 * @property string|null $description
 * @property ChannelType $type
 * @property ChannelStatus $status
 * @property LiveStreamProtocol|null $live_protocol
 * @property string|null $live_url
 * @property int $width
 * @property int $height
 * @property int $version
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $zones_count
 * @property int|null $zones_count
 * @property int|null $screens_count
 * @property-read Playlist|null $playlist
 * @property-read Collection<int, ChannelZone> $zones
 * @property-read Collection<int, Screen> $screens
 */
#[Fillable([
    'team_id',
    'created_by',
    'updated_by',
    'playlist_id',
    'name',
    'description',
    'type',
    'status',
    'live_protocol',
    'live_url',
    'width',
    'height',
    'version',
    'scheduled_at',
    'published_at',
])]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use BelongsToTeam, HasContentApproval, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return HasMany<ChannelZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(ChannelZone::class)->orderBy('position');
    }

    /**
     * Screens currently assigned to play this channel.
     *
     * @return HasMany<Screen, $this>
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class, 'current_channel_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function zonesSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->zones as $zone) {
            $snapshot[] = [
                'name' => $zone->name,
                'playlist_id' => $zone->playlist_id,
                'x' => $zone->x,
                'y' => $zone->y,
                'width' => $zone->width,
                'height' => $zone->height,
                'z_index' => $zone->z_index,
                'position' => $zone->position,
            ];
        }

        return $snapshot;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'status' => ChannelStatus::class,
            'live_protocol' => LiveStreamProtocol::class,
            'width' => 'integer',
            'height' => 'integer',
            'version' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
