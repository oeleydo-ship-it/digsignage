<?php

namespace App\Actions\Notifications;

use App\Enums\SignageAlert;
use App\Models\NotificationChannelPreference;
use App\Models\NotificationSetting;
use App\Models\Team;
use App\Support\SafeOutboundHttp;
use Illuminate\Validation\ValidationException;

class SaveNotificationSettings
{
    /**
     * Persist webhook URLs, minimum player version, and per-event channels.
     *
     * @param  array{
     *     webhook_url?: string|null,
     *     slack_webhook_url?: string|null,
     *     min_player_version?: string|null,
     *     preferences: array<string, array{email?: mixed, in_app?: mixed, webhook?: mixed, slack?: mixed}>
     * }  $input
     */
    public function handle(Team $team, array $input): NotificationSetting
    {
        $webhook = $this->cleanUrl($input['webhook_url'] ?? null, 'webhook_url');
        $slack = $this->cleanUrl($input['slack_webhook_url'] ?? null, 'slack_webhook_url');
        $minRaw = $input['min_player_version'] ?? null;
        $minVersion = is_string($minRaw) ? trim($minRaw) : null;

        $setting = NotificationSetting::resolveForTeam($team);
        $setting->forceFill([
            'webhook_url' => $webhook,
            'slack_webhook_url' => $slack,
            'min_player_version' => $minVersion === '' ? null : $minVersion,
        ])->save();

        foreach (SignageAlert::cases() as $alert) {
            $row = $input['preferences'][$alert->value] ?? [];
            NotificationChannelPreference::query()->updateOrCreate(
                ['team_id' => $team->id, 'event' => $alert->value],
                [
                    'email' => (bool) ($row['email'] ?? false),
                    'in_app' => (bool) ($row['in_app'] ?? false),
                    'webhook' => (bool) ($row['webhook'] ?? false),
                    'slack' => (bool) ($row['slack'] ?? false),
                ],
            );
        }

        return $setting->fresh('preferences') ?? $setting;
    }

    protected function cleanUrl(mixed $value, string $field): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $url = trim($value);

        if (! SafeOutboundHttp::isAllowed($url)) {
            throw ValidationException::withMessages([
                $field => __('Use a public http(s) URL.'),
            ]);
        }

        return $url;
    }
}
