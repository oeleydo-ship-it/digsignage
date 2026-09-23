<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SignageAlert;
use Database\Factories\NotificationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string|null $webhook_url
 * @property string|null $slack_webhook_url
 * @property string|null $min_player_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, NotificationChannelPreference> $preferences
 */
#[Fillable(['team_id', 'webhook_url', 'slack_webhook_url', 'min_player_version'])]
class NotificationSetting extends Model
{
    /** @use HasFactory<NotificationSettingFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return HasMany<NotificationChannelPreference, $this>
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationChannelPreference::class, 'team_id', 'team_id');
    }

    public static function resolveForTeam(Team $team): self
    {
        $existing = static::query()->where('team_id', $team->id)->first();

        if ($existing instanceof self) {
            return $existing;
        }

        $setting = new self;
        $setting->forceFill([
            'team_id' => $team->id,
            'webhook_url' => null,
            'slack_webhook_url' => null,
            'min_player_version' => null,
        ])->save();

        return $setting;
    }

    /**
     * @return array{email: bool, in_app: bool, webhook: bool, slack: bool}
     */
    public function channelsFor(SignageAlert $alert): array
    {
        $preference = $this->relationLoaded('preferences')
            ? $this->preferences->first(
                fn (NotificationChannelPreference $row): bool => $row->event === $alert,
            )
            : $this->preferences()->where('event', $alert->value)->first();

        if ($preference instanceof NotificationChannelPreference) {
            return [
                'email' => $preference->email,
                'in_app' => $preference->in_app,
                'webhook' => $preference->webhook,
                'slack' => $preference->slack,
            ];
        }

        return $alert->defaultChannels();
    }
}
