<?php

namespace App\Widgets;

use App\Models\Team;
use DateTimeInterface;

readonly class WidgetContext
{
    public function __construct(
        public Team $team,
        public string $timezone,
        public DateTimeInterface $at,
    ) {}
}
