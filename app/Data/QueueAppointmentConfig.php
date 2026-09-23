<?php

namespace App\Data;

final readonly class QueueAppointmentConfig
{
    public function __construct(
        public ?int $priorityId = null,
        public int $checkInBeforeMinutes = 30,
        public int $checkInAfterMinutes = 15,
    ) {}

    /** @param array<string, mixed>|null $settings */
    public static function resolve(?array $settings): self
    {
        $appointments = is_array($settings['appointments'] ?? null) ? $settings['appointments'] : [];

        return new self(
            priorityId: filled($appointments['priority_id'] ?? null) ? (int) $appointments['priority_id'] : null,
            checkInBeforeMinutes: max(0, min(1440, (int) ($appointments['check_in_before_minutes'] ?? 30))),
            checkInAfterMinutes: max(0, min(1440, (int) ($appointments['check_in_after_minutes'] ?? 15))),
        );
    }

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self(
            priorityId: filled($attributes['priority_id'] ?? null) ? (int) $attributes['priority_id'] : null,
            checkInBeforeMinutes: (int) ($attributes['check_in_before_minutes'] ?? 30),
            checkInAfterMinutes: (int) ($attributes['check_in_after_minutes'] ?? 15),
        );
    }

    /** @return array{priority_id: int|null, check_in_before_minutes: int, check_in_after_minutes: int} */
    public function toArray(): array
    {
        return [
            'priority_id' => $this->priorityId,
            'check_in_before_minutes' => $this->checkInBeforeMinutes,
            'check_in_after_minutes' => $this->checkInAfterMinutes,
        ];
    }
}
