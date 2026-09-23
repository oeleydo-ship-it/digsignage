<?php

namespace App\Events;

use App\Models\DeviceCommand;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class DeviceCommandIssued implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public DeviceCommand $command) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $this->command->loadMissing('screen');
        $uuid = $this->command->screen->device_uuid;

        if (! is_string($uuid) || $uuid === '') {
            return [];
        }

        return [new PrivateChannel('player.'.$uuid)];
    }

    public function broadcastAs(): string
    {
        return 'command';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->command->playerPayload();
    }
}
