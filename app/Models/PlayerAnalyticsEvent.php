<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AnalyticsEventType;
use Database\Factories\PlayerAnalyticsEventFactory;
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
 * @property AnalyticsEventType $type
 * @property string|null $message
 * @property string|null $player_version
 * @property int|null $storage_free
 * @property int|null $storage_total
 * @property bool $storage_warning
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Screen $screen
 */
#[Fillable([
    'team_id',
    'screen_id',
    'location_id',
    'type',
    'message',
    'player_version',
    'storage_free',
    'storage_total',
    'storage_warning',
    'recorded_at',
])]
class PlayerAnalyticsEvent extends Model
{
    /** @use HasFactory<PlayerAnalyticsEventFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnalyticsEventType::class,
            'storage_free' => 'integer',
            'storage_total' => 'integer',
            'storage_warning' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }
}
