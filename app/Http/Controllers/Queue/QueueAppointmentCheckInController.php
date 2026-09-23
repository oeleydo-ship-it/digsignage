<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\CheckInQueueAppointment;
use App\Enums\QueueAppointmentCheckInSource;
use App\Http\Controllers\Controller;
use App\Models\QueueAppointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueAppointmentCheckInController extends Controller
{
    public function show(Request $request, QueueAppointment $queueAppointment): Response
    {
        $queueAppointment->load(['team:id,name', 'service:id,name', 'location:id,name', 'ticket:id,number']);

        return Inertia::render('queue/appointment-check-in', [
            'appointment' => [
                'customer_name' => $queueAppointment->customer_name,
                'service_name' => $queueAppointment->service->name,
                'location_name' => $queueAppointment->location?->name,
                'scheduled_at' => $queueAppointment->scheduled_at->toIso8601String(),
                'reference' => $queueAppointment->reference,
                'status' => $queueAppointment->status->value,
                'ticket_number' => $queueAppointment->ticket?->number,
                'team_name' => $queueAppointment->team->name,
            ],
            'source' => $request->string('source')->toString() === 'qr' ? 'qr' : 'reference',
        ]);
    }

    public function store(Request $request, QueueAppointment $queueAppointment, CheckInQueueAppointment $checkIn): RedirectResponse
    {
        $source = $request->string('source')->toString() === 'qr'
            ? QueueAppointmentCheckInSource::Qr
            : QueueAppointmentCheckInSource::Reference;
        $checkIn->handle($queueAppointment, $source);

        return back();
    }
}
