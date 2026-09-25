<?php

namespace App\Http\Controllers\Queue;

use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueNotificationChannelRequest;
use App\Jobs\SendQueueCustomerNotification;
use App\Models\QueueNotificationDelivery;
use App\Models\QueueSetting;
use App\Models\Team;
use App\Services\QueueNotifications\QueueNotificationProviderRegistry;
use App\Support\QueueNotificationChannels;
use App\Support\QueueNotificationTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

/**
 * Queue Configuration → Customer notifications: where each channel delivers,
 * message wording, test sends and retrying failed deliveries.
 */
class QueueNotificationSettingsController extends Controller
{
    public function updateChannel(
        SaveQueueNotificationChannelRequest $request,
        string $current_team,
        string $channel,
        QueueNotificationChannels $channels,
    ): RedirectResponse {
        $team = $this->authorizedTeam($request);
        $type = QueueNotificationChannel::tryFrom($channel) ?? abort(404);

        $channels->save($team, $type, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':channel settings saved.', ['channel' => $type->label()])]);

        return back(fallback: route('queue.settings', $team));
    }

    public function updateTemplates(Request $request, QueueNotificationTemplates $templates): RedirectResponse
    {
        $team = $this->authorizedTeam($request);
        $events = array_map(fn (QueueNotificationEvent $event) => $event->value, QueueNotificationEvent::cases());

        $validated = $request->validate([
            'templates' => ['required', 'array'],
            'templates.*' => ['array'],
            'templates.*.subject' => ['nullable', 'string', 'max:150'],
            'templates.*.body' => ['nullable', 'string', 'max:1000'],
        ]);

        $templates->save($team, array_intersect_key((array) $validated['templates'], array_flip($events)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message templates saved.')]);

        return back(fallback: route('queue.settings', $team));
    }

    /**
     * Send a sample "ticket called" message right away, bypassing the queue,
     * so problems show immediately.
     */
    public function test(
        Request $request,
        string $current_team,
        string $channel,
        QueueNotificationProviderRegistry $providers,
        QueueNotificationTemplates $templates,
    ): RedirectResponse {
        $team = $this->authorizedTeam($request);
        $type = QueueNotificationChannel::tryFrom($channel) ?? abort(404);

        if ($type === QueueNotificationChannel::Push) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('To test push, open a virtual ticket on your phone, tap "Notify me", then call the ticket.')]);

            return back(fallback: route('queue.settings', $team));
        }

        $destination = (string) $request->validate([
            'destination' => ['required', 'string', 'max:255', $type === QueueNotificationChannel::Email ? 'email' : 'regex:/^[+\d][\d\s().\-]{5,30}$/'],
        ], [
            'destination.regex' => __('Enter a phone number with its country code, such as +15551234567.'),
        ])['destination'];

        $template = $templates->all($team->id)[QueueNotificationEvent::TicketCalled->value];
        $sample = QueueNotificationTemplates::sample($team->name);

        try {
            $providers->for($type)->send(new QueueNotificationMessage(
                destination: $destination,
                subject: '[Test] '.QueueNotificationTemplates::fill($template['subject'], $sample),
                body: QueueNotificationTemplates::fill($template['body'], $sample),
                data: ['test' => true],
                teamId: $team->id,
            ));
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':channel test failed: :error', ['channel' => $type->label(), 'error' => mb_substr($exception->getMessage(), 0, 300)])]);

            return back(fallback: route('queue.settings', $team));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test :channel sent to :destination.', ['channel' => $type->label(), 'destination' => $destination])]);

        return back(fallback: route('queue.settings', $team));
    }

    public function retry(Request $request, string $current_team, QueueNotificationDelivery $delivery, QueueNotificationChannels $channels): RedirectResponse
    {
        $team = $this->authorizedTeam($request);

        abort_unless($delivery->team_id === $team->id, 404);
        abort_unless(in_array($delivery->status, ['failed', 'skipped'], true), 422, __('Only failed or skipped notifications can be sent again.'));

        if (blank($delivery->destination) || ! $channels->canSend($team->id, $delivery->channel)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => blank($delivery->destination)
                ? __('This customer has no :channel destination.', ['channel' => $delivery->channel->label()])
                : __(':channel is not set up yet.', ['channel' => $delivery->channel->label()])]);

            return back(fallback: route('queue.settings', $team));
        }

        $delivery->forceFill(['status' => 'pending', 'error' => null])->save();
        SendQueueCustomerNotification::dispatch($delivery->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sending again.')]);

        return back(fallback: route('queue.settings', $team));
    }

    protected function authorizedTeam(Request $request): Team
    {
        $team = $request->user()?->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('update', QueueSetting::resolveForTeam($team));

        return $team;
    }
}
