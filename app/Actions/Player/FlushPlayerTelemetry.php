<?php

namespace App\Actions\Player;

use App\Models\Screen;
use App\Support\PlayerTelemetry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FlushPlayerTelemetry
{
    public function __construct(
        protected RecordPlayerHeartbeat $heartbeat,
        protected RecordPlayerPlayback $playback,
    ) {}

    /**
     * Apply queued heartbeats and playback events in order after reconnect.
     *
     * @param  array<int, mixed>  $items
     * @return array{heartbeats: int, playback: int}
     */
    public function handle(Screen $screen, array $items, ?string $ip = null): array
    {
        $heartbeats = 0;
        $playback = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'items' => __('Invalid telemetry item.'),
                ]);
            }

            $type = $item['type'] ?? null;
            $payload = $item['payload'] ?? null;

            if (! is_string($type) || ! is_array($payload)) {
                throw ValidationException::withMessages([
                    'items' => __('Invalid telemetry item.'),
                ]);
            }

            if ($type === 'heartbeat') {
                $this->heartbeat->handle(
                    $screen,
                    $this->validated($payload, PlayerTelemetry::heartbeatRules()),
                    $ip,
                );
                $heartbeats++;

                continue;
            }

            if ($type === 'playback') {
                $this->playback->handle(
                    $screen,
                    $this->validated($payload, PlayerTelemetry::playbackRules()),
                );
                $playback++;
            }
        }

        return [
            'heartbeats' => $heartbeats,
            'playback' => $playback,
        ];
    }

    /**
     * @param  array<mixed>  $payload
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validated(array $payload, array $rules): array
    {
        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
