<?php

namespace App\Actions\Player;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Events\DeviceCommandIssued;
use App\Models\Channel;
use App\Models\DeviceCommand;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DispatchDeviceCommand
{
    /**
     * Create a device command, apply immediate side effects, and broadcast it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Screen $screen,
        DeviceCommandType $type,
        array $payload = [],
        ?User $issuer = null,
    ): DeviceCommand {
        abort_unless($screen->isPaired(), 422, __('Pair the screen before sending commands.'));

        $payload = $this->preparePayload($screen, $type, $payload);

        $command = DB::transaction(function () use ($screen, $type, $payload, $issuer) {
            if ($type === DeviceCommandType::ChangeChannel) {
                $screen->forceFill([
                    'current_channel_id' => $payload['channel_id'],
                ])->save();
            }

            return DeviceCommand::query()->create([
                'team_id' => $screen->team_id,
                'screen_id' => $screen->id,
                'issued_by' => $issuer?->id,
                'command' => $type,
                'payload' => $payload,
                'status' => DeviceCommandStatus::Pending,
                'expires_at' => now()->addMinutes((int) config('signage.player.command_ttl_minutes')),
            ]);
        });

        $command->load('screen');

        try {
            broadcast(new DeviceCommandIssued($command));
        } catch (Throwable) {
            // REST polling delivers the command when Reverb is unavailable.
        }

        $command->forceFill([
            'status' => DeviceCommandStatus::Sent,
            'sent_at' => now(),
        ])->save();

        if ($issuer !== null
            && ! in_array($type, [DeviceCommandType::EmergencyStart, DeviceCommandType::EmergencyStop], true)
        ) {
            app(RecordOrganizationAudit::class)->handle(
                $screen->team,
                AuditAction::DeviceCommandExecuted,
                $issuer,
                'device_command',
                $command->id,
                null,
                [
                    'command' => $type->value,
                    'screen_id' => $screen->id,
                    'screen' => $screen->name,
                ],
            );
        }

        return $command->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(Screen $screen, DeviceCommandType $type, array $payload): array
    {
        if ($type === DeviceCommandType::ChangeChannel) {
            $channelId = (int) ($payload['channel_id'] ?? 0);
            $channel = Channel::query()
                ->where('team_id', $screen->team_id)
                ->find($channelId);

            if ($channel === null) {
                throw ValidationException::withMessages([
                    'payload.channel_id' => __('The selected channel is invalid.'),
                ]);
            }

            return ['channel_id' => $channel->id];
        }

        if ($type === DeviceCommandType::EmergencyStart) {
            return [
                'emergency_id' => isset($payload['emergency_id']) ? (int) $payload['emergency_id'] : null,
                'severity' => isset($payload['severity']) ? (string) $payload['severity'] : 'emergency',
                'title' => isset($payload['title']) ? (string) $payload['title'] : 'Emergency',
                'message' => isset($payload['message']) ? (string) $payload['message'] : '',
                'instructions' => isset($payload['instructions']) ? (string) $payload['instructions'] : '',
                'background' => isset($payload['background']) ? (string) $payload['background'] : '#b91c1c',
            ];
        }

        if ($type === DeviceCommandType::EmergencyStop) {
            return [
                'emergency_id' => isset($payload['emergency_id']) ? (int) $payload['emergency_id'] : null,
            ];
        }

        if ($type === DeviceCommandType::UpdateSettings) {
            return $payload;
        }

        return [];
    }
}
