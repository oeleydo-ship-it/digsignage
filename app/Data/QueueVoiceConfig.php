<?php

namespace App\Data;

final readonly class QueueVoiceConfig
{
    /** @var list<string> */
    public const LANGUAGES = ['en-US', 'ar-AE', 'fil-PH', 'hi-IN'];

    /**
     * @param  list<string>  $languages
     */
    public function __construct(
        public bool $enabled = false,
        public array $languages = ['en-US'],
        public ?string $voice = null,
        public float $speed = 1.0,
        public float $volume = 1.0,
        public int $repeatCount = 1,
        public bool $chime = true,
        public float $announcementDelaySeconds = 1.0,
    ) {}

    /** @param array<string, mixed>|null $settings */
    public static function resolve(?array $settings): self
    {
        $voice = is_array($settings['voice'] ?? null) ? $settings['voice'] : [];

        return self::fromArray($voice);
    }

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        $languages = array_values(array_intersect(
            self::LANGUAGES,
            is_array($attributes['languages'] ?? null) ? $attributes['languages'] : ['en-US'],
        ));

        return new self(
            enabled: (bool) ($attributes['enabled'] ?? false),
            languages: $languages !== [] ? $languages : ['en-US'],
            voice: filled($attributes['voice'] ?? null) ? trim((string) $attributes['voice']) : null,
            speed: max(0.5, min(2.0, (float) ($attributes['speed'] ?? 1.0))),
            volume: max(0.0, min(1.0, (float) ($attributes['volume'] ?? 1.0))),
            repeatCount: max(1, min(5, (int) ($attributes['repeat_count'] ?? 1))),
            chime: (bool) ($attributes['chime'] ?? true),
            announcementDelaySeconds: max(0.0, min(30.0, (float) ($attributes['announcement_delay_seconds'] ?? 1.0))),
        );
    }

    /** @return array{enabled: bool, languages: list<string>, voice: string|null, speed: float, volume: float, repeat_count: int, chime: bool, announcement_delay_seconds: float} */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'languages' => $this->languages,
            'voice' => $this->voice,
            'speed' => $this->speed,
            'volume' => $this->volume,
            'repeat_count' => $this->repeatCount,
            'chime' => $this->chime,
            'announcement_delay_seconds' => $this->announcementDelaySeconds,
        ];
    }
}
