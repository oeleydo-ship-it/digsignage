<?php

namespace App\Actions\Emergency;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Actions\Player\DispatchDeviceCommand;
use App\Enums\DeviceCommandType;
use App\Enums\EmergencyAuditAction;
use App\Enums\EmergencyStatus;
use App\Enums\SignageAlert;
use App\Enums\WebhookEvent;
use App\Models\Emergency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StopEmergencyBroadcast
{
    public function __construct(
        protected DispatchDeviceCommand $dispatchDeviceCommand,
        protected RecordEmergencyAudit $audit,
        protected DispatchSignageAlert $alerts,
    ) {}

    public function handle(Emergency $emergency, ?User $user, bool $confirmed = true, bool $expired = false): Emergency
    {
        if (! $confirmed && ! $expired) {
            throw ValidationException::withMessages([
                'confirmed' => __('Confirm this action before stopping the broadcast.'),
            ]);
        }

        if (! in_array($emergency->status, [EmergencyStatus::Active, EmergencyStatus::Scheduled], true)) {
            throw ValidationException::withMessages([
                'status' => __('This broadcast is not running.'),
            ]);
        }

        $status = $expired ? EmergencyStatus::Expired : EmergencyStatus::Stopped;

        DB::transaction(function () use ($emergency, $user, $status, $expired) {
            $emergency->forceFill([
                'status' => $status,
                'stopped_at' => now(),
                'stopped_by' => $user?->id,
            ])->save();

            $this->audit->handle(
                $emergency,
                $expired ? EmergencyAuditAction::Expired : EmergencyAuditAction::Stopped,
                $user,
                null,
                ['status' => $status->value],
            );
        });

        $emergency->load('deliveries.screen');

        foreach ($emergency->deliveries as $delivery) {
            $screen = $delivery->screen;

            if (! $screen->isPaired()) {
                continue;
            }

            try {
                $this->dispatchDeviceCommand->handle(
                    $screen,
                    DeviceCommandType::EmergencyStop,
                    ['emergency_id' => $emergency->id],
                    $user,
                );
            } catch (Throwable) {
                // Pairing can change between start and stop; delivery history remains.
            }
        }

        $emergency->loadMissing('team');
        $this->alerts->queue(
            $emergency->team,
            SignageAlert::EmergencyStopped,
            __('Emergency stopped: :title', ['title' => $emergency->title]),
            $expired
                ? __('The emergency broadcast expired.')
                : __('The emergency broadcast was stopped.'),
            ['emergency_id' => $emergency->id, 'expired' => $expired],
            'emergency_stopped:'.$emergency->id,
            60,
        );

        app(DispatchPartnerWebhook::class)->handle(
            $emergency->team,
            WebhookEvent::EmergencyStopped,
            ['id' => $emergency->id, 'title' => $emergency->title, 'expired' => $expired],
        );

        return $emergency->fresh(['deliveries', 'audits']) ?? $emergency;
    }
}
