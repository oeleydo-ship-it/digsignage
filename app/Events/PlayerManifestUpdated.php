<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PlayerManifestUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(public string $deviceUuid) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('player.'.$this->deviceUuid)];
    }

    public function broadcastAs(): string
    {
        return 'manifest.updated';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['device_uuid' => $this->deviceUuid];
    }
}
