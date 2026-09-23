<?php

namespace App\Actions\Queue;

use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Models\Location;
use App\Models\QueueService;
use App\Models\Team;
use App\Support\QueueOpeningHours;
use App\Support\QueueTicketNumbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveQueueService
{
    /**
     * Create or update a queue service within a team.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes, ?QueueService $service = null): QueueService
    {
        return DB::transaction(function () use ($team, $attributes, $service) {
            $location = $this->location($team, $attributes['location_id'] ?? null);
            $creating = $service === null || ! $service->exists;
            $reset = QueueNumberingReset::from($attributes['numbering_reset']);
            $strategy = QueueStrategy::from($attributes['queue_strategy'] ?? QueueStrategy::Fifo->value);

            $service ??= new QueueService(['team_id' => $team->id]);

            $service->fill([
                'location_id' => $location?->id,
                'name' => $attributes['name'],
                'code' => $attributes['code'],
                'ticket_prefix' => $attributes['ticket_prefix'],
                'description' => $attributes['description'] ?? null,
                'opening_hours' => QueueOpeningHours::normalize($attributes['opening_hours'] ?? null),
                'average_service_duration_seconds' => $attributes['average_service_duration_seconds'],
                'max_queue_capacity' => $attributes['max_queue_capacity'] ?? null,
                'numbering_reset' => $reset,
                'priority_rules' => $attributes['priority_rules'] ?? [],
                'default_priority' => $attributes['default_priority'] ?? 0,
                'queue_strategy' => $strategy,
                'starvation' => $attributes['starvation'] ?? $service->starvation,
                'display_color' => $attributes['display_color'],
                'is_active' => array_key_exists('is_active', $attributes)
                    ? (bool) $attributes['is_active']
                    : ($creating ? true : $service->is_active),
            ]);

            if ($creating) {
                $service->next_sequence = 1;
                $service->last_issued = null;
                $service->sequence_period = QueueTicketNumbering::periodKey($reset, now());
            }

            $service->save();

            return $service->refresh();
        });
    }

    /**
     * Resolve a location in the same team.
     */
    protected function location(Team $team, mixed $locationId): ?Location
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $location = Location::query()
            ->forTeam($team)
            ->whereKey($locationId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => __('The selected location is invalid.'),
            ]);
        }

        return $location;
    }
}
