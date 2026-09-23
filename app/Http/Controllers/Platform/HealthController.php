<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/health/index', [
            'health' => [
                'app' => config('app.name'),
                'env' => config('app.env'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'database' => config('database.default'),
                'pending_jobs' => (int) DB::table('jobs')->count(),
                'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            ],
        ]);
    }
}
