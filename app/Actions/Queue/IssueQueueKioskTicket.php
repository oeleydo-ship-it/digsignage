<?php

namespace App\Actions\Queue;

use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Models\QueueKiosk;
use App\Models\QueueService;
use App\Models\QueueTicket;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IssueQueueKioskTicket
{
    public function __construct(
        protected IssueQueueTicket $issueQueueTicket,
    ) {
        //
    }

    /**
     * Issue a kiosk ticket and compute wait estimates for the YOUR TICKET screen.
     *
     * @return array{ticket: QueueTicket, people_ahead: int, estimated_wait_seconds: int, estimated_wait_minutes: int}
     */
    public function handle(QueueKiosk $kiosk, QueueService $service, ?string $idempotencyKey = null): array
    {
        if (! $kiosk->is_active) {
            throw new HttpException(403, __('This kiosk is not active.'));
        }

        if ($service->team_id !== $kiosk->team_id) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('The selected service is invalid.'),
            ]);
        }

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('This queue service is not accepting tickets.'),
            ]);
        }

        if ($kiosk->location_id !== null && $service->location_id !== $kiosk->location_id) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('This service is not available at this kiosk.'),
            ]);
        }

        $ticket = $this->issueQueueTicket->handle(
            $kiosk->team,
            $service,
            [
                'source' => QueueTicketSource::Kiosk,
                'idempotency_key' => $idempotencyKey,
            ],
        );

        $peopleAhead = QueueTicket::query()
            ->where('queue_service_id', $service->id)
            ->where('status', QueueTicketStatus::Waiting)
            ->where('id', '!=', $ticket->id)
            ->count();

        $estimatedWaitSeconds = $peopleAhead * max(0, $service->average_service_duration_seconds);
        $estimatedWaitMinutes = $estimatedWaitSeconds === 0
            ? 0
            : (int) max(1, (int) ceil($estimatedWaitSeconds / 60));

        return [
            'ticket' => $ticket->load(['service:id,name', 'location:id,name']),
            'people_ahead' => $peopleAhead,
            'estimated_wait_seconds' => $estimatedWaitSeconds,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
        ];
    }
}
