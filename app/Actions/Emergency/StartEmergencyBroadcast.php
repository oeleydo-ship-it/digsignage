<?php

namespace App\Actions\Emergency;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Actions\Notifications\DispatchSignageAlert;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Actions\Player\DispatchDeviceCommand;
use App\Enums\AuditAction;
use App\Enums\DeviceCommandType;
use App\Enums\EmergencyAuditAction;
use App\Enums\EmergencyDeliveryStatus;
use App\Enums\EmergencyStatus;
use App\Enums\SignageAlert;
use App\Enums\WebhookEvent;
use App\Models\Emergency;
use App\Models\EmergencyDelivery;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartEmergencyBroadcast
{
    public function __construct(
        protected ResolveEmergencyScreens $resolveScreens,
        protected DispatchDeviceCommand $dispatchDeviceCommand,
        protected RecordEmergencyAudit $audit,
        protected DispatchSignageAlert $alerts,
    ) {}

    public function handle(Emergency $emergency, ?User $user, bool $confirmed = true): Emergency
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirmed' => __('Confirm this broadcast before starting it.'),
            ]);
        }

        if (! in_array($emergency->status, [EmergencyStatus::Draft, EmergencyStatus::Scheduled], true)) {
            throw ValidationException::withMessages([
                'status' => __('Only draft or scheduled broadcasts can be started.'),
            ]);
        }

        $emergency->loadMissing('targets');
        $screens = $this->resolveScreens->handle($emergency->team, $emergency);

        if ($screens->isEmpty()) {
            throw ValidationException::withMessages([
                'screen_ids' => __('No screens match the selected locations.'),
            ]);
        }

        $startsAt = $emergency->starts_at;

        if ($startsAt !== null && $startsAt->isFuture()) {
            DB::transaction(function () use ($emergency, $user, $screens) {
                $emergency->forceFill([
                    'status' => EmergencyStatus::Scheduled,
                    'started_by' => $user !== null ? $user->id : $emergency->started_by,
                ])->save();

                $this->seedDeliveries($emergency, $screens);
                $this->audit->handle($emergency, EmergencyAuditAction::Scheduled, $user, null, [
                    'starts_at' => $emergency->starts_at?->toIso8601String(),
                    'screen_count' => $screens->count(),
                ]);
            });

            return $emergency->fresh(['deliveries', 'targets']) ?? $emergency;
        }

        return $this->activate($emergency, $user, $screens);
    }

    public function activateDue(Emergency $emergency): Emergency
    {
        $emergency->loadMissing('targets');
        $screens = $this->resolveScreens->handle($emergency->team, $emergency);

        return $this->activate($emergency, $emergency->starter, $screens);
    }

    /**
     * @param  Collection<int, Screen>  $screens
     */
    protected function activate(Emergency $emergency, ?User $user, Collection $screens): Emergency
    {
        DB::transaction(function () use ($emergency, $user, $screens) {
            $emergency->forceFill([
                'status' => EmergencyStatus::Active,
                'started_at' => now(),
                'started_by' => $user !== null ? $user->id : $emergency->started_by,
                'starts_at' => $emergency->starts_at ?? now(),
            ])->save();

            $this->seedDeliveries($emergency, $screens);
            $this->audit->handle($emergency, EmergencyAuditAction::Started, $user, null, [
                'severity' => $emergency->severity->value,
                'screen_count' => $screens->count(),
            ]);
        });

        $this->dispatchStartCommands($emergency->fresh(['deliveries.screen']) ?? $emergency);

        $emergency->loadMissing('team');
        $this->alerts->queue(
            $emergency->team,
            SignageAlert::EmergencyActivated,
            __('Emergency activated: :title', ['title' => $emergency->title]),
            $emergency->message ?: __('An emergency broadcast is running.'),
            ['emergency_id' => $emergency->id],
            'emergency_activated:'.$emergency->id,
            60,
        );

        app(RecordOrganizationAudit::class)->handle(
            $emergency->team,
            AuditAction::EmergencyActivated,
            $user,
            'emergency',
            $emergency->id,
            null,
            ['title' => $emergency->title, 'severity' => $emergency->severity->value],
        );

        app(DispatchPartnerWebhook::class)->handle(
            $emergency->team,
            WebhookEvent::EmergencyStarted,
            ['id' => $emergency->id, 'title' => $emergency->title, 'severity' => $emergency->severity->value],
        );

        return $emergency->fresh(['deliveries', 'targets']) ?? $emergency;
    }

    /**
     * @param  Collection<int, Screen>  $screens
     */
    protected function seedDeliveries(Emergency $emergency, Collection $screens): void
    {
        foreach ($screens as $screen) {
            $delivery = EmergencyDelivery::query()->firstOrNew([
                'emergency_id' => $emergency->id,
                'screen_id' => $screen->id,
            ]);

            $delivery->forceFill([
                'team_id' => $emergency->team_id,
                'status' => $screen->isPaired()
                    ? EmergencyDeliveryStatus::Pending
                    : EmergencyDeliveryStatus::Unreachable,
            ])->save();
        }
    }

    protected function dispatchStartCommands(Emergency $emergency): void
    {
        foreach ($emergency->deliveries as $delivery) {
            $screen = $delivery->screen;

            if (! $screen->isPaired()) {
                continue;
            }

            try {
                $command = $this->dispatchDeviceCommand->handle(
                    $screen,
                    DeviceCommandType::EmergencyStart,
                    [
                        'emergency_id' => $emergency->id,
                        'severity' => $emergency->severity->value,
                        'title' => $emergency->title,
                        'message' => $emergency->message ?? '',
                        'instructions' => $emergency->instructions ?? '',
                        'background' => $emergency->overlayBackground(),
                    ],
                    $emergency->starter,
                );

                $delivery->forceFill([
                    'device_command_id' => $command->id,
                    'status' => EmergencyDeliveryStatus::Sent,
                    'sent_at' => now(),
                ])->save();
            } catch (Throwable) {
                $delivery->forceFill([
                    'status' => EmergencyDeliveryStatus::Failed,
                ])->save();
            }
        }
    }
}
