<?php

namespace App\Data;

final readonly class QueueAlertConfig
{
    public function __construct(
        public bool $enabled,
        public int $averageWaitMinutes,
        public int $waitingCustomers,
        public int $customerWaitMinutes,
        public bool $noCounterAvailable,
        public bool $capacityReached,
        public bool $counterOffline,
        public int $cooldownMinutes,
    ) {
        //
    }

    /** @param array<string, mixed> $settings */
    public static function resolve(array $settings): self
    {
        $alerts = $settings['alerts'] ?? [];

        return self::fromArray(is_array($alerts) ? $alerts : []);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            averageWaitMinutes: self::boundedInt($data['average_wait_minutes'] ?? 20, 20, 1, 1440),
            waitingCustomers: self::boundedInt($data['waiting_customers'] ?? 30, 30, 1, 100000),
            customerWaitMinutes: self::boundedInt($data['customer_wait_minutes'] ?? 45, 45, 1, 10080),
            noCounterAvailable: (bool) ($data['no_counter_available'] ?? true),
            capacityReached: (bool) ($data['capacity_reached'] ?? true),
            counterOffline: (bool) ($data['counter_offline'] ?? true),
            cooldownMinutes: self::boundedInt($data['cooldown_minutes'] ?? 30, 30, 1, 1440),
        );
    }

    /** @return array<string, bool|int> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'average_wait_minutes' => $this->averageWaitMinutes,
            'waiting_customers' => $this->waitingCustomers,
            'customer_wait_minutes' => $this->customerWaitMinutes,
            'no_counter_available' => $this->noCounterAvailable,
            'capacity_reached' => $this->capacityReached,
            'counter_offline' => $this->counterOffline,
            'cooldown_minutes' => $this->cooldownMinutes,
        ];
    }

    protected static function boundedInt(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return min($max, max($min, (int) $value));
    }
}
