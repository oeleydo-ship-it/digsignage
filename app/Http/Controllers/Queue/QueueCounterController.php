<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CallNextQueueTicket;
use App\Actions\Queue\DeleteQueueCounter;
use App\Actions\Queue\OperateQueueDesk;
use App\Actions\Queue\SaveQueueCounter;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueCounterRequest;
use App\Http\Requests\Queue\TransferQueueTicketRequest;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QueueCounterController extends Controller
{
    /**
     * Display counters for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QueueCounter::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        QueueSetting::resolveForTeam($team);

        $counters = QueueCounter::query()
            ->forTeam($team)
            ->with(['location:id,name', 'assignedUser:id,name,email', 'services:id,name,code'])
            ->orderBy('name')
            ->get()
            ->map(fn (QueueCounter $counter) => $this->counterPayload($counter, $request->user()));

        return Inertia::render('queue/counters', [
            'counters' => $counters,
            'locations' => Location::query()
                ->forTeam($team)
                ->orderBy('path')
                ->orderBy('name')
                ->get(['id', 'name', 'depth']),
            'services' => QueueService::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_active', 'location_id']),
            'members' => $team->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ]),
            'statuses' => collect(QueueCounterStatus::cases())->map(fn (QueueCounterStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    /**
     * Store a newly created counter.
     */
    public function store(SaveQueueCounterRequest $request, SaveQueueCounter $saveQueueCounter): RedirectResponse
    {
        Gate::authorize('create', QueueCounter::class);

        $saveQueueCounter->handle(
            $request->user()->currentTeam,
            $request->validated(),
            actor: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Counter created.')]);

        return redirect()->route('queue.counters', $request->user()->currentTeam);
    }

    /**
     * Update the specified counter.
     */
    public function update(
        SaveQueueCounterRequest $request,
        string $current_team,
        QueueCounter $queueCounter,
        SaveQueueCounter $saveQueueCounter,
    ): RedirectResponse {
        Gate::authorize('update', $queueCounter);
        abort_unless($queueCounter->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveQueueCounter->handle(
            $request->user()->currentTeam,
            $request->validated(),
            $queueCounter,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Counter updated.')]);

        return redirect()->route('queue.counters', $request->user()->currentTeam);
    }

    /**
     * Remove the specified counter.
     */
    public function destroy(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        DeleteQueueCounter $deleteQueueCounter,
    ): RedirectResponse {
        Gate::authorize('delete', $queueCounter);
        abort_unless($queueCounter->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteQueueCounter->handle($queueCounter);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Counter deleted.')]);

        return back(fallback: route('queue.counters', $request->user()->currentTeam));
    }

    /**
     * Focused staff desk for a single counter.
     */
    public function desk(Request $request, string $current_team, QueueCounter $queueCounter): Response
    {
        Gate::authorize('operate', $queueCounter);
        abort_unless($queueCounter->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $queueCounter->load(['services:id,name,code', 'assignedUser:id,name']);

        $serviceIds = $queueCounter->services->modelKeys();
        $current = $queueCounter->currentTicket();
        $waiting = $serviceIds === []
            ? collect()
            : QueueTicket::query()
                ->where('team_id', $queueCounter->team_id)
                ->whereIn('queue_service_id', $serviceIds)
                ->where('status', QueueTicketStatus::Waiting)
                ->get();

        $waitSeconds = $waiting
            ->map(fn (QueueTicket $ticket) => $ticket->waitingDurationSeconds() ?? 0)
            ->filter()
            ->all();

        $averageWaitSeconds = $waitSeconds === []
            ? null
            : (int) round(array_sum($waitSeconds) / count($waitSeconds));

        $team = $request->user()->currentTeam;

        return Inertia::render('queue/desk', [
            'counter' => [
                'id' => $queueCounter->id,
                'name' => $queueCounter->name,
                'code' => $queueCounter->code,
                'status' => $queueCounter->status->value,
                'status_label' => $queueCounter->status->label(),
                'assigned_user_name' => $queueCounter->assignedUser?->name,
                'services' => $queueCounter->services->map(fn (QueueService $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                ])->values(),
            ],
            'current' => $current === null ? null : [
                'id' => $current->id,
                'number' => $current->number,
                'status' => $current->status->value,
                'status_label' => $current->status->label(),
                'customer_name' => $current->customer_name,
                'called_at' => $current->called_at?->toIso8601String(),
                'service_started_at' => $current->service_started_at?->toIso8601String(),
                'queue_service_id' => $current->queue_service_id,
            ],
            'waiting_count' => $waiting->count(),
            'held_count' => QueueTicket::query()
                ->where('counter_id', $queueCounter->id)
                ->where('status', QueueTicketStatus::OnHold)
                ->count(),
            'average_wait_seconds' => $averageWaitSeconds,
            'transferServices' => QueueService::query()
                ->forTeam($team)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'location_id']),
            'transferCounters' => QueueCounter::query()
                ->forTeam($team)
                ->with('services:id')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'location_id'])
                ->map(fn (QueueCounter $option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'code' => $option->code,
                    'location_id' => $option->location_id,
                    'service_ids' => $option->services->pluck('id')->all(),
                ])
                ->values(),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    /**
     * Call the next eligible ticket for this desk.
     */
    public function callNext(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        CallNextQueueTicket $callNextQueueTicket,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'call');

        try {
            $ticket = $callNextQueueTicket->handle($queueCounter, $request->user(), advanceCurrent: true);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Now serving :number.', ['number' => $ticket->number]),
        ]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    /**
     * Re-announce the ticket currently at this desk.
     */
    public function recall(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'call');

        $ticket = $operateQueueDesk->recall($queueCounter, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Recalled :number.', ['number' => $ticket->number]),
        ]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    public function hold(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'call');

        $operateQueueDesk->hold($queueCounter, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket placed on hold.')]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    public function resume(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'call');

        $ticket = $operateQueueDesk->resume($queueCounter, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resumed :number.', ['number' => $ticket->number]),
        ]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    public function complete(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'complete');

        $operateQueueDesk->complete($queueCounter, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket completed.')]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    public function noShow(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'complete');

        $operateQueueDesk->noShow($queueCounter, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as no show.')]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    public function transfer(
        TransferQueueTicketRequest $request,
        string $current_team,
        QueueCounter $queueCounter,
        OperateQueueDesk $operateQueueDesk,
    ): RedirectResponse {
        $this->assertOperable($request, $current_team, $queueCounter, 'transfer');

        $team = $request->user()->currentTeam;
        $service = QueueService::query()
            ->forTeam($team)
            ->whereKey($request->integer('queue_service_id'))
            ->firstOrFail();

        $destinationCounter = null;

        if ($request->filled('counter_id')) {
            $destinationCounter = QueueCounter::query()
                ->forTeam($team)
                ->whereKey($request->integer('counter_id'))
                ->firstOrFail();
        }

        $operateQueueDesk->transfer(
            $queueCounter,
            $service,
            $request->user(),
            $destinationCounter,
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket transferred.')]);

        return redirect()->route('queue.counters.desk', [$request->user()->currentTeam, $queueCounter]);
    }

    protected function assertOperable(
        Request $request,
        string $current_team,
        QueueCounter $queueCounter,
        string $ability,
    ): void {
        Gate::authorize($ability, $queueCounter);
        abort_unless($queueCounter->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function counterPayload(QueueCounter $counter, User $user): array
    {
        return [
            'id' => $counter->id,
            'location_id' => $counter->location_id,
            'location_name' => $counter->location?->name,
            'name' => $counter->name,
            'code' => $counter->code,
            'status' => $counter->status->value,
            'status_label' => $counter->status->label(),
            'assigned_user_id' => $counter->assigned_user_id,
            'assigned_user_name' => $counter->assignedUser?->name,
            'service_ids' => $counter->services->pluck('id')->all(),
            'services' => $counter->services->map(fn (QueueService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'code' => $service->code,
            ])->values(),
            'can_operate' => $user->can('operate', $counter),
        ];
    }
}
