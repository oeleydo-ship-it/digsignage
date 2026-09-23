<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CheckInQueueAppointment;
use App\Actions\Queue\IssueQueueKioskTicket;
use App\Enums\QueueAppointmentCheckInSource;
use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\IssueQueueKioskTicketRequest;
use App\Models\QueueAppointment;
use App\Models\QueueKiosk;
use App\Models\QueueService;
use App\Models\QueueTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QueueKioskServeController extends Controller
{
    /**
     * Full-screen self-service kiosk. Authenticated by the kiosk token in the URL.
     */
    public function show(Request $request, QueueKiosk $queueKiosk): Response
    {
        abort_unless($queueKiosk->is_active, 403);

        $queueKiosk->load(['team:id,name', 'location:id,name']);

        $services = QueueService::query()
            ->where('team_id', $queueKiosk->team_id)
            ->where('is_active', true)
            ->when(
                $queueKiosk->location_id !== null,
                fn ($query) => $query->where('location_id', $queueKiosk->location_id),
            )
            ->withCount([
                'tickets as waiting_count' => fn ($query) => $query->where('status', QueueTicketStatus::Waiting),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'display_color']);

        $issued = $request->session()->get('issuedKioskTicket');

        return Inertia::render('queue/kiosk-serve', [
            'kiosk' => [
                'id' => $queueKiosk->id,
                'name' => $queueKiosk->name,
                'printer_enabled' => $queueKiosk->printer_enabled,
                'location_name' => $queueKiosk->location?->name,
                'team_name' => $queueKiosk->team->name,
                'branding' => $queueKiosk->resolvedBranding()->toArray(),
            ],
            'services' => $services->map(fn (QueueService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'display_color' => $service->display_color,
                'waiting_count' => (int) $service->getAttribute('waiting_count'),
            ])->values(),
            'issuedTicket' => is_array($issued) ? $issued : null,
        ]);
    }

    /**
     * Issue a ticket from the kiosk tablet.
     */
    public function issue(
        IssueQueueKioskTicketRequest $request,
        QueueKiosk $queueKiosk,
        IssueQueueKioskTicket $issueQueueKioskTicket,
    ): RedirectResponse {
        $service = QueueService::query()
            ->where('team_id', $queueKiosk->team_id)
            ->whereKey($request->integer('queue_service_id'))
            ->firstOrFail();

        $result = $issueQueueKioskTicket->handle(
            $queueKiosk,
            $service,
            $request->validated('idempotency_key'),
        );
        $ticket = $result['ticket'];

        $this->flashIssuedTicket($request, $queueKiosk, $ticket, $result['people_ahead'], $result['estimated_wait_seconds'], $result['estimated_wait_minutes']);

        return back(fallback: route('queue.kiosk.serve', $queueKiosk));
    }

    public function checkInAppointment(
        Request $request,
        QueueKiosk $queueKiosk,
        CheckInQueueAppointment $checkIn,
    ): RedirectResponse {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:32']]);
        $appointment = QueueAppointment::query()
            ->where('team_id', $queueKiosk->team_id)
            ->where('reference', strtoupper($validated['reference']))
            ->first();

        if ($appointment === null || (
            $queueKiosk->location_id !== null
                && $appointment->location_id !== null
                && $queueKiosk->location_id !== $appointment->location_id
        )) {
            throw ValidationException::withMessages([
                'reference' => __('No appointment with that reference is available at this kiosk.'),
            ]);
        }

        $ticket = $checkIn->handle($appointment, QueueAppointmentCheckInSource::Kiosk);
        $ticket->load(['service', 'location']);
        $peopleAhead = max(0, (int) $ticket->queue_position - 1);
        $estimatedSeconds = $peopleAhead * $ticket->service->average_service_duration_seconds;
        $this->flashIssuedTicket($request, $queueKiosk, $ticket, $peopleAhead, $estimatedSeconds, (int) ceil($estimatedSeconds / 60));

        return back(fallback: route('queue.kiosk.serve', $queueKiosk));
    }

    protected function flashIssuedTicket(Request $request, QueueKiosk $queueKiosk, QueueTicket $ticket, int $peopleAhead, int $estimatedSeconds, int $estimatedMinutes): void
    {
        $request->session()->flash('issuedKioskTicket', [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'service_name' => $ticket->service->name,
            'location_name' => $ticket->location?->name,
            'people_ahead' => $peopleAhead,
            'estimated_wait_seconds' => $estimatedSeconds,
            'estimated_wait_minutes' => $estimatedMinutes,
            'issued_at' => $ticket->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'qr_value' => implode("\n", array_filter([
                $queueKiosk->team->name,
                __('Ticket: :number', ['number' => $ticket->number]),
                __('Service: :service', ['service' => $ticket->service->name]),
                $ticket->location ? __('Location: :location', ['location' => $ticket->location->name]) : null,
                $ticket->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ])),
        ]);
    }
}
