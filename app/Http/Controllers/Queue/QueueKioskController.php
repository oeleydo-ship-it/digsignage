<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\DeleteQueueKiosk;
use App\Actions\Queue\SaveQueueKiosk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueKioskRequest;
use App\Models\Location;
use App\Models\QueueKiosk;
use App\Models\QueueSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QueueKioskController extends Controller
{
    /**
     * Display kiosks for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QueueKiosk::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        QueueSetting::resolveForTeam($team);

        $kiosks = QueueKiosk::query()
            ->forTeam($team)
            ->with('location:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (QueueKiosk $kiosk) => $this->kioskPayload($kiosk));

        return Inertia::render('queue/kiosks', [
            'kiosks' => $kiosks,
            'locations' => Location::query()
                ->forTeam($team)
                ->orderBy('path')
                ->orderBy('name')
                ->get(['id', 'name', 'depth']),
            'permissions' => $request->user()->toQueuePermissions($team),
        ]);
    }

    /**
     * Store a newly created kiosk.
     */
    public function store(SaveQueueKioskRequest $request, SaveQueueKiosk $saveQueueKiosk): RedirectResponse
    {
        Gate::authorize('create', QueueKiosk::class);

        $saveQueueKiosk->handle($request->user()->currentTeam, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk created.')]);

        return back(fallback: route('queue.kiosks', $request->user()->currentTeam));
    }

    /**
     * Update the specified kiosk.
     */
    public function update(
        SaveQueueKioskRequest $request,
        string $current_team,
        QueueKiosk $queueKiosk,
        SaveQueueKiosk $saveQueueKiosk,
    ): RedirectResponse {
        Gate::authorize('update', $queueKiosk);
        abort_unless($queueKiosk->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveQueueKiosk->handle($request->user()->currentTeam, $request->validated(), $queueKiosk);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk updated.')]);

        return back(fallback: route('queue.kiosks', $request->user()->currentTeam));
    }

    /**
     * Remove the specified kiosk.
     */
    public function destroy(
        Request $request,
        string $current_team,
        QueueKiosk $queueKiosk,
        DeleteQueueKiosk $deleteQueueKiosk,
    ): RedirectResponse {
        Gate::authorize('delete', $queueKiosk);
        abort_unless($queueKiosk->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteQueueKiosk->handle($queueKiosk);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk deleted.')]);

        return back(fallback: route('queue.kiosks', $request->user()->currentTeam));
    }

    /**
     * @return array<string, mixed>
     */
    protected function kioskPayload(QueueKiosk $kiosk): array
    {
        $branding = $kiosk->resolvedBranding()->toArray();

        return [
            'id' => $kiosk->id,
            'location_id' => $kiosk->location_id,
            'location_name' => $kiosk->location?->name,
            'name' => $kiosk->name,
            'branding' => $branding,
            'printer_enabled' => $kiosk->printer_enabled,
            'is_active' => $kiosk->is_active,
            'has_pin' => filled($kiosk->getAttribute('pin')),
            'serve_url' => $kiosk->serveUrl(),
        ];
    }
}
