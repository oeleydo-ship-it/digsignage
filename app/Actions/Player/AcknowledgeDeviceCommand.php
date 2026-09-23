<?php

namespace App\Actions\Player;

use App\Actions\Emergency\SyncEmergencyDeliveryFromCommand;
use App\Enums\DeviceCommandStatus;
use App\Models\DeviceCommand;
use App\Models\Screen;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcknowledgeDeviceCommand
{
    public function __construct(protected SyncEmergencyDeliveryFromCommand $syncEmergencyDelivery) {}

    public function handle(Screen $screen, DeviceCommand $command): DeviceCommand
    {
        $this->assertOwned($screen, $command);

        if (in_array($command->status, [
            DeviceCommandStatus::Acknowledged,
            DeviceCommandStatus::Completed,
            DeviceCommandStatus::Failed,
        ], true)) {
            return $command;
        }

        if ($command->status === DeviceCommandStatus::Expired) {
            throw new HttpException(409, __('This command has expired.'));
        }

        $command->forceFill([
            'status' => DeviceCommandStatus::Acknowledged,
            'acknowledged_at' => $command->acknowledged_at ?? now(),
            'sent_at' => $command->sent_at ?? now(),
        ])->save();

        $command = $command->refresh();
        $this->syncEmergencyDelivery->handle($command);

        return $command;
    }

    protected function assertOwned(Screen $screen, DeviceCommand $command): void
    {
        abort_unless(
            $command->screen_id === $screen->id && $command->team_id === $screen->team_id,
            404,
        );
    }
}
