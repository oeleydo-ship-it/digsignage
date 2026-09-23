<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use Database\Factories\ScreenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $location_id
 * @property string $name
 * @property string|null $description
 * @property string|null $device_uuid
 * @property string|null $device_token
 * @property string|null $device_token_hash
 * @property ScreenOrientation $orientation
 * @property int|null $resolution_width
 * @property int|null $resolution_height
 * @property string|null $timezone
 * @property ScreenStatus $status
 * @property Carbon|null $last_seen_at
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property int|null $storage_available
 * @property int|null $storage_total
 * @property int|null $current_channel_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Location|null $location
 * @property-read Channel|null $currentChannel
 * @property-read Collection<int, ScreenGroup> $groups
 * @property-read Collection<int, DeviceRegistration> $registrations
 * @property-read Collection<int, DeviceCommand> $commands
 */
#[Fillable([
    'team_id',
    'location_id',
    'name',
    'description',
    'device_uuid',
    'device_token',
    'device_token_hash',
    'orientation',
    'resolution_width',
    'resolution_height',
    'timezone',
    'status',
    'last_seen_at',
    'app_version',
    'ip_address',
    'storage_available',
    'storage_total',
    'current_channel_id',
    'metadata',
])]
#[Hidden(['device_token', 'device_token_hash'])]
class Screen extends Model
{
    /** @use HasFactory<ScreenFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * Get the location this screen belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Channel currently assigned to this screen.
     *
     * @return BelongsTo<Channel, $this>
     */
    public function currentChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'current_channel_id');
    }

    /**
     * Get the groups this screen belongs to.
     *
     * @return BelongsToMany<ScreenGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ScreenGroup::class, 'screen_group_screen')
            ->withTimestamps();
    }

    /**
     * Get device registrations for this screen.
     *
     * @return HasMany<DeviceRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }

    /**
     * Remote commands issued to this screen.
     *
     * @return HasMany<DeviceCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * Determine if the screen has been paired to a player.
     */
    public function isPaired(): bool
    {
        return filled($this->device_uuid);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => ScreenOrientation::class,
            'status' => ScreenStatus::class,
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
            'resolution_width' => 'integer',
            'resolution_height' => 'integer',
            'storage_available' => 'integer',
            'storage_total' => 'integer',
        ];
    }
}
