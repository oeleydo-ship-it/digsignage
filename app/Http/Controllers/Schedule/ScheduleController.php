<?php

namespace App\Http\Controllers\Schedule;

use App\Actions\Schedule\ResolveSchedule;
use App\Actions\Schedule\SaveSchedule;
use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Enums\ScheduleTargetType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\SaveScheduleRequest;
use App\Models\Channel;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\ScheduleTarget;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Support\ScheduleCalendar;
use App\Support\ScheduleClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Display the weekly schedule calendar.
     */
    public function index(Request $request, ResolveSchedule $resolveSchedule): Response
    {
        Gate::authorize('viewAny', Schedule::class);

        $team = $request->user()->currentTeam;
        $weekParam = $request->string('week')->toString();
        $screenId = $request->integer('screen_id') ?: null;
        $timezone = $request->string('timezone')->toString();

        $screen = $screenId !== null
            ? Screen::query()->forTeam($team)->with('location.parent')->whereKey($screenId)->first()
            : null;

        if ($timezone === '' || ! ScheduleClock::isValidTimezone($timezone)) {
            $timezone = $screen !== null ? ScheduleClock::timezoneFor($screen) : 'UTC';
        }

        $weekStart = $weekParam !== ''
            ? CarbonImmutable::parse($weekParam, $timezone)->startOfWeek(CarbonImmutable::MONDAY)
            : CarbonImmutable::now($timezone)->startOfWeek(CarbonImmutable::MONDAY);

        $schedules = Schedule::query()
            ->forTeam($team)
            ->with(['channel:id,name', 'playlist:id,name', 'targets.screen', 'targets.screenGroup', 'targets.location'])
            ->orderByDesc('priority')
            ->orderBy('start_time')
            ->get();

        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->addDays($offset);
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->isoFormat('ddd D'),
                'is_today' => $day->isSameDay(CarbonImmutable::now($timezone)),
            ];
        }

        $resolved = null;

        if ($screen !== null) {
            $resolved = $resolveSchedule->handle($screen, CarbonImmutable::now($timezone))->toArray();
        }

        return Inertia::render('schedules/index', [
            'schedules' => $schedules->map(fn (Schedule $schedule) => $this->schedulePayload($schedule))->values()->all(),
            'occurrences' => ScheduleCalendar::occurrences($schedules, $weekStart, $screen, $timezone),
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekStart->addDays(6)->toDateString(),
                'previous' => $weekStart->subWeek()->toDateString(),
                'next' => $weekStart->addWeek()->toDateString(),
                'days' => $days,
            ],
            'filters' => [
                'week' => $weekStart->toDateString(),
                'screen_id' => $screen?->id,
                'timezone' => $timezone,
            ],
            'resolved' => $resolved,
            'channels' => Channel::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Channel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                ])->values()->all(),
            'playlists' => Playlist::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Playlist $playlist) => [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                ])->values()->all(),
            'screens' => Screen::query()->forTeam($team)->with('location:id,name,timezone')->orderBy('name')->get()
                ->map(fn (Screen $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'timezone' => ScheduleClock::timezoneFor($item),
                    'current_channel_id' => $item->current_channel_id,
                ])->values()->all(),
            'groups' => ScreenGroup::query()->forTeam($team)->orderBy('name')->get(['id', 'name'])
                ->map(fn (ScreenGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                ])->values()->all(),
            'locations' => Location::query()->forTeam($team)->orderBy('path')->orderBy('name')->get(['id', 'name', 'depth'])
                ->map(fn (Location $location) => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'depth' => $location->depth,
                ])->values()->all(),
            'timezones' => $this->timezoneOptions(),
            'recurrences' => $this->enumOptions(ScheduleRecurrence::cases()),
            'contentTypes' => $this->enumOptions(ScheduleContentType::cases()),
            'targetTypes' => $this->enumOptions(ScheduleTargetType::cases()),
            'permissions' => $request->user()->toSchedulePermissions($team),
        ]);
    }

    /**
     * Store a new schedule.
     */
    public function store(SaveScheduleRequest $request, SaveSchedule $saveSchedule): RedirectResponse
    {
        Gate::authorize('create', Schedule::class);

        $saveSchedule->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule created.')]);

        return back();
    }

    /**
     * Update a schedule.
     */
    public function update(
        SaveScheduleRequest $request,
        string $current_team,
        Schedule $schedule,
        SaveSchedule $saveSchedule,
    ): RedirectResponse {
        Gate::authorize('update', $schedule);
        $this->assertAccessible($request, $current_team, $schedule);

        $saveSchedule->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $schedule,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule saved.')]);

        return back();
    }

    /**
     * Remove a schedule.
     */
    public function destroy(Request $request, string $current_team, Schedule $schedule): RedirectResponse
    {
        Gate::authorize('delete', $schedule);
        $this->assertAccessible($request, $current_team, $schedule);

        $schedule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function schedulePayload(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'description' => $schedule->description,
            'content_type' => $schedule->content_type->value,
            'content_type_label' => $schedule->content_type->label(),
            'channel_id' => $schedule->channel_id,
            'channel_name' => $schedule->channel?->name,
            'playlist_id' => $schedule->playlist_id,
            'playlist_name' => $schedule->playlist?->name,
            'timezone' => $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
            'start_time' => substr($schedule->start_time, 0, 5),
            'end_time' => substr($schedule->end_time, 0, 5),
            'recurrence' => $schedule->recurrence->value,
            'recurrence_label' => $schedule->recurrence->label(),
            'weekdays' => $schedule->weekdays ?? [],
            'priority' => $schedule->priority,
            'is_enabled' => $schedule->is_enabled,
            'targets' => $schedule->targets->map(fn (ScheduleTarget $target) => $target->toEditorArray())->values()->all(),
        ];
    }

    /**
     * @param  list<\UnitEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    protected function enumOptions(array $cases): array
    {
        $options = [];

        foreach ($cases as $item) {
            if (! $item instanceof ScheduleContentType
                && ! $item instanceof ScheduleRecurrence
                && ! $item instanceof ScheduleTargetType) {
                continue;
            }

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
    protected function timezoneOptions(): array
    {
        $identifiers = [
            'UTC',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'America/Toronto',
            'America/Sao_Paulo',
            'Europe/London',
            'Europe/Paris',
            'Europe/Berlin',
            'Africa/Johannesburg',
            'Asia/Dubai',
            'Asia/Kolkata',
            'Asia/Singapore',
            'Asia/Tokyo',
            'Australia/Sydney',
            'Pacific/Auckland',
        ];

        $options = [];

        foreach ($identifiers as $identifier) {
            $options[] = [
                'value' => $identifier,
                'label' => $identifier,
            ];
        }

        return $options;
    }

    protected function assertAccessible(Request $request, string $currentTeam, Schedule $schedule): void
    {
        abort_unless($schedule->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
    }
}
