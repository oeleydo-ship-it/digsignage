<?php

namespace App\Http\Controllers\Notifications;

use App\Actions\Notifications\SaveNotificationSettings;
use App\Enums\SignageAlert;
use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\NotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * In-app inbox and administrator delivery preferences.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InAppNotification::class);

        $user = $request->user();
        $team = $user->currentTeam;
        abort_unless($team !== null, 403);

        $setting = NotificationSetting::resolveForTeam($team);
        $setting->load('preferences');

        $preferences = [];

        foreach (SignageAlert::cases() as $alert) {
            $preferences[$alert->value] = [
                'label' => $alert->label(),
                ...$setting->channelsFor($alert),
            ];
        }

        return Inertia::render('notifications/index', [
            'notifications' => InAppNotification::query()
                ->forTeam($team)
                ->where('user_id', $user->id)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (InAppNotification $item) => $item->toInboxItem($team->slug)),
            'settings' => [
                'webhook_url' => $setting->webhook_url,
                'slack_webhook_url' => $setting->slack_webhook_url,
                'min_player_version' => $setting->min_player_version,
                'preferences' => $preferences,
            ],
            'canManage' => $user->can('manage', InAppNotification::class),
        ]);
    }

    /**
     * Save team notification channels and outbound webhooks.
     */
    public function updateSettings(Request $request, SaveNotificationSettings $save): RedirectResponse
    {
        Gate::authorize('manage', InAppNotification::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $validated = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'slack_webhook_url' => ['nullable', 'string', 'max:2048'],
            'min_player_version' => ['nullable', 'string', 'max:32'],
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.email' => ['sometimes', 'boolean'],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.webhook' => ['sometimes', 'boolean'],
            'preferences.*.slack' => ['sometimes', 'boolean'],
        ]);

        $save->handle($team, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences saved.')]);

        return back();
    }

    /**
     * Mark a single in-app notification as read.
     */
    public function markRead(Request $request, string $current_team, InAppNotification $inAppNotification): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 404);
        abort_unless($inAppNotification->team_id === $team->id, 404);
        abort_unless($team->slug === $current_team, 404);
        Gate::authorize('update', $inAppNotification);

        if ($inAppNotification->read_at === null) {
            $inAppNotification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    /**
     * Mark every unread notification for the current user and team as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', InAppNotification::class);

        $user = $request->user();
        $team = $user->currentTeam;
        abort_unless($team !== null, 403);

        InAppNotification::query()
            ->forTeam($team)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
