<?php

namespace App\Data;

final readonly class QueueNotificationMessage
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $destination,
        public string $subject,
        public string $body,
        public array $data = [],
        public ?int $teamId = null,
    ) {}
}
