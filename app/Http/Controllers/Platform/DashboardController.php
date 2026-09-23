<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\PlatformMetrics;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): Response
    {
        return Inertia::render('platform/dashboard', [
            'metrics' => $metrics->dashboard(),
        ]);
    }
}
