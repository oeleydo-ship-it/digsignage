<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Signage\UpdateScreenFallbackImage;
use App\Actions\Signage\UpdateTeamFallbackImage;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signage\UpdateFallbackImageRequest;
use App\Models\Screen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FallbackImageController extends Controller
{
    /**
     * Show the team-level player fallback screen settings.
     */
    public function edit(Request $request): Response
    {
        Gate::authorize('viewAny', Screen::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $settings = is_array($team->settings) ? $team->settings : [];
        $image = $settings['fallback_image'] ?? null;

        return Inertia::render('settings/player-fallback', [
            'fallbackImage' => is_string($image) && $image !== '' ? $image : null,
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam),
        ]);
    }

    /**
     * Upload or replace the team-wide fallback image.
     */
    public function update(UpdateFallbackImageRequest $request, UpdateTeamFallbackImage $updateFallbackImage): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam), 403);

        $image = $request->file('image');

        $updateFallbackImage->handle($team, $image instanceof UploadedFile ? $image : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fallback image updated.')]);

        return back();
    }

    /**
     * Remove the team-wide fallback image, reverting to the branded frame.
     */
    public function destroy(Request $request, UpdateTeamFallbackImage $updateFallbackImage): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        abort_unless($request->user()->hasTeamPermission($team, TeamPermission::UpdateTeam), 403);

        $updateFallbackImage->handle($team, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fallback image removed.')]);

        return back();
    }

    /**
     * Upload or replace a screen-level fallback image override.
     */
    public function updateScreen(UpdateFallbackImageRequest $request, string $current_team, Screen $screen, UpdateScreenFallbackImage $updateFallbackImage): RedirectResponse
    {
        Gate::authorize('update', $screen);
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $image = $request->file('image');

        $updateFallbackImage->handle($screen, $image instanceof UploadedFile ? $image : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen fallback image updated.')]);

        return back();
    }

    /**
     * Remove a screen-level fallback image override.
     */
    public function destroyScreen(Request $request, string $current_team, Screen $screen, UpdateScreenFallbackImage $updateFallbackImage): RedirectResponse
    {
        Gate::authorize('update', $screen);
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $updateFallbackImage->handle($screen, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen fallback image removed.')]);

        return back();
    }
}
