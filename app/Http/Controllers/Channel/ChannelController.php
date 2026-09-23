<?php

namespace App\Http\Controllers\Channel;

use App\Actions\Channel\DuplicateChannel;
use App\Actions\Channel\SaveChannel;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\LiveStreamProtocol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\SaveChannelRequest;
use App\Models\Channel;
use App\Models\ChannelZone;
use App\Models\Playlist;
use App\Models\Screen;
use App\Support\ContentApprovalPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    /**
     * Display the channel library.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Channel::class);

        $team = $request->user()->currentTeam;
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        $channels = Channel::query()
            ->forTeam($team)
            ->with(['playlist:id,name'])
            ->withCount(['zones', 'screens'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Channel $channel) => $this->listPayload($channel));

        return Inertia::render('channels/index', [
            'channels' => $channels,
            'filters' => ['search' => $search, 'status' => $status, 'type' => $type],
            'statuses' => $this->statusOptions(),
            'types' => $this->typeOptions(),
            'permissions' => $request->user()->toChannelPermissions($team),
        ]);
    }

    /**
     * Store a new channel and open the editor.
     */
    public function store(SaveChannelRequest $request, SaveChannel $saveChannel): RedirectResponse
    {
        Gate::authorize('create', Channel::class);

        $channel = $saveChannel->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel created.')]);

        return redirect()->route('channels.edit', [$request->user()->currentTeam, $channel]);
    }

    /**
     * Preview the channel layout or stream.
     */
    public function show(Request $request, string $current_team, Channel $channel): Response
    {
        Gate::authorize('view', $channel);
        $this->assertAccessible($request, $current_team, $channel);

        $channel->load(['zones.playlist', 'playlist', 'screens']);

        return Inertia::render('channels/preview', [
            'channel' => [
                ...$this->listPayload($channel),
                'live_protocol' => $channel->live_protocol?->value,
                'live_protocol_label' => $channel->live_protocol?->label(),
                'live_url' => $channel->live_url,
                'width' => $channel->width,
                'height' => $channel->height,
                'zones' => $channel->zones->map(fn (ChannelZone $zone) => $zone->toEditorArray())->values()->all(),
            ],
        ]);
    }

    /**
     * Edit a channel.
     */
    public function edit(Request $request, string $current_team, Channel $channel): Response
    {
        Gate::authorize('view', $channel);
        $this->assertAccessible($request, $current_team, $channel);

        $team = $request->user()->currentTeam;
        $channel->load(['zones.playlist', 'playlist', 'screens']);

        return Inertia::render('channels/edit', [
            'channel' => [
                ...$this->listPayload($channel),
                'description' => $channel->description,
                'playlist_id' => $channel->playlist_id,
                'live_protocol' => $channel->live_protocol?->value,
                'live_url' => $channel->live_url,
                'width' => $channel->width,
                'height' => $channel->height,
                'scheduled_at' => $channel->scheduled_at?->toIso8601String(),
                'zones' => $channel->zones->map(fn (ChannelZone $zone) => $zone->toEditorArray())->values()->all(),
                'screen_ids' => $channel->screens->pluck('id')->values()->all(),
            ],
            'playlists' => Playlist::query()->forTeam($team)->latest()->limit(100)->get(['id', 'name', 'duration_seconds'])
                ->map(fn (Playlist $playlist) => [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'duration_seconds' => $playlist->duration_seconds,
                ]),
            'screens' => Screen::query()->forTeam($team)->orderBy('name')->get(['id', 'name', 'current_channel_id'])
                ->map(fn (Screen $screen) => [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'current_channel_id' => $screen->current_channel_id,
                ]),
            'types' => $this->typeOptions(),
            'protocols' => collect(LiveStreamProtocol::cases())->map(fn (LiveStreamProtocol $item) => [
                'value' => $item->value,
                'label' => $item->label(),
            ]),
            'permissions' => $request->user()->toChannelPermissions($team),
            'approval' => ContentApprovalPresenter::for($request->user(), $channel),
        ]);
    }

    /**
     * Update a channel.
     */
    public function update(
        SaveChannelRequest $request,
        string $current_team,
        Channel $channel,
        SaveChannel $saveChannel,
    ): RedirectResponse {
        Gate::authorize('update', $channel);
        $this->assertAccessible($request, $current_team, $channel);

        $saveChannel->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $channel,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel saved.')]);

        return redirect()->route('channels.edit', [$request->user()->currentTeam, $channel]);
    }

    /**
     * Duplicate a channel.
     */
    public function duplicate(
        Request $request,
        string $current_team,
        Channel $channel,
        DuplicateChannel $duplicateChannel,
    ): RedirectResponse {
        Gate::authorize('create', Channel::class);
        $this->assertAccessible($request, $current_team, $channel);

        $copy = $duplicateChannel->handle($request->user(), $channel);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel duplicated.')]);

        return redirect()->route('channels.edit', [$request->user()->currentTeam, $copy]);
    }

    /**
     * Soft-delete a channel.
     */
    public function destroy(Request $request, string $current_team, Channel $channel): RedirectResponse
    {
        Gate::authorize('delete', $channel);
        $this->assertAccessible($request, $current_team, $channel);

        $channel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Channel deleted.')]);

        return redirect()->route('channels.index', $request->user()->currentTeam);
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Channel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'type' => $channel->type->value,
            'type_label' => $channel->type->label(),
            'status' => $channel->status->value,
            'status_label' => $channel->status->label(),
            'playlist_name' => $channel->playlist?->name,
            'version' => $channel->version,
            'zones_count' => $channel->zones_count ?? $channel->zones()->count(),
            'screens_count' => $channel->screens_count ?? $channel->screens()->count(),
            'updated_at' => $channel->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function typeOptions(): array
    {
        $options = [];

        foreach (ChannelType::cases() as $item) {
            $options[] = [
                'value' => $item->value,
                'label' => $item->label(),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        $options = [];

        foreach (ChannelStatus::cases() as $item) {
            if (! in_array($item, [ChannelStatus::Draft, ChannelStatus::Archived, ChannelStatus::Scheduled], true)) {
                continue;
            }

            $options[] = [
                'value' => $item->value,
                'label' => $item->label(),
            ];
        }

        return $options;
    }

    protected function assertAccessible(Request $request, string $currentTeam, Channel $channel): void
    {
        abort_unless($channel->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
    }
}
