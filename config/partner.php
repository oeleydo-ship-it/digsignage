<?php

return [
    'rate_per_minute' => (int) env('PARTNER_API_RATE_PER_MINUTE', 120),
    'token_prefix' => 'dsg_',
    'token_bytes' => 48,
    'webhook_timeout' => 10,
];
