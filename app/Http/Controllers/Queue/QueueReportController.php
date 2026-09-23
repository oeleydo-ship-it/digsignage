<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\ExportQueueAnalytics;
use App\Actions\Queue\QueryQueueAnalytics;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\User;
use App\Support\QueueAnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueReportController extends Controller
{
    public function index(Request $request, QueryQueueAnalytics $query): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ViewQueueReports), 403);
        $filters = QueueAnalyticsFilters::fromRequest($request, $team);

        return Inertia::render('queue/reports', [
            'filters' => $filters->filters,
            'report' => $query->handle($filters),
            'options' => [
                'locations' => Location::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Location $location) => ['value' => (string) $location->id, 'label' => $location->name]),
                'services' => QueueService::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (QueueService $service) => ['value' => (string) $service->id, 'label' => $service->name]),
                'counters' => QueueCounter::query()->forTeam($team)->orderBy('name')->get(['id', 'name', 'code'])
                    ->map(fn (QueueCounter $counter) => ['value' => (string) $counter->id, 'label' => $counter->name.' · '.$counter->code]),
                'employees' => $team->members()->orderBy('name')->get(['users.id', 'users.name'])
                    ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name]),
            ],
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    public function export(Request $request, ExportQueueAnalytics $export): StreamedResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::ViewQueueReports), 403);

        return $export->handle(QueueAnalyticsFilters::fromRequest($request, $team));
    }
}
