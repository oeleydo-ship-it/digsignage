<?php

namespace App\Actions\Queue;

use App\Enums\PlanFeature;
use App\Enums\QueueAppointmentStatus;
use App\Enums\QueueNotificationEvent;
use App\Models\QueueAppointment;
use App\Models\QueueNotificationRule;
use App\Models\Team;
use App\Support\TeamQuota;

class EvaluateApproachingQueueAppointments
{
    public function __construct(
        protected DispatchQueueCustomerNotification $dispatch,
        protected TeamQuota $quota,
    ) {}

    public function handle(): int
    {
        $groups = QueueNotificationRule::query()
            ->where('event', QueueNotificationEvent::AppointmentApproaching->value)
            ->where('is_enabled', true)
            ->get()
            ->groupBy('team_id');
        $evaluated = 0;

        foreach ($groups as $teamId => $rules) {
            $team = Team::query()->find($teamId);

            if ($team === null || ! $this->quota->allowsFeature($team, PlanFeature::QueueManagement)) {
                continue;
            }

            $minutes = max(1, (int) ($rules->first()->minutes_before ?? 60));
            QueueAppointment::query()
                ->where('team_id', $teamId)
                ->where('status', QueueAppointmentStatus::Scheduled->value)
                ->whereBetween('scheduled_at', [now(), now()->addMinutes($minutes)])
                ->each(function (QueueAppointment $appointment) use (&$evaluated): void {
                    $this->dispatch->forAppointment($appointment);
                    $evaluated++;
                });
        }

        return $evaluated;
    }
}
