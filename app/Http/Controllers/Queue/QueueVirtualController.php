<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CancelQueueTicket;
use App\Actions\Queue\IssueQueueTicket;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\JoinVirtualQueueRequest;
use App\Models\Location;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);
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
