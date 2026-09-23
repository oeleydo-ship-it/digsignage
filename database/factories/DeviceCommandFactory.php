<?php

namespace Database\Factories;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Models\DeviceCommand;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceCommand>
 */
class DeviceCommandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'screen_id' => Screen::factory(),
            'command' => DeviceCommandType::Refresh,
            'payload' => [],
            'status' => DeviceCommandStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function typed(DeviceCommandType $command, array $payload = []): static
    {
        return $this->state(fn () => [
            'command' => $command,
            'payload' => $payload,
        ]);
    }
}
