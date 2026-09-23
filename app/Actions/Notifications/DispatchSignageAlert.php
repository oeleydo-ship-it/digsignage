<?php

namespace App\Actions\Notifications;

use App\Enums\SignageAlert;
use App\Enums\TeamRole;
use App\Jobs\SendSignageAlert;
use App\Models\InAppNotification;
use App\Models\NotificationSetting;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SignageAlertMail;
use App\Support\SafeOutboundHttp;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

class DispatchSignageAlert
{
    /**
     * Queue a team alert so player heartbeats stay non-blocking.
     *
     * @param  array<string, mixed>  $data
     */
    public function queue(
        Team $team,
        SignageAlert $alert,
        string $title,
        string $body,
        array $data = [],
        ?string $dedupeKey = null,
        int $dedupeSeconds = 300,
    ): void {
        SendSignageAlert::dispatch(
            $team->id,
            $alert->value,
            $title,
            $body,
            $data,
            $dedupeKey,
            $dedupeSeconds,
        );
    }

    /**
     * Deliver an alert on the channels enabled for this team.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Team $team,
        SignageAlert $alert,
        string $title,
        string $body,
        array $data = [],
        ?string $dedupeKey = null,
        int $dedupeSeconds = 300,
    ): void {
        if ($dedupeKey !== null && $dedupeKey !== '') {
            $ttl = max(1, $dedupeSeconds);
            $cacheKey = 'signage-alert:'.$team->id.':'.$dedupeKey;

            if (! Cache::add($cacheKey, 1, $ttl)) {
                return;
            }
        }

        $settings = NotificationSetting::resolveForTeam($team);
        $settings->load('preferences');
        $channels = $settings->channelsFor($alert);
        $recipients = $this->recipients($team, $alert);

        if ($channels['in_app']) {
            foreach ($recipients as $user) {
                InAppNotification::query()->create([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'event' => $alert,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ]);
            }
        }

        if ($channels['email'] && $recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new SignageAlertMail($team, $alert, $title, $body, $data),
            );
        }

        $payload = [
            'event' => $alert->value,
            'team' => $team->slug,
            'title' => $title,
            'body' => $body,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        if ($channels['webhook'] && is_string($settings->webhook_url) && $settings->webhook_url !== '') {
            $this->postJson($settings->webhook_url, $payload);
        }

        if ($channels['slack'] && is_string($settings->slack_webhook_url) && $settings->slack_webhook_url !== '') {
            $this->postJson($settings->slack_webhook_url, [
                'text' => '*'.$title.'*'."\n".$body,
            ]);
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    protected function recipients(Team $team, SignageAlert $alert): EloquentCollection
    {
        $roles = [
            TeamRole::Owner->value,
            TeamRole::Admin->value,
            TeamRole::ContentManager->value,
            TeamRole::Publisher->value,
        ];

        if (str_starts_with($alert->value, 'queue_')) {
            $roles[] = TeamRole::BranchManager->value;
            $roles[] = TeamRole::QueueSupervisor->value;
        }

        return $team->members()
            ->wherePivotIn('role', $roles)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postJson(string $url, array $payload): void
    {
        if (! SafeOutboundHttp::isAllowed($url)) {
            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['User-Agent' => 'DigSignageAlerts/1.0'])
                ->acceptJson()
                ->post($url, $payload);
        } catch (Throwable) {
            // Delivery failures must not roll back in-app or email channels.
        }
    }
}
