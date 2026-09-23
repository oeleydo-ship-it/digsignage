<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProofOfPlay\QueryProofOfPlay;
use App\Models\PlayerPlaybackEvent;
use App\Support\ProofOfPlayFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProofOfPlayController extends Controller
{
    public function index(Request $request, QueryProofOfPlay $query): JsonResponse
    {
        Gate::authorize('viewAny', PlayerPlaybackEvent::class);

        $report = $query->handle(
            ProofOfPlayFilters::fromRequest($request, $this->team($request)),
            $this->perPage($request),
        );

        $events = $report['events'];

        return response()->json([
            'data' => [
                'totals' => $report['totals'],
                'grouped' => $report['grouped'],
                'events' => array_values($events->items()),
            ],
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
