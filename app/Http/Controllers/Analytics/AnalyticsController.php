<?php

namespace App\Http\Controllers\Analytics;

use App\Actions\Analytics\QueryAnalytics;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Screen;
use App\Support\AnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Screen, content, and operational analytics for the current team.
     */
    public function index(Request $request, QueryAnalytics $query): Response
    {
        Gate::authorize('viewAny', Screen::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $filters = AnalyticsFilters::fromRequest($request, $team);

        return Inertia::render('analytics/index', [
            'filters' => $filters->filters,
            'report' => $query->handle($filters),
            'options' => [
                'screens' => Screen::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Screen $screen) => ['value' => (string) $screen->id, 'label' => $screen->name]),
                'locations' => Location::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Location $location) => ['value' => (string) $location->id, 'label' => $location->name]),
            ],
        ]);
    }
}
