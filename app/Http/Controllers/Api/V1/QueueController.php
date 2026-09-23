<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Queue\BuildQueueOperationsDashboard;
use App\Actions\Queue\CallNextQueueTicket;
use App\Actions\Queue\CancelQueueTicket;
use App\Actions\Queue\IssueQueueTicket;
use App\Actions\Queue\OperateQueueDesk;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Http\Requests\Api\V1\IssueQueueTicketRequest;
use App\Http\Requests\Api\V1\TransferQueueTicketRequest;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueController extends Controller
{
    public function services(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', QueueService::class);

        $filters = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);

        $services = QueueService::query()
            ->forTeam($this->team($request))
            ->with('location:id,name')
            ->withCount([
                'tickets as waiting_count' => fn ($query) => $query->where('status', QueueTicketStatus::Waiting),
            ])
            ->when(isset($filters['location_id']), fn ($query) => $query->where('location_id', $filters['location_id']))
            ->when(array_key_exists('active', $filters), fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (QueueService $service) => PartnerApi::queueService($service));

        return PartnerApi::paginated($services);
    }

    public function storeTicket(
        IssueQueueTicketRequest $request,
        IssueQueueTicket $issueQueueTicket,
    ): JsonResponse {
        Gate::authorize('create', QueueTicket::class);

        $team = $this->team($request);
        QueueSetting::resolveForTeam($team);
        $service = $this->findForTeam($request, QueueService::class, $request->integer('queue_service_id'));
        $ticket = $issueQueueTicket->handle(
            $team,
            $service,
            [...$request->validated(), 'source' => QueueTicketSource::Api->value],
            $request->user(),
        );

        return PartnerApi::item(
            PartnerApi::queueTicket($ticket),
            $ticket->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function showTicket(Request $request, int $ticket): JsonResponse
    {
        $model = $this->findForTeam($request, QueueTicket::class, $ticket);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::queueTicket($model, withEvents: true));
    }

    public function cancelTicket(
        Request $request,
        CancelQueueTicket $cancelQueueTicket,
        int $ticket,
    ): JsonResponse {
        $model = $this->findForTeam($request, QueueTicket::class, $ticket);
        Gate::authorize('cancel', $model);

        return PartnerApi::item(PartnerApi::queueTicket(
            $cancelQueueTicket->handle($model, $request->user()),
        ));
    }

    public function next(
        Request $request,
        CallNextQueueTicket $callNextQueueTicket,
        int $counter,
    ): JsonResponse {
        $model = $this->findForTeam($request, QueueCounter::class, $counter);
        Gate::authorize('call', $model);

        return PartnerApi::item(PartnerApi::queueTicket(
            $callNextQueueTicket->handle($model, $request->user()),
        ));
    }

    public function recall(
        Request $request,
        OperateQueueDesk $operateQueueDesk,
        int $ticket,
    ): JsonResponse {
        [, $counter] = $this->activeTicketAndCounter($request, $ticket);
        Gate::authorize('call', $counter);

        return PartnerApi::item(PartnerApi::queueTicket(
            $operateQueueDesk->recall($counter, $request->user()),
        ));
    }

    public function complete(
        Request $request,
        OperateQueueDesk $operateQueueDesk,
        int $ticket,
    ): JsonResponse {
        [, $counter] = $this->activeTicketAndCounter($request, $ticket);
        Gate::authorize('complete', $counter);

        return PartnerApi::item(PartnerApi::queueTicket(
            $operateQueueDesk->complete($counter, $request->user()),
        ));
    }

    public function transfer(
        TransferQueueTicketRequest $request,
        OperateQueueDesk $operateQueueDesk,
        int $ticket,
    ): JsonResponse {
        [, $counter] = $this->activeTicketAndCounter($request, $ticket);
        Gate::authorize('transfer', $counter);
        $service = $this->findForTeam(
            $request,
            QueueService::class,
            $request->integer('queue_service_id'),
        );
        $destinationCounter = $request->filled('counter_id')
            ? $this->findForTeam($request, QueueCounter::class, $request->integer('counter_id'))
            : null;

        return PartnerApi::item(PartnerApi::queueTicket($operateQueueDesk->transfer(
            $counter,
            $service,
            $request->user(),
            $destinationCounter,
            $request->validated('reason'),
        )));
    }

    public function status(Request $request, BuildQueueOperationsDashboard $dashboard): JsonResponse
    {
        Gate::authorize('viewAny', QueueTicket::class);
        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $this->team($request)->id),
            ],
        ]);
        $status = $dashboard->handle($this->team($request), $validated['location_id'] ?? null);
        unset($status['stats']['longest_waiting']['customer_name']);

        return PartnerApi::item($status);
    }

    /**
     * @return array{QueueTicket, QueueCounter}
     */
    protected function activeTicketAndCounter(Request $request, int $ticket): array
    {
        $model = $this->findForTeam($request, QueueTicket::class, $ticket);
        Gate::authorize('view', $model);

        if (
            $model->counter_id === null
            || ! in_array($model->status, [QueueTicketStatus::Called, QueueTicketStatus::Serving], true)
        ) {
            throw ValidationException::withMessages([
                'ticket' => __('This ticket is not active at a counter.'),
            ]);
        }

        $counter = $this->findForTeam($request, QueueCounter::class, $model->counter_id);

        if ($counter->currentTicket()?->id !== $model->id) {
            throw ValidationException::withMessages([
                'ticket' => __('This ticket is no longer active at its counter.'),
            ]);
        }

        return [$model, $counter];
    }
}
