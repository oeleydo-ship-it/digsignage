<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\QueryAnalytics;
use App\Models\Screen;
use App\Support\AnalyticsFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnalyticsController extends Controller
{
    public function index(Request $request, QueryAnalytics $query): JsonResponse
    {
        Gate::authorize('viewAny', Screen::class);

        return response()->json([
            'data' => $query->handle(AnalyticsFilters::fromRequest($request, $this->team($request))),
        ]);
    }
}
