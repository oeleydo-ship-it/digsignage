<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Playlist\SavePlaylist;
use App\Http\Requests\Playlist\SavePlaylistRequest;
use App\Models\Playlist;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PlaylistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Playlist::class);

        $playlists = Playlist::query()
            ->forTeam($this->team($request))
            ->withCount('items')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Playlist $playlist) => PartnerApi::playlist($playlist));

        return PartnerApi::paginated($playlists);
    }

    public function store(SavePlaylistRequest $request, SavePlaylist $save): JsonResponse
    {
        Gate::authorize('create', Playlist::class);

        $playlist = $save->handle($request->user(), $this->team($request), $request->validated());

        return PartnerApi::item(PartnerApi::playlist($playlist), 201);
    }

    public function show(Request $request, int $playlist): JsonResponse
    {
        $model = $this->findForTeam($request, Playlist::class, $playlist);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::playlist($model));
    }

    public function update(SavePlaylistRequest $request, SavePlaylist $save, int $playlist): JsonResponse
    {
        $model = $this->findForTeam($request, Playlist::class, $playlist);
        Gate::authorize('update', $model);

        $model = $save->handle($request->user(), $this->team($request), $request->validated(), $model);

        return PartnerApi::item(PartnerApi::playlist($model));
    }

    public function destroy(Request $request, int $playlist): Response
    {
        $model = $this->findForTeam($request, Playlist::class, $playlist);
        Gate::authorize('delete', $model);
        $model->delete();

        return response()->noContent();
    }
}
