<?php

namespace App\Http\Controllers\Playlist;

use App\Actions\Playlist\DuplicatePlaylist;
use App\Actions\Playlist\RestorePlaylistRevision;
use App\Actions\Playlist\SavePlaylist;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Enums\PlaylistTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Playlist\SavePlaylistRequest;
use App\Models\Design;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistRevision;
use App\Support\ContentApprovalPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    /**
     * Display the playlist library.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Playlist::class);

        $team = $request->user()->currentTeam;
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $playlists = Playlist::query()
            ->forTeam($team)
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Playlist $playlist) => $this->listPayload($playlist));

        return Inertia::render('playlists/index', [
            'playlists' => $playlists,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => collect(PlaylistStatus::cases())->map(fn (PlaylistStatus $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'permissions' => $request->user()->toPlaylistPermissions($team),
        ]);
    }

    /**
     * Store a new playlist and open the editor.
     */
    public function store(SavePlaylistRequest $request, SavePlaylist $savePlaylist): RedirectResponse
    {
        Gate::authorize('create', Playlist::class);

        $playlist = $savePlaylist->handle(
            $request->user(),
            $request->user()->currentTeam,
            [
                ...$request->validated(),
                'items' => $request->validated('items') ?? [],
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist created.')]);

        return redirect()->route('playlists.edit', [$request->user()->currentTeam, $playlist]);
    }

    /**
     * Preview the playlist sequence.
     */
    public function show(Request $request, string $current_team, Playlist $playlist): Response
    {
        Gate::authorize('view', $playlist);
        $this->assertAccessible($request, $current_team, $playlist);

        $playlist->load(['items.media', 'items.template']);
        $slug = $request->user()->currentTeam->slug;

        return Inertia::render('playlists/preview', [
            'playlist' => [
                ...$this->listPayload($playlist),
                'items' => $playlist->items->map(fn (PlaylistItem $item) => $item->toEditorArray($slug))->values()->all(),
            ],
        ]);
    }

    /**
     * Edit the playlist sequence.
     */
    public function edit(Request $request, string $current_team, Playlist $playlist): Response
    {
        Gate::authorize('view', $playlist);
        $this->assertAccessible($request, $current_team, $playlist);

        $team = $request->user()->currentTeam;
        $playlist->load(['items.media', 'items.template']);

        return Inertia::render('playlists/edit', [
            'playlist' => [
                ...$this->listPayload($playlist),
                'loop' => $playlist->loop,
                'description' => $playlist->description,
                'items' => $playlist->items->map(fn (PlaylistItem $item) => $item->toEditorArray($team->slug))->values()->all(),
            ],
            'revisions' => $playlist->revisions()
                ->limit(20)
                ->get(['id', 'version', 'created_at'])
                ->map(fn (PlaylistRevision $revision) => [
                    'id' => $revision->id,
                    'version' => $revision->version,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ]),
            'itemTypes' => collect(PlaylistItemType::cases())
                ->reject(fn (PlaylistItemType $type) => $type === PlaylistItemType::Template)
                ->map(fn (PlaylistItemType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ])
                ->values(),
            'transitions' => collect(PlaylistTransition::cases())->map(fn (PlaylistTransition $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'catalog' => [
                'media' => Media::query()->forTeam($team)->notArchived()->latest()->limit(50)
                    ->get(['id', 'name', 'type', 'duration', 'thumbnail_path', 'storage_path'])
                    ->map(fn (Media $media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'type' => $media->type->value,
                        'duration_seconds' => $media->duration ?: 15,
                        'preview_url' => $media->previewUrl($team->slug),
                    ]),
                'designs' => Design::query()->forTeam($team)->published()->latest()->limit(50)->get(['id', 'name'])
                    ->map(fn (Design $design) => [
                        'id' => $design->id,
                        'name' => $design->name,
                    ]),
            ],
            'permissions' => $request->user()->toPlaylistPermissions($team),
            'approval' => ContentApprovalPresenter::for($request->user(), $playlist),
        ]);
    }

    /**
     * Update playlist metadata and ordered items.
     */
    public function update(
        SavePlaylistRequest $request,
        string $current_team,
        Playlist $playlist,
        SavePlaylist $savePlaylist,
    ): RedirectResponse {
        Gate::authorize('update', $playlist);
        $this->assertAccessible($request, $current_team, $playlist);

        $savePlaylist->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $playlist,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist saved.')]);

        return redirect()->route('playlists.edit', [$request->user()->currentTeam, $playlist]);
    }

    /**
     * Duplicate a playlist.
     */
    public function duplicate(
        Request $request,
        string $current_team,
        Playlist $playlist,
        DuplicatePlaylist $duplicatePlaylist,
    ): RedirectResponse {
        Gate::authorize('create', Playlist::class);
        $this->assertAccessible($request, $current_team, $playlist);

        $copy = $duplicatePlaylist->handle($request->user(), $playlist);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist duplicated.')]);

        return redirect()->route('playlists.edit', [$request->user()->currentTeam, $copy]);
    }

    /**
     * Restore a previous playlist revision.
     */
    public function restore(
        Request $request,
        string $current_team,
        Playlist $playlist,
        PlaylistRevision $revision,
        RestorePlaylistRevision $restoreRevision,
    ): RedirectResponse {
        Gate::authorize('update', $playlist);
        $this->assertAccessible($request, $current_team, $playlist);

        $restoreRevision->handle($request->user(), $playlist, $revision);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revision restored.')]);

        return back();
    }

    /**
     * Soft-delete a playlist.
     */
    public function destroy(Request $request, string $current_team, Playlist $playlist): RedirectResponse
    {
        Gate::authorize('delete', $playlist);
        $this->assertAccessible($request, $current_team, $playlist);

        $playlist->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playlist deleted.')]);

        return redirect()->route('playlists.index', $request->user()->currentTeam);
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Playlist $playlist): array
    {
        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'status' => $playlist->status->value,
            'status_label' => $playlist->status->label(),
            'loop' => $playlist->loop,
            'version' => $playlist->version,
            'duration_seconds' => $playlist->duration_seconds,
            'duration_label' => $this->formatDuration($playlist->duration_seconds),
            'items_count' => $playlist->items_count ?? $playlist->items()->count(),
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remain = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$remain}s";
        }

        if ($minutes > 0) {
            return "{$minutes}m {$remain}s";
        }

        return "{$remain}s";
    }

    protected function assertAccessible(Request $request, string $currentTeam, Playlist $playlist): void
    {
        abort_unless($playlist->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
    }
}
