<?php

namespace App\Events;

use App\Data\QueueVoiceConfig;
use App\Models\QueueSetting;
use App\Support\QueueDisplayScreenResolver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Throwable;

class QueueUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(
        public int $teamId,
        public int $serviceId,
        public ?int $ticketId = null,
        public ?string $ticketNumber = null,
        public ?string $status = null,
        public ?int $counterId = null,
        public ?int $locationId = null,
        public ?string $counterName = null,
        public ?string $calledAt = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('queue.service.'.$this->serviceId)];

        try {
            $screens = app(QueueDisplayScreenResolver::class)->forService($this->teamId, $this->serviceId);

            foreach ($screens as $screen) {
                $channels[] = new PrivateChannel('player.'.$screen->device_uuid);
            }
        } catch (Throwable) {
            // Public virtual-queue updates and REST player polling remain available.
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $settings = QueueSetting::query()->where('team_id', $this->teamId)->first();

        return [
            'team_id' => $this->teamId,
            'service_id' => $this->serviceId,
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'status' => $this->status,
            'counter_id' => $this->counterId,
            'location_id' => $this->locationId,
            'counter_name' => $this->counterName,
            'called_at' => $this->calledAt,
            'voice' => QueueVoiceConfig::resolve($settings?->settings ?? [])->toArray(),
        ];
    }
}
