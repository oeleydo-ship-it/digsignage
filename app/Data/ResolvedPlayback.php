<?php

namespace App\Data;

readonly class ResolvedPlayback
{
    public function __construct(
        public string $source,
        public ?int $scheduleId,
        public ?int $channelId,
        public ?int $playlistId,
        public ?string $scheduleName,
        public string $timezone,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'schedule_id' => $this->scheduleId,
            'channel_id' => $this->channelId,
            'playlist_id' => $this->playlistId,
            'schedule_name' => $this->scheduleName,
            'timezone' => $this->timezone,
        ];
    }
}
