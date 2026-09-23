<?php

return [
    'driver' => env('BILLING_DRIVER', 'fake'),

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    'currency' => env('BILLING_CURRENCY', 'usd'),

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
    ],

    'coupons' => [
        'SAVE20' => [
            'percent_off' => 20,
            'stripe_promotion_code' => env('STRIPE_COUPON_SAVE20'),
        ],
        'LAUNCH50' => [
            'percent_off' => 50,
            'stripe_promotion_code' => env('STRIPE_COUPON_LAUNCH50'),
        ],
    ],

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'screens' => 10,
            'storage_gb' => 25,
            'users' => 5,
            'bandwidth_gb' => 50,
            'advanced' => false,
            'features' => [
                'queue_management' => false,
            ],
            'price_cents' => 2900,
            'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
        ],
        'business' => [
            'name' => 'Business',
            'screens' => 50,
            'storage_gb' => 100,
            'users' => 20,
            'bandwidth_gb' => 500,
            'advanced' => true,
            'features' => [
                'queue_management' => true,
            ],
            'price_cents' => 9900,
            'stripe_price_id' => env('STRIPE_PRICE_BUSINESS'),
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'screens' => null,
            'storage_gb' => null,
            'users' => null,
            'bandwidth_gb' => null,
            'advanced' => true,
            'features' => [
                'queue_management' => true,
            ],
            'price_cents' => null,
            'stripe_price_id' => env('STRIPE_PRICE_ENTERPRISE'),
        ],
    ],
];
