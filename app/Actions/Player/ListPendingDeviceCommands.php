<?php

namespace App\Actions\Player;

use App\Enums\DeviceCommandStatus;
use App\Models\DeviceCommand;
use App\Models\Screen;

class ListPendingDeviceCommands
{
    public function __construct(protected ExpireDeviceCommands $expireCommands) {}

    /**
     * Return deliverable commands for a player, marking pending rows as sent.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Screen $screen): array
    {
        $this->expireCommands->handle($screen);

        $commands = DeviceCommand::query()
            ->where('screen_id', $screen->id)
            ->whereIn('status', [
                DeviceCommandStatus::Pending->value,
                DeviceCommandStatus::Sent->value,
            ])
            ->orderBy('id')
            ->get();

        $payload = [];

        foreach ($commands as $command) {
            if ($command->status === DeviceCommandStatus::Pending) {
                $command->forceFill([
                    'status' => DeviceCommandStatus::Sent,
                    'sent_at' => $command->sent_at ?? now(),
                ])->save();
            }

            $payload[] = $command->playerPayload();
        }

        return $payload;
    }
}
