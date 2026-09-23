<?php

namespace App\Actions\Signage;

use App\Actions\Notifications\EvaluatePlayerAlerts;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Enums\ScreenStatus;
use App\Enums\WebhookEvent;
use App\Models\Screen;
use App\Models\Team;
use App\Support\ScreenHealth;

class EvaluateScreenHealth
{
    public function __construct(protected EvaluatePlayerAlerts $evaluatePlayerAlerts) {}

    /**
     * Apply configurable last-seen thresholds to paired screens.
     */
    public function handle(?Team $team = null): int
    {
        $updated = 0;
        $query = Screen::query()
            ->with(['team', 'currentChannel:id,name'])
            ->where('status', '!=', ScreenStatus::Disabled->value);

        if ($team !== null) {
            $query->forTeam($team);
        }

        $screens = $query->get()->groupBy('team_id');

        foreach ($screens as $group) {
            $first = $group->first();

            if (! $first instanceof Screen) {
                continue;
            }

            $thresholds = ScreenHealth::thresholds($first->team);

            foreach ($group as $screen) {
                $status = ScreenHealth::statusFor($screen, $thresholds);

                if ($screen->status === $status) {
                    continue;
                }

                $previous = $screen->status;
                $screen->forceFill(['status' => $status])->save();
                $updated++;

                if ($status === ScreenStatus::Offline && $previous !== ScreenStatus::Offline) {
                    $this->evaluatePlayerAlerts->screenOffline($screen);
                    $screen->loadMissing('team');
                    app(DispatchPartnerWebhook::class)->handle(
                        $screen->team,
                        WebhookEvent::ScreenOffline,
                        ['screen_id' => $screen->id, 'name' => $screen->name, 'status' => $status->value],
                    );
                }
            }
        }

        return $updated;
    }
}
