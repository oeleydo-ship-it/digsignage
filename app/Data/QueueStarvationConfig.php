<?php

namespace App\Data;

readonly class QueueStarvationConfig
{
    public function __construct(
        public ?int $maxPriorityWaitSeconds,
        public ?int $promoteAfterSeconds,
    ) {
        //
    }

    /**
     * Service starvation JSON overrides team-wide defaults when present.
     *
     * @param  array<string, mixed>|null  $serviceStarvation
     * @param  array<string, mixed>  $teamSettings
     */
    public static function resolve(?array $serviceStarvation, array $teamSettings): self
    {
        $teamStarvation = $teamSettings['starvation'] ?? [];
        $fromTeam = is_array($teamStarvation) ? $teamStarvation : [];
        $fromService = is_array($serviceStarvation) ? $serviceStarvation : [];

        $source = self::hasValues($fromService) ? $fromService : $fromTeam;

        return self::fromArray($source);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            maxPriorityWaitSeconds: self::positiveInt($data['max_priority_wait_seconds'] ?? null),
            promoteAfterSeconds: self::positiveInt($data['promote_after_seconds'] ?? null),
        );
    }

    /**
     * @return array{max_priority_wait_seconds: int|null, promote_after_seconds: int|null}
     */
    public function toArray(): array
    {
        return [
            'max_priority_wait_seconds' => $this->maxPriorityWaitSeconds,
            'promote_after_seconds' => $this->promoteAfterSeconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function hasValues(array $data): bool
    {
        return array_key_exists('max_priority_wait_seconds', $data)
            || array_key_exists('promote_after_seconds', $data);
    }

    protected static function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
