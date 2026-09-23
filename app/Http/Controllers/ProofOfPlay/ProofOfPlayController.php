<?php

namespace App\Http\Controllers\ProofOfPlay;

use App\Actions\ProofOfPlay\ExportProofOfPlay;
use App\Actions\ProofOfPlay\QueryProofOfPlay;
use App\Enums\PlaybackStatus;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Location;
use App\Models\PlayerPlaybackEvent;
use App\Models\Playlist;
use App\Models\Screen;
use App\Support\ProofOfPlayFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProofOfPlayController extends Controller
{
    /**
     * Proof-of-play report for the current team.
     */
    public function index(Request $request, QueryProofOfPlay $query): Response
    {
        Gate::authorize('viewAny', PlayerPlaybackEvent::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $filters = ProofOfPlayFilters::fromRequest($request, $team);
        $report = $query->handle($filters);

        return Inertia::render('reports/proof-of-play', [
            'filters' => $filters->filters,
            'totals' => $report['totals'],
            'grouped' => $report['grouped'],
            'events' => $report['events'],
            'options' => [
                'screens' => Screen::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Screen $screen) => ['value' => (string) $screen->id, 'label' => $screen->name]),
                'locations' => Location::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Location $location) => ['value' => (string) $location->id, 'label' => $location->name]),
                'playlists' => Playlist::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Playlist $playlist) => ['value' => (string) $playlist->id, 'label' => $playlist->name]),
                'channels' => Channel::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Channel $channel) => ['value' => (string) $channel->id, 'label' => $channel->name]),
                'statuses' => collect(PlaybackStatus::cases())->map(fn (PlaybackStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ]),
                'groups' => [
                    ['value' => 'screen', 'label' => 'Screen'],
                    ['value' => 'location', 'label' => 'Location'],
                    ['value' => 'content', 'label' => 'Content'],
                    ['value' => 'playlist', 'label' => 'Playlist'],
                    ['value' => 'channel', 'label' => 'Channel'],
                    ['value' => 'day', 'label' => 'Date'],
                ],
            ],
        ]);
    }

    /**
     * Download the filtered proof-of-play log as CSV.
     */
    public function export(Request $request, ExportProofOfPlay $export): StreamedResponse
    {
        Gate::authorize('viewAny', PlayerPlaybackEvent::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        return $export->handle(ProofOfPlayFilters::fromRequest($request, $team));
    }
}
