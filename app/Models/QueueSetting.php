<?php

namespace App\Models;

use App\Actions\Queue\EnsureDefaultQueuePriorities;
use App\Concerns\BelongsToTeam;
use Database\Factories\QueueSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property array<string, mixed> $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'settings'])]
class QueueSetting extends Model
{
    /** @use HasFactory<QueueSettingFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Resolve or create the unique queue settings row for a team.
     */
    public static function resolveForTeam(Team $team): self
    {
        $settings = static::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'settings' => [
                    'starvation' => [
                        'max_priority_wait_seconds' => null,
                        'promote_after_seconds' => null,
                    ],
                    'voice' => [
                        'enabled' => false,
                        'languages' => ['en-US'],
                        'voice' => null,
                        'speed' => 1.0,
                        'volume' => 1.0,
                        'repeat_count' => 1,
                        'chime' => true,
                        'announcement_delay_seconds' => 1.0,
                    ],
                    'appointments' => [
                        'priority_id' => null,
                        'check_in_before_minutes' => 30,
                        'check_in_after_minutes' => 15,
                    ],
                    'alerts' => [
                        'enabled' => false,
                        'average_wait_minutes' => 20,
                        'waiting_customers' => 30,
                        'customer_wait_minutes' => 45,
                        'no_counter_available' => true,
                        'capacity_reached' => true,
                        'counter_offline' => true,
                        'cooldown_minutes' => 30,
                    ],
                ],
            ],
        );

        app(EnsureDefaultQueuePriorities::class)->handle($team);

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
