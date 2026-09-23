<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Signage\SaveMonitoringSettings;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Support\ScreenHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    /**
     * Screen health dashboard for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Screen::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        $thresholds = ScreenHealth::thresholds($team);

        $screens = Screen::query()
            ->forTeam($team)
            ->with(['currentChannel:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Screen $screen) => ScreenHealth::snapshot($screen, $thresholds));

        $counts = [
            'healthy' => $screens->where('status', 'online')->count(),
            'warning' => $screens->where('status', 'warning')->count(),
            'offline' => $screens->where('status', 'offline')->count(),
            'disabled' => $screens->where('status', 'disabled')->count(),
        ];

        return Inertia::render('signage/monitoring/index', [
            'screens' => $screens->values(),
            'counts' => $counts,
            'thresholds' => [
                'healthy_seconds' => $thresholds->healthySeconds,
                'warning_seconds' => $thresholds->warningSeconds,
            ],
            'canUpdateThresholds' => $request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam),
        ]);
    }

    /**
     * Save team-specific healthy / warning / offline windows.
     */
    public function update(Request $request, SaveMonitoringSettings $saveSettings): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam), 403);

        $validated = $request->validate([
            'healthy_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'warning_seconds' => ['required', 'integer', 'min:60', 'max:172800', 'gt:healthy_seconds'],
        ]);

        $saveSettings->handle(
            $team,
            $validated['healthy_seconds'],
            $validated['warning_seconds'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Monitoring thresholds saved.')]);

        return back();
    }
}
