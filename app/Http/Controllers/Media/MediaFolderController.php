<?php

namespace App\Http\Controllers\Media;

use App\Actions\Media\DeleteMediaFolder;
use App\Actions\Media\SaveMediaFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\SaveMediaFolderRequest;
use App\Models\MediaFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MediaFolderController extends Controller
{
    /**
     * Store a newly created folder.
     */
    public function store(SaveMediaFolderRequest $request, SaveMediaFolder $saveMediaFolder): RedirectResponse
    {
        Gate::authorize('create', MediaFolder::class);

        $saveMediaFolder->handle($request->user()->currentTeam, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return back();
    }

    /**
     * Update the specified folder.
     */
    public function update(
        SaveMediaFolderRequest $request,
        string $current_team,
        MediaFolder $mediaFolder,
        SaveMediaFolder $saveMediaFolder,
    ): RedirectResponse {
        Gate::authorize('update', $mediaFolder);
        abort_unless($mediaFolder->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveMediaFolder->handle($request->user()->currentTeam, $request->validated(), $mediaFolder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder updated.')]);

        return back();
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(
        Request $request,
        string $current_team,
        MediaFolder $mediaFolder,
        DeleteMediaFolder $deleteMediaFolder,
    ): RedirectResponse {
        Gate::authorize('delete', $mediaFolder);
        abort_unless($mediaFolder->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteMediaFolder->handle($mediaFolder);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder deleted.')]);

        return back();
    }
}
