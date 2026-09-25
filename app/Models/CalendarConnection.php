<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\CalendarConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organization's Microsoft 365 tenant, connected through an Entra ID app
 * registration using the client-credentials flow.
 *
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string|null $tenant_id
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MeetingRoom> $rooms
 */
#[Fillable([
    'team_id',
    'provider',
    'tenant_id',
    'client_id',
    'client_secret',
    'is_active',
    'last_synced_at',
    'last_error',
])]
class CalendarConnection extends Model
{
    /** @use HasFactory<CalendarConnectionFactory> */
    use BelongsToTeam, HasFactory;

    public const MICROSOFT_365 = 'microsoft365';

    /**
     * @var list<string>
     */
    protected $hidden = ['client_secret'];

    /**
     * @return HasMany<MeetingRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(MeetingRoom::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->tenant_id) && filled($this->client_id) && filled($this->client_secret);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
