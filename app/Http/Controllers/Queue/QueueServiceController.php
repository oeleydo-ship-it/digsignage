<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\DeleteQueueService;
use App\Actions\Queue\SaveQueueService;
use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueServiceRequest;
use App\Models\Location;
use App\Models\QueueService;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QueueServiceController extends Controller
{
    /**
     * Display queue services for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QueueService::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $locationId = $request->integer('location_id') ?: null;

        $services = QueueService::query()
            ->forTeam($team)
            ->with('location:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('ticket_prefix', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('name')
            ->get()
            ->map(fn (QueueService $service) => $this->servicePayload($service, $team));

        $locations = Location::query()
            ->forTeam($team)
            ->orderBy('path')
            ->orderBy('name')
            ->get(['id', 'name', 'depth'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'depth' => $location->depth,
                'join_url' => route('queue.virtual.show', [
                    'team' => $team,
                    'location' => $location->id,
                ]),
            ]);

        return Inertia::render('queue/services', [
            'services' => $services,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'location_id' => $locationId,
            ],
            'locations' => $locations,
            'virtualJoinUrl' => route('queue.virtual.show', $team),
            'numberingResets' => collect(QueueNumberingReset::cases())->map(fn (QueueNumberingReset $reset) => [
                'value' => $reset->value,
                'label' => $reset->label(),
            ]),
            'strategies' => collect(QueueStrategy::cases())->map(fn (QueueStrategy $strategy) => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
            ]),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    /**
     * Store a newly created queue service.
     */
    public function store(SaveQueueServiceRequest $request, SaveQueueService $saveQueueService): RedirectResponse
    {
        Gate::authorize('create', QueueService::class);

        $saveQueueService->handle($request->user()->currentTeam, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue service created.')]);

        return back(fallback: route('queue.services', $request->user()->currentTeam));
    }

    /**
     * Update the specified queue service.
     */
    public function update(
        SaveQueueServiceRequest $request,
        string $current_team,
        QueueService $queueService,
        SaveQueueService $saveQueueService,
    ): RedirectResponse {
        Gate::authorize('update', $queueService);
        abort_unless($queueService->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveQueueService->handle($request->user()->currentTeam, $request->validated(), $queueService);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue service updated.')]);

        return back(fallback: route('queue.services', $request->user()->currentTeam));
    }

    /**
     * Remove the specified queue service.
     */
    public function destroy(
        Request $request,
        string $current_team,
        QueueService $queueService,
        DeleteQueueService $deleteQueueService,
    ): RedirectResponse {
        Gate::authorize('delete', $queueService);
        abort_unless($queueService->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteQueueService->handle($queueService);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue service deleted.')]);

        return back(fallback: route('queue.services', $request->user()->currentTeam));
    }

    /**
     * @return array<string, mixed>
     */
    protected function servicePayload(QueueService $service, Team $team): array
    {
        return [
            'id' => $service->id,
            'location_id' => $service->location_id,
            'location_name' => $service->location?->name,
            'name' => $service->name,
            'code' => $service->code,
            'ticket_prefix' => $service->ticket_prefix,
            'description' => $service->description,
            'opening_hours' => $service->opening_hours,
            'average_service_duration_seconds' => $service->average_service_duration_seconds,
            'max_queue_capacity' => $service->max_queue_capacity,
            'numbering_reset' => $service->numbering_reset->value,
            'numbering_reset_label' => $service->numbering_reset->label(),
            'next_sequence' => $service->next_sequence,
            'last_issued' => $service->last_issued,
            'sequence_period' => $service->sequence_period,
            'next_ticket_number' => $service->nextTicketNumber(),
            'priority_rules' => $service->priority_rules ?? [],
            'default_priority' => $service->default_priority,
            'queue_strategy' => $service->queue_strategy->value,
            'queue_strategy_label' => $service->queue_strategy->label(),
            'starvation' => $service->starvation,
            'display_color' => $service->display_color,
            'is_active' => $service->is_active,
            'join_url' => route('queue.virtual.show', [
                'team' => $team,
                'service' => $service->id,
            ]),
        ];
    }
}
