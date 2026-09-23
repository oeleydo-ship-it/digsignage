<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\QueueCounterStatus;
use App\Events\QueueUpdated;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueAuditSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveQueueCounter
{
    public function __construct(
        protected RecordOrganizationAudit $recordOrganizationAudit,
    ) {
        //
    }

    /**
     * Create or update a counter and the services it can take.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Team $team,
        array $attributes,
        ?QueueCounter $counter = null,
        ?User $actor = null,
    ): QueueCounter {
        return DB::transaction(function () use ($team, $attributes, $counter, $actor) {
            $creating = $counter === null || ! $counter->exists;
            $before = $creating ? null : QueueAuditSnapshot::counter($counter);
            $previousStatus = $counter?->status;
            $previousServiceIds = $counter?->services()->pluck('queue_services.id')->all() ?? [];
            $location = $this->location($team, $attributes['location_id'] ?? null);
            $assignedUser = $this->assignedUser($team, $attributes['assigned_user_id'] ?? null);
            $serviceIds = $this->serviceIds($team, $attributes['service_ids'] ?? [], $location);

            $counter ??= new QueueCounter(['team_id' => $team->id]);

            $counter->fill([
                'location_id' => $location?->id,
                'name' => $attributes['name'],
                'code' => $attributes['code'],
                'status' => QueueCounterStatus::from($attributes['status']),
                'assigned_user_id' => $assignedUser?->id,
            ]);

            $counter->save();
            $counter->services()->sync($serviceIds);
            $affectedServiceIds = array_values(array_unique([...$previousServiceIds, ...$serviceIds]));

            foreach ($affectedServiceIds as $serviceId) {
                event(new QueueUpdated(
                    teamId: $team->id,
                    serviceId: (int) $serviceId,
                    counterId: $counter->id,
                    locationId: $counter->location_id,
                    counterName: $counter->name,
                ));
            }

            $counter = $counter->refresh()->load(['location', 'assignedUser', 'services']);
            $action = match (true) {
                $counter->status === QueueCounterStatus::Open
                    && ($creating || $previousStatus !== QueueCounterStatus::Open) => AuditAction::QueueCounterOpened,
                $counter->status === QueueCounterStatus::Closed
                    && ($creating || $previousStatus !== QueueCounterStatus::Closed) => AuditAction::QueueCounterClosed,
                default => AuditAction::QueueCounterChanged,
            };

            $this->recordOrganizationAudit->handle(
                $team,
                $action,
                $actor,
                'queue_counter',
                $counter->id,
                $before,
                QueueAuditSnapshot::counter($counter),
            );

            return $counter;
        });
    }

    protected function location(Team $team, mixed $locationId): ?Location
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $location = Location::query()
            ->forTeam($team)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => __('The selected location is invalid.'),
            ]);
        }

        return $location;
    }

    protected function assignedUser(Team $team, mixed $userId): ?User
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $user = $team->members()->whereKey($userId)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'assigned_user_id' => __('The selected staff member is invalid.'),
            ]);
        }

        return $user;
    }

    /**
     * @param  array<int, mixed>  $serviceIds
     * @return list<int>
     */
    protected function serviceIds(Team $team, array $serviceIds, ?Location $location): array
    {
        $ids = array_values(array_unique(array_map('intval', $serviceIds)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'service_ids' => __('Select at least one service for this counter.'),
            ]);
        }

        $services = QueueService::query()
            ->forTeam($team)
            ->whereKey($ids)
            ->get(['id', 'location_id']);

        if ($services->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'service_ids' => __('One or more selected services are invalid.'),
            ]);
        }

        if ($location !== null && $services->contains(
            fn (QueueService $service): bool => $service->location_id !== null
                && $service->location_id !== $location->id,
        )) {
            throw ValidationException::withMessages([
                'service_ids' => __('Counters may only serve services at the same location or services available at all locations.'),
            ]);
        }

        return $ids;
    }
}
