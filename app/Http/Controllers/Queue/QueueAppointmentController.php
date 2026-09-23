<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CheckInQueueAppointment;
use App\Actions\Queue\SaveQueueAppointment;
use App\Actions\Queue\SaveQueueAppointmentRules;
use App\Data\QueueAppointmentConfig;
use App\Enums\QueueAppointmentCheckInSource;
use App\Enums\QueueAppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueAppointmentRequest;
use App\Http\Requests\Queue\SaveQueueAppointmentRulesRequest;
use App\Models\Location;
use App\Models\QueueAppointment;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QueueAppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QueueAppointment::class);
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        $settings = QueueSetting::resolveForTeam($team);

        $appointments = QueueAppointment::query()
            ->forTeam($team)
            ->with(['service:id,name', 'location:id,name', 'ticket:id,number,status'])
            ->orderByDesc('scheduled_at')
            ->paginate(25)
            ->through(fn (QueueAppointment $appointment) => $this->payload($appointment));

        return Inertia::render('queue/appointments', [
            'appointments' => $appointments,
            'services' => QueueService::query()->forTeam($team)->where('is_active', true)->with('location:id,name')->orderBy('name')->get()->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'location_id' => $service->location_id,
                'location_name' => $service->location?->name,
            ]),
            'locations' => Location::query()->forTeam($team)->orderBy('name')->get(['id', 'name']),
            'priorities' => QueuePriority::query()->forTeam($team)->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'weight']),
            'rules' => QueueAppointmentConfig::resolve($settings->settings ?? [])->toArray(),
            'statuses' => collect(QueueAppointmentStatus::cases())->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()]),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    public function store(SaveQueueAppointmentRequest $request, SaveQueueAppointment $save): RedirectResponse
    {
        Gate::authorize('create', QueueAppointment::class);
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        $save->handle($team, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment created.')]);

        return back(fallback: route('queue.appointments', $team));
    }

    public function update(SaveQueueAppointmentRequest $request, string $current_team, QueueAppointment $queueAppointment, SaveQueueAppointment $save): RedirectResponse
    {
        Gate::authorize('update', $queueAppointment);
        abort_unless($queueAppointment->team_id === $request->user()->currentTeam?->id, 403);
        $save->handle($request->user()->currentTeam, $request->validated(), $queueAppointment);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment updated.')]);

        return back(fallback: route('queue.appointments', $request->user()->currentTeam));
    }

    public function destroy(Request $request, string $current_team, QueueAppointment $queueAppointment): RedirectResponse
    {
        Gate::authorize('delete', $queueAppointment);
        abort_unless($queueAppointment->team_id === $request->user()->currentTeam?->id, 403);
        $queueAppointment->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment deleted.')]);

        return back(fallback: route('queue.appointments', $request->user()->currentTeam));
    }

    public function checkIn(Request $request, string $current_team, QueueAppointment $queueAppointment, CheckInQueueAppointment $checkIn): RedirectResponse
    {
        Gate::authorize('update', $queueAppointment);
        abort_unless($queueAppointment->team_id === $request->user()->currentTeam?->id, 403);
        $ticket = $checkIn->handle($queueAppointment, QueueAppointmentCheckInSource::Staff, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment checked in as ticket :ticket.', ['ticket' => $ticket->number])]);

        return back(fallback: route('queue.appointments', $request->user()->currentTeam));
    }

    public function updateRules(SaveQueueAppointmentRulesRequest $request, SaveQueueAppointmentRules $save): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('create', QueueAppointment::class);
        $save->handle($team, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Appointment priority rules saved.')]);

        return back(fallback: route('queue.appointments', $team));
    }

    /** @return array<string, mixed> */
    protected function payload(QueueAppointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'customer_name' => $appointment->customer_name,
            'customer_phone' => $appointment->customer_phone,
            'customer_email' => $appointment->customer_email,
            'queue_service_id' => $appointment->queue_service_id,
            'service_name' => $appointment->service->name,
            'location_id' => $appointment->location_id,
            'location_name' => $appointment->location?->name,
            'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
            'reference' => $appointment->reference,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'check_in_source' => $appointment->check_in_source?->label(),
            'checked_in_at' => $appointment->checked_in_at?->toIso8601String(),
            'ticket_number' => $appointment->ticket?->number,
            'check_in_url' => route('queue.appointment.check-in.show', ['queueAppointment' => $appointment, 'source' => 'qr']),
        ];
    }
}
