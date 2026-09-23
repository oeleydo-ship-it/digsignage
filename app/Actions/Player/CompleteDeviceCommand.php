<?php

namespace App\Actions\Player;

use App\Actions\Analytics\RecordScreenAnalytics;
use App\Actions\Emergency\SyncEmergencyDeliveryFromCommand;
use App\Actions\Notifications\DispatchSignageAlert;
use App\Enums\DeviceCommandStatus;
use App\Enums\SignageAlert;
use App\Models\DeviceCommand;
use App\Models\Screen;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompleteDeviceCommand
{
    public function __construct(
        protected SyncEmergencyDeliveryFromCommand $syncEmergencyDelivery,
        protected RecordScreenAnalytics $recordScreenAnalytics,
        protected DispatchSignageAlert $alerts,
    ) {}

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function handle(
        Screen $screen,
        DeviceCommand $command,
        DeviceCommandStatus $status,
        ?array $result = null,
    ): DeviceCommand {
        abort_unless(
            $command->screen_id === $screen->id && $command->team_id === $screen->team_id,
            404,
        );

        abort_unless(in_array($status, [DeviceCommandStatus::Completed, DeviceCommandStatus::Failed], true), 422);

        if (in_array($command->status, [DeviceCommandStatus::Completed, DeviceCommandStatus::Failed], true)) {
            return $command;
        }

        if ($command->status === DeviceCommandStatus::Expired) {
            throw new HttpException(409, __('This command has expired.'));
        }

        $command->forceFill([
            'status' => $status,
            'result' => $result,
            'acknowledged_at' => $command->acknowledged_at ?? now(),
            'sent_at' => $command->sent_at ?? now(),
            'completed_at' => now(),
        ])->save();

        $command = $command->refresh();
        $this->syncEmergencyDelivery->handle($command);

        if ($status === DeviceCommandStatus::Failed) {
            $message = is_array($result) && isset($result['error']) && is_string($result['error'])
                ? $result['error']
                : 'Remote command failed.';
            $this->recordScreenAnalytics->commandFailure($screen, $message);
            $screen->loadMissing('team');
            $this->alerts->queue(
                $screen->team,
                SignageAlert::DeviceCommandFailed,
                __('Command failed: :command on :name', [
                    'command' => $command->command->label(),
                    'name' => $screen->name,
                ]),
                $message,
                [
                    'screen_id' => $screen->id,
                    'command_id' => $command->id,
                ],
                'command_failed:'.$command->id,
                86400,
            );
        }

        return $command;
    }
}
