<?php

namespace App\Actions\Emergency;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\EmergencyAuditAction;
use App\Enums\EmergencyDeliveryStatus;
use App\Models\DeviceCommand;
use App\Models\EmergencyDelivery;

class SyncEmergencyDeliveryFromCommand
{
    public function __construct(protected RecordEmergencyAudit $audit) {}

    public function handle(DeviceCommand $command): void
    {
        $payload = $command->payload ?? [];
        $emergencyId = (int) ($payload['emergency_id'] ?? 0);

        if ($emergencyId === 0) {
            return;
        }

        $delivery = EmergencyDelivery::query()
            ->where('emergency_id', $emergencyId)
            ->where('screen_id', $command->screen_id)
            ->first();

        if ($delivery === null) {
            return;
        }

        if ($command->command === DeviceCommandType::EmergencyStart) {
            if ($command->status === DeviceCommandStatus::Acknowledged) {
                $delivery->forceFill([
                    'status' => EmergencyDeliveryStatus::Acknowledged,
                    'acknowledged_at' => $command->acknowledged_at ?? now(),
                    'device_command_id' => $command->id,
                ])->save();

                $this->audit->handle(
                    $delivery->emergency,
                    EmergencyAuditAction::Acknowledged,
                    null,
                    $delivery->screen_id,
                    ['command_id' => $command->id],
                );
            }

            if ($command->status === DeviceCommandStatus::Completed) {
                $delivery->forceFill([
                    'status' => EmergencyDeliveryStatus::Acknowledged,
                    'acknowledged_at' => $delivery->acknowledged_at ?? $command->acknowledged_at ?? now(),
                    'completed_at' => $command->completed_at ?? now(),
                    'result' => $command->result,
                    'device_command_id' => $command->id,
                ])->save();
            }

            if ($command->status === DeviceCommandStatus::Failed) {
                $delivery->forceFill([
                    'status' => EmergencyDeliveryStatus::Failed,
                    'completed_at' => $command->completed_at ?? now(),
                    'result' => $command->result,
                ])->save();

                $this->audit->handle(
                    $delivery->emergency,
                    EmergencyAuditAction::Failed,
                    null,
                    $delivery->screen_id,
                    ['command_id' => $command->id],
                );
            }
        }
    }
}
