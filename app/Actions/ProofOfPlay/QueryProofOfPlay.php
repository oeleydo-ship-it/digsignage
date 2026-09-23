<?php

namespace App\Actions\ProofOfPlay;

use App\Enums\PlaybackStatus;
use App\Models\PlayerPlaybackEvent;
use App\Support\ProofOfPlayFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueryProofOfPlay
{
    /**
     * @return array{
     *     events: LengthAwarePaginator<int, array{
     *         id: int,
     *         screen: string,
     *         location: string|null,
     *         channel: string|null,
     *         playlist: string|null,
     *         content_id: string|null,
     *         title: string|null,
     *         started_at: string|null,
     *         ended_at: string|null,
     *         duration_ms: int|null,
     *         status: string,
     *         status_label: string
     *     }>,
     *     totals: array{plays: int, duration_ms: int, screens: int, content: int},
     *     grouped: list<array{key: string, label: string, plays: int, duration_ms: int, screens: int}>
     * }
     */
    public function handle(ProofOfPlayFilters $filters, int $perPage = 25): array
    {
        $base = $this->filtered($filters);

        $totalsRow = (clone $base)
            ->toBase()
            ->selectRaw('count(*) as plays')
            ->selectRaw('coalesce(sum(duration_ms), 0) as duration_ms')
            ->selectRaw('count(distinct screen_id) as screens')
            ->selectRaw('count(distinct content_id) as content')
            ->first();

        $events = (clone $base)
            ->with($this->playbackRelations())
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(max(1, $perPage))
            ->withQueryString()
            ->through(fn (PlayerPlaybackEvent $event) => $this->serialize($event));

        return [
            'events' => $events,
            'totals' => [
                'plays' => (int) ($totalsRow->plays ?? 0),
                'duration_ms' => (int) ($totalsRow->duration_ms ?? 0),
                'screens' => (int) ($totalsRow->screens ?? 0),
                'content' => (int) ($totalsRow->content ?? 0),
            ],
            'grouped' => $this->grouped($filters, $base),
        ];
    }

    /**
     * @return Builder<PlayerPlaybackEvent>
     */
    public function filtered(ProofOfPlayFilters $filters): Builder
    {
        $query = PlayerPlaybackEvent::query()->forTeam($filters->team);

        $query->whereBetween('played_at', [$filters->from, $filters->until]);

        foreach (['screen_id', 'location_id', 'playlist_id', 'channel_id'] as $column) {
            $value = $filters->filters[$column] ?? null;

            if (is_int($value) && $value > 0) {
                $query->where($column, $value);
            }
        }

        $contentId = $filters->filters['content_id'] ?? null;

        if (is_string($contentId) && $contentId !== '') {
            $query->where(function (Builder $inner) use ($contentId) {
                $inner->where('content_id', $contentId)
                    ->orWhere('title', 'like', '%'.$contentId.'%');
            });
        }

        $status = PlaybackStatus::tryFrom((string) ($filters->filters['status'] ?? ''));

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * @return array<string, \Closure>
     */
    public function playbackRelations(): array
    {
        $withTrashed = fn ($query) => $query->withTrashed()->select('id', 'name');

        return [
            'screen' => $withTrashed,
            'location' => fn ($query) => $query->select('id', 'name'),
            'playlist' => $withTrashed,
            'channel' => $withTrashed,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     screen: string,
     *     location: string|null,
     *     channel: string|null,
     *     playlist: string|null,
     *     content_id: string|null,
     *     title: string|null,
     *     started_at: string|null,
     *     ended_at: string|null,
     *     duration_ms: int|null,
     *     status: string,
     *     status_label: string
     * }
     */
    public function serialize(PlayerPlaybackEvent $event): array
    {
        return [
            'id' => $event->id,
            'screen' => $event->screen->name,
            'location' => $event->location?->name,
            'channel' => $event->channel?->name,
            'playlist' => $event->playlist?->name,
            'content_id' => $event->content_id,
            'title' => $event->title,
            'started_at' => $event->started_at?->toIso8601String(),
            'ended_at' => $event->ended_at?->toIso8601String(),
            'duration_ms' => $event->duration_ms,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
        ];
    }

    /**
     * @param  Builder<PlayerPlaybackEvent>  $base
     * @return list<array{key: string, label: string, plays: int, duration_ms: int, screens: int}>
     */
    protected function grouped(ProofOfPlayFilters $filters, Builder $base): array
    {
        $select = match ($filters->group) {
            'location' => [
                'player_playback_events.location_id as group_key',
                'locations.name as group_label',
            ],
            'content' => [
                'player_playback_events.content_id as group_key',
                DB::raw('coalesce(player_playback_events.title, player_playback_events.content_id) as group_label'),
            ],
            'playlist' => [
                'player_playback_events.playlist_id as group_key',
                'playlists.name as group_label',
            ],
            'channel' => [
                'player_playback_events.channel_id as group_key',
                'channels.name as group_label',
            ],
            'day' => [
                DB::raw('date(player_playback_events.played_at) as group_key'),
                DB::raw('date(player_playback_events.played_at) as group_label'),
            ],
            default => [
                'player_playback_events.screen_id as group_key',
                'screens.name as group_label',
            ],
        };

        $query = (clone $base)->select($select)
            ->selectRaw('count(*) as plays')
            ->selectRaw('coalesce(sum(player_playback_events.duration_ms), 0) as duration_ms')
            ->selectRaw('count(distinct player_playback_events.screen_id) as screens');

        match ($filters->group) {
            'location' => $query->leftJoin('locations', 'locations.id', '=', 'player_playback_events.location_id')
                ->groupBy('player_playback_events.location_id', 'locations.name'),
            'content' => $query->groupBy('player_playback_events.content_id', 'player_playback_events.title'),
            'playlist' => $query->leftJoin('playlists', 'playlists.id', '=', 'player_playback_events.playlist_id')
                ->groupBy('player_playback_events.playlist_id', 'playlists.name'),
            'channel' => $query->leftJoin('channels', 'channels.id', '=', 'player_playback_events.channel_id')
                ->groupBy('player_playback_events.channel_id', 'channels.name'),
            'day' => $query->groupBy(DB::raw('date(player_playback_events.played_at)')),
            default => $query->leftJoin('screens', 'screens.id', '=', 'player_playback_events.screen_id')
                ->groupBy('player_playback_events.screen_id', 'screens.name'),
        };

        /** @var Collection<int, object{group_key: mixed, group_label: mixed, plays: mixed, duration_ms: mixed, screens: mixed}> $rows */
        $rows = $query->orderByDesc('plays')->limit(50)->get();

        return array_values($rows->map(fn (object $row) => [
            'key' => (string) ($row->group_key ?? 'none'),
            'label' => (string) ($row->group_label ?: 'Unassigned'),
            'plays' => (int) $row->plays,
            'duration_ms' => (int) $row->duration_ms,
            'screens' => (int) $row->screens,
        ])->all());
    }
}
