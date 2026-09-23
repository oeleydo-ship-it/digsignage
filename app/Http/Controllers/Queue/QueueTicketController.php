<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CancelQueueTicket;
use App\Actions\Queue\IssueQueueTicket;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\IssueQueueTicketRequest;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QueueTicketController extends Controller
{
    /**
     * List tickets for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QueueTicket::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        QueueSetting::resolveForTeam($team);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $serviceId = $request->integer('queue_service_id') ?: null;

        $tickets = QueueTicket::query()
            ->forTeam($team)
            ->with([
                'service:id,name,ticket_prefix',
                'queuePriority:id,name,color',
                'events.user:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->where('number', 'like', '%'.$search.'%'))
            ->when(
                $status !== '' && QueueTicketStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when($serviceId, fn ($query) => $query->where('queue_service_id', $serviceId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (QueueTicket $ticket) => $this->ticketPayload($ticket));

        return Inertia::render('queue/tickets', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'queue_service_id' => $serviceId,
            ],
            'services' => QueueService::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name', 'ticket_prefix', 'is_active', 'numbering_reset', 'next_sequence', 'sequence_period'])
                ->map(fn (QueueService $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'ticket_prefix' => $service->ticket_prefix,
                    'is_active' => $service->is_active,
                    'next_ticket_number' => $service->nextTicketNumber(),
                ]),
            'statuses' => collect(QueueTicketStatus::cases())->map(fn (QueueTicketStatus $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ]),
            'priorities' => QueuePriority::query()
                ->forTeam($team)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('weight')
                ->get(['id', 'name', 'code', 'weight', 'color'])
                ->map(fn (QueuePriority $priority) => [
                    'id' => $priority->id,
                    'name' => $priority->name,
                    'code' => $priority->code,
                    'weight' => $priority->weight,
                    'color' => $priority->color,
                ]),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    /**
     * Issue a staff ticket for a service.
     */
    public function store(IssueQueueTicketRequest $request, IssueQueueTicket $issueQueueTicket): RedirectResponse
    {
        Gate::authorize('create', QueueTicket::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        QueueSetting::resolveForTeam($team);

        $service = QueueService::query()
            ->forTeam($team)
            ->whereKey($request->integer('queue_service_id'))
            ->firstOrFail();

        $ticket = $issueQueueTicket->handle(
            $team,
            $service,
            [
                ...$request->validated(),
                'source' => $request->validated('source') ?? QueueTicketSource::Staff->value,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ticket :number issued.', ['number' => $ticket->number]),
        ]);

        return back(fallback: route('queue.tickets', $team));
    }

    /**
     * Cancel a waiting ticket.
     */
    public function cancel(
        Request $request,
        string $current_team,
        QueueTicket $queueTicket,
        CancelQueueTicket $cancelQueueTicket,
    ): RedirectResponse {
        Gate::authorize('cancel', $queueTicket);
        abort_unless($queueTicket->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $cancelQueueTicket->handle($queueTicket, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ticket :number cancelled.', ['number' => $queueTicket->number]),
        ]);

        return back(fallback: route('queue.tickets', $request->user()->currentTeam));
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketPayload(QueueTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'queue_service_id' => $ticket->queue_service_id,
            'service_name' => $ticket->service->name,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'queue_position' => $ticket->queue_position,
            'source' => $ticket->source->value,
            'source_label' => $ticket->source->label(),
            'priority' => $ticket->priority,
            'queue_priority_id' => $ticket->queue_priority_id,
            'priority_name' => $ticket->queuePriority?->name,
            'customer_name' => $ticket->customer_name,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'events' => $ticket->events
                ->sortBy('id')
                ->values()
                ->map(fn (QueueTicketEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'type_label' => $event->type->label(),
                    'user_name' => $event->user?->name,
                    'created_at' => $event->created_at?->toIso8601String(),
                    'payload' => $event->payload,
                ])
                ->values(),
        ];
    }
}
