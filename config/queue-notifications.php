<?php

return [
    'providers' => [
        'sms' => env('QUEUE_SMS_WEBHOOK_URL'),
        'whatsapp' => env('QUEUE_WHATSAPP_WEBHOOK_URL'),
        'push' => env('QUEUE_PUSH_WEBHOOK_URL'),
    ],
];
