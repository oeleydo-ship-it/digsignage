<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CancelQueueTicket;
use App\Actions\Queue\IssueQueueTicket;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\JoinVirtualQueueRequest;
use App\Models\Location;
use App\Models\QueueNotificationRule;
use App\Models\QueuePushSubscription;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use App\Services\QueueNotifications\WebPushSender;
use App\Support\QueueNotificationChannels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QueueVirtualController extends Controller
{
    public function show(Request $request, Team $team): Response
    {
        $locationId = $request->integer('location') ?: null;
        $serviceId = $request->integer('service') ?: null;

        $location = $locationId
            ? Location::query()->forTeam($team)->whereKey($locationId)->firstOrFail()
            : null;

        $services = QueueService::query()
            ->forTeam($team)
            ->where('is_active', true)
            ->when($location, fn ($query) => $query->where('location_id', $location->id))
            ->when($serviceId, fn ($query) => $query->whereKey($serviceId))
            ->with('location:id,name')
            ->withCount([
                'tickets as waiting_count' => fn ($query) => $query->where('status', QueueTicketStatus::Waiting),
            ])
            ->orderBy('name')
            ->get();

        abort_if($serviceId && $services->isEmpty(), 404);

        return Inertia::render('queue/virtual-join', [
            'team' => ['name' => $team->name, 'slug' => $team->slug],
            'location' => $location ? ['id' => $location->id, 'name' => $location->name] : null,
            'services' => $services->map(fn (QueueService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'location_name' => $service->location?->name,
                'display_color' => $service->display_color,
                'waiting_count' => (int) $service->getAttribute('waiting_count'),
                'estimated_wait_minutes' => (int) ceil(
                    ((int) $service->getAttribute('waiting_count') * $service->average_service_duration_seconds) / 60,
                ),
            ])->values(),
        ]);
    }

    public function store(
        JoinVirtualQueueRequest $request,
        Team $team,
        IssueQueueTicket $issueQueueTicket,
    ): RedirectResponse {
        $service = QueueService::query()
            ->forTeam($team)
            ->where('is_active', true)
            ->whereKey($request->integer('queue_service_id'))
            ->firstOrFail();

        $locationId = $request->integer('location_id') ?: null;

        if ($locationId !== null && $service->location_id !== $locationId) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('This service is not available at the selected location.'),
            ]);
        }

        if ($service->max_queue_capacity !== null) {
            $waiting = $service->tickets()
                ->where('status', QueueTicketStatus::Waiting)
                ->count();

            if ($waiting >= $service->max_queue_capacity) {
                throw ValidationException::withMessages([
                    'queue_service_id' => __('This queue is currently full. Please try again later.'),
                ]);
            }
        }

        $ticket = $issueQueueTicket->handle($team, $service, [
            ...$request->safe()->only(['customer_name', 'customer_phone', 'customer_email', 'idempotency_key']),
            'source' => QueueTicketSource::Qr,
        ]);

        return redirect()->route('queue.virtual.ticket', $ticket->public_token);
    }

    public function ticket(QueueTicket $queueTicket): Response
    {
        $queueTicket->load(['team:id,name,slug', 'service:id,name,average_service_duration_seconds', 'location:id,name', 'counter:id,name,code']);

        return Inertia::render('queue/virtual-ticket', [
            'ticket' => $this->ticketPayload($queueTicket),
            'reverb' => $this->reverbConfig(),
            'push' => $this->pushPayload($queueTicket),
        ]);
    }

    /**
     * Save the customer's browser so ticket updates reach it as push
     * notifications, even with the page closed.
     */
    public function subscribePush(Request $request, QueueTicket $queueTicket): JsonResponse
    {
        abort_unless($this->pushPayload($queueTicket)['enabled'], 403, __('Notifications are not available for this ticket.'));

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        $endpoint = (string) $data['endpoint'];

        if (! self::isPushService($endpoint)) {
            throw ValidationException::withMessages(['endpoint' => __('This browser push service is not supported.')]);
        }

        if (QueuePushSubscription::query()->where('queue_ticket_id', $queueTicket->id)->count() >= 5) {
            QueuePushSubscription::query()->where('queue_ticket_id', $queueTicket->id)->oldest('id')->first()?->delete();
        }

        QueuePushSubscription::query()->updateOrCreate(
            ['queue_ticket_id' => $queueTicket->id, 'endpoint_hash' => hash('sha256', $endpoint)],
            [
                'team_id' => $queueTicket->team_id,
                'endpoint' => $endpoint,
                'public_key' => (string) data_get($data, 'keys.p256dh'),
                'auth_token' => (string) data_get($data, 'keys.auth'),
                'content_encoding' => (string) ($data['content_encoding'] ?? 'aes128gcm'),
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribePush(Request $request, QueueTicket $queueTicket): JsonResponse
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:2000']])['endpoint'];

        QueuePushSubscription::query()
            ->where('queue_ticket_id', $queueTicket->id)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    /**
     * The server sends requests to subscription endpoints, so only accept the
     * browsers' own push services.
     */
    public static function isPushService(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        foreach (['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'] as $service) {
            if ($host === $service || str_ends_with($host, '.'.$service)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{enabled: bool, public_key: string|null}
     */
    protected function pushPayload(QueueTicket $ticket): array
    {
        $active = in_array($ticket->status, [QueueTicketStatus::Waiting, QueueTicketStatus::Called, QueueTicketStatus::Serving], true);
        $wanted = $active
            && app(QueueNotificationChannels::class)->canSend($ticket->team_id, QueueNotificationChannel::Push)
            && QueueNotificationRule::query()
                ->where('team_id', $ticket->team_id)
                ->where('channel', QueueNotificationChannel::Push->value)
                ->where('is_enabled', true)
                ->exists();

        if (! $wanted) {
            return ['enabled' => false, 'public_key' => null];
        }

        try {
            return ['enabled' => true, 'public_key' => app(WebPushSender::class)->publicKey()];
        } catch (Throwable $exception) {
            report($exception);

            return ['enabled' => false, 'public_key' => null];
        }
    }

    public function cancel(
        QueueTicket $queueTicket,
        CancelQueueTicket $cancelQueueTicket,
    ): RedirectResponse {
        abort_unless($queueTicket->source === QueueTicketSource::Qr, 403);

        $cancelQueueTicket->handle($queueTicket);

        return back(fallback: route('queue.virtual.ticket', $queueTicket->public_token));
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketPayload(QueueTicket $ticket): array
    {
        $position = $ticket->status === QueueTicketStatus::Waiting
            ? $ticket->queue_position
            : null;
        $peopleAhead = max(0, ($position ?? 1) - 1);
        $nowServing = QueueTicket::query()
            ->where('queue_service_id', $ticket->queue_service_id)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->with('counter:id,name,code')
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();

        return [
            'token' => $ticket->public_token,
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'service_id' => $ticket->queue_service_id,
            'service_name' => $ticket->service->name,
            'location_name' => $ticket->location?->name,
            'position' => $position,
            'people_ahead' => $peopleAhead,
            'estimated_wait_minutes' => (int) ceil(
                ($peopleAhead * $ticket->service->average_service_duration_seconds) / 60,
            ),
            'counter_name' => $ticket->counter === null
                ? null
                : ($ticket->counter->name ?? $ticket->counter->code),
            'now_serving' => $nowServing ? [
                'number' => $nowServing->number,
                'counter_name' => $nowServing->counter === null
                    ? null
                    : ($nowServing->counter->name ?? $nowServing->counter->code),
            ] : null,
            'can_cancel' => $ticket->status === QueueTicketStatus::Waiting,
            'team_name' => $ticket->team->name,
        ];
    }

    /**
     * @return array{enabled: bool, key: mixed, host: mixed, port: int, scheme: mixed}
     */
    protected function reverbConfig(): array
    {
        return [
            'enabled' => config('broadcasting.default') === 'reverb'
                && filled(config('broadcasting.connections.reverb.key')),
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host') ?: 'localhost',
            'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme') ?: 'http',
        ];
    }
}
