<?php

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Laravel Horizon (queue monitoring)
|--------------------------------------------------------------------------
|
| Horizon requires the laravel/horizon Composer package, a Redis queue
| connection, and a Unix host (ext-pcntl / ext-posix). It is intentionally
| not installed in the local Windows development environment — run
| `composer require laravel/horizon && php artisan horizon:install` when
| deploying to production, then set QUEUE_CONNECTION=redis.
|
| The /horizon dashboard is restricted to platform administrators via
| App\Providers\HorizonServiceProvider.
|
*/

return [

    'name' => env('APP_NAME', 'DigSignage'),

    'prefix' => env('HORIZON_PREFIX', Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefixes' => [
        'snapshots' => 'snapshots:',
    ],

    'storage' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
    ],

    'waits' => [
        'redis:default' => 60,
        'redis:media' => 120,
        'redis:notifications' => 60,
        'redis:webhooks' => 60,
    ],

    'memory' => 64,

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\SomeQuietJob::class,
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'notifications', 'webhooks', 'analytics'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 10,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 60,
                'nice' => 0,
            ],
            'supervisor-media' => [
                'connection' => 'redis',
                'queue' => ['media'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'size',
                'minProcesses' => 1,
                'maxProcesses' => 4,
                'memory' => 512,
                'tries' => 3,
                // Video processing and thumbnail generation can take a while.
                'timeout' => 600,
                'nice' => 0,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default', 'media', 'notifications', 'webhooks', 'analytics'],
                'balance' => 'simple',
                'maxProcesses' => 3,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
    ],
];
