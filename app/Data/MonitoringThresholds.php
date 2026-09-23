<?php

namespace App\Data;

readonly class MonitoringThresholds
{
    public function __construct(
        public int $healthySeconds,
        public int $warningSeconds,
    ) {}
}
