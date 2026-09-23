<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Signage\SyncScreenGroupScreens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signage\SaveScreenGroupRequest;
use App\Http\Requests\Signage\SyncScreenGroupScreensRequest;
use App\Models\Screen;
use App\Models\ScreenGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScreenGroupController extends Controller
{
    /**
     * Display a listing of screen groups.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ScreenGroup::class);

        $team = $request->user()->currentTeam;

        $groups = ScreenGroup::query()
            ->forTeam($team)
            ->with(['screens:id,name,status'])
            ->withCount('screens')
            ->orderBy('name')
            ->get()
            ->map(fn (ScreenGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'screens_count' => $group->screens_count,
                'screen_ids' => $group->screens->pluck('id')->all(),
                'screens' => $group->screens->map(fn (Screen $screen) => [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'status' => $screen->status->value,
                ]),
            ]);

        return Inertia::render('signage/groups/index', [
            'groups' => $groups,
            'screens' => Screen::query()->forTeam($team)->orderBy('name')->get(['id', 'name', 'status']),
            'permissions' => $request->user()->toSignagePermissions($team),
        ]);
    }

    /**
     * Store a newly created group.
     */
    public function store(SaveScreenGroupRequest $request): RedirectResponse
    {
        Gate::authorize('create', ScreenGroup::class);

        $request->user()->currentTeam->screenGroups()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group created.')]);

        return back();
    }

    /**
     * Update the specified group.
     */
    public function update(SaveScreenGroupRequest $request, string $current_team, ScreenGroup $screenGroup): RedirectResponse
    {
        Gate::authorize('update', $screenGroup);
        abort_unless($screenGroup->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $screenGroup->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group updated.')]);

        return back();
    }

    /**
     * Remove the specified group.
     */
    public function destroy(Request $request, string $current_team, ScreenGroup $screenGroup): RedirectResponse
    {
        Gate::authorize('delete', $screenGroup);
        abort_unless($screenGroup->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $screenGroup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group deleted.')]);

        return back();
    }

    /**
     * Sync screens assigned to the group.
     */
    public function syncScreens(
        SyncScreenGroupScreensRequest $request,
        string $current_team,
        ScreenGroup $screenGroup,
        SyncScreenGroupScreens $syncScreenGroupScreens,
    ): RedirectResponse {
        Gate::authorize('update', $screenGroup);
        abort_unless($screenGroup->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $syncScreenGroupScreens->handle(
            $request->user()->currentTeam,
            $screenGroup,
            $request->validated('screen_ids'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group screens updated.')]);

        return back();
    }
}
