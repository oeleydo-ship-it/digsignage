<?php

namespace App\Http\Controllers\Queue;

use App\Actions\Queue\BuildQueueOperationsDashboard;
use App\Actions\Queue\SaveQueueAlertSettings;
use App\Actions\Queue\SaveQueueNotificationRules;
use App\Actions\Queue\SaveQueueStarvationSettings;
use App\Actions\Queue\SaveQueueVoiceSettings;
use App\Actions\Queue\SelectNextQueueTicket;
use App\Data\QueueAlertConfig;
use App\Data\QueueStarvationConfig;
use App\Data\QueueVoiceConfig;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Enums\QueueStrategy;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Queue\SaveQueueAlertSettingsRequest;
use App\Http\Requests\Queue\SaveQueueNotificationRulesRequest;
use App\Http\Requests\Queue\SaveQueueStarvationRequest;
use App\Http\Requests\Queue\SaveQueueVoiceSettingsRequest;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\QueueCounter;
use App\Models\QueueNotificationRule;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Screen;
use App\Support\QueueBoardPresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QueuePageController extends Controller
{
    /**
     * Queue overview dashboard for the current team.
     */
    public function overview(Request $request, BuildQueueOperationsDashboard $dashboard): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        $requestedLocationId = $request->integer('location_id') ?: null;
        $locationId = $requestedLocationId === null
            ? null
            : Location::query()->forTeam($team)->whereKey($requestedLocationId)->value('id');

        return $this->page($request, 'queue/overview', [
            'operations' => $dashboard->handle($team, $locationId),
            'filters' => ['location_id' => $locationId],
            'locations' => Location::query()
                ->forTeam($team)
                ->orderBy('path')
                ->orderBy('name')
                ->get(['id', 'name', 'depth']),
            'reverb' => $this->reverbConfig(),
        ]);
    }

    /**
     * Live waiting line ordered by the dispatch engine.
     */
    public function live(Request $request, SelectNextQueueTicket $selectNextQueueTicket): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $services = QueueService::query()
            ->forTeam($team)
            ->orderBy('name')
            ->get()
            ->map(function (QueueService $service) use ($team, $selectNextQueueTicket) {
                $waiting = $selectNextQueueTicket->rankedWaiting($team, $service);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'queue_strategy' => $service->queue_strategy->value,
                    'queue_strategy_label' => $service->queue_strategy->label(),
                    'waiting_count' => $waiting->count(),
                    'waiting' => $waiting->map(fn (QueueTicket $ticket) => [
                        'id' => $ticket->id,
                        'number' => $ticket->number,
                        'priority' => $ticket->priority,
                        'queue_position' => $ticket->queue_position,
                        'customer_name' => $ticket->customer_name,
                        'created_at' => $ticket->created_at?->toIso8601String(),
                    ])->values(),
                ];
            });

        return $this->page($request, 'queue/live', [
            'title' => 'Live Queue',
            'services' => $services,
        ]);
    }

    /**
     * Queue displays shell.
     */
    public function displays(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $screens = Screen::query()
            ->forTeam($team)
            ->with(['location:id,name', 'currentChannel:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Screen $screen) => [
                'id' => $screen->id,
                'name' => $screen->name,
                'location_name' => $screen->location?->name,
                'status' => $screen->status->value,
                'status_label' => $screen->status->label(),
                'paired' => $screen->isPaired(),
                'last_seen_at' => $screen->last_seen_at?->toIso8601String(),
                'current_channel_id' => $screen->current_channel_id,
                'current_channel' => $screen->currentChannel?->name,
                'current_content' => is_array($screen->metadata)
                    ? ($screen->metadata['current_content'] ?? null)
                    : null,
            ]);

        $nowServing = QueueTicket::query()
            ->forTeam($team)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->with(['service:id,name', 'counter:id,name,code'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (QueueTicket $ticket) => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'service_name' => $ticket->service->name,
                'counter_name' => $ticket->counter_id === null ? null : $ticket->counter->name,
                'status' => $ticket->status->value,
            ])
            ->values();

        $queueBoards = Design::query()
            ->forTeam($team)
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn (Design $design) => QueueDisplayController::isQueueBoard($design))
            ->map(fn (Design $design) => [
                'id' => $design->id,
                'name' => $design->name,
                'status' => $design->status->value,
                'status_label' => $design->status->label(),
                'width' => $design->width,
                'height' => $design->height,
                'updated_at' => $design->updated_at?->toIso8601String(),
            ])
            ->values();

        $user = $request->user();
        $canManageQueue = $user->hasTeamPermission($team, TeamPermission::ManageQueue);
        $canCreateBoard = $canManageQueue && Gate::allows('create', Design::class);
        $canDeploy = $canManageQueue
            && Gate::allows('create', Playlist::class)
            && Gate::allows('create', Channel::class)
            && $user->hasTeamPermission($team, TeamPermission::PublishContent)
            && $user->hasTeamPermission($team, TeamPermission::UpdateScreen);

        return $this->page($request, 'queue/displays', [
            'title' => 'Displays',
            'screens' => $screens,
            'nowServing' => $nowServing,
            'waitingCount' => QueueTicket::query()
                ->forTeam($team)
                ->where('status', QueueTicketStatus::Waiting)
                ->count(),
            'presets' => QueueBoardPresets::catalog(),
            'queueBoards' => $queueBoards,
            'services' => QueueService::query()
                ->forTeam($team)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'locations' => Location::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name']),
            'counters' => QueueCounter::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'displayPermissions' => [
                'canCreateBoard' => $canCreateBoard,
                'canDeploy' => $canDeploy,
            ],
        ]);
    }

    /**
     * Queue priority levels and starvation settings.
     */
    public function settings(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $settings = QueueSetting::resolveForTeam($team);
        $starvation = QueueStarvationConfig::resolve(null, $settings->settings ?? []);
        $alerts = QueueAlertConfig::resolve($settings->settings ?? []);
        $voice = QueueVoiceConfig::resolve($settings->settings ?? []);
        $notificationRules = QueueNotificationRule::query()->forTeam($team)->get();
        $notificationMatrix = [];

        foreach ($notificationRules as $rule) {
            $notificationMatrix[$rule->event->value][$rule->channel->value] = $rule->is_enabled;
        }

        $appointmentNotificationRule = $notificationRules
            ->firstWhere('event', QueueNotificationEvent::AppointmentApproaching);

        $priorities = QueuePriority::query()
            ->forTeam($team)
            ->orderBy('sort_order')
            ->orderBy('weight')
            ->orderBy('name')
            ->get()
            ->map(fn (QueuePriority $priority) => [
                'id' => $priority->id,
                'name' => $priority->name,
                'code' => $priority->code,
                'weight' => $priority->weight,
                'color' => $priority->color,
                'is_active' => $priority->is_active,
                'sort_order' => $priority->sort_order,
            ]);

        return $this->page($request, 'queue/settings', [
            'title' => 'Settings',
            'priorities' => $priorities,
            'starvation' => $starvation->toArray(),
            'alerts' => $alerts->toArray(),
            'voice' => $voice->toArray(),
            'customerNotifications' => [
                'events' => collect(QueueNotificationEvent::cases())->map(fn ($event) => ['value' => $event->value, 'label' => $event->label()]),
                'channels' => collect(QueueNotificationChannel::cases())->map(fn ($channel) => ['value' => $channel->value, 'label' => $channel->label()]),
                'rules' => $notificationMatrix,
                'appointment_minutes_before' => (int) ($appointmentNotificationRule === null
                    ? 60
                    : ($appointmentNotificationRule->minutes_before ?? 60)),
            ],
            'strategies' => collect(QueueStrategy::cases())->map(fn (QueueStrategy $strategy) => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
            ]),
        ]);
    }

    /**
     * Update team-wide starvation defaults.
     */
    public function updateSettings(
        SaveQueueStarvationRequest $request,
        SaveQueueStarvationSettings $saveQueueStarvationSettings,
    ): RedirectResponse {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $settings = QueueSetting::resolveForTeam($team);
        Gate::authorize('update', $settings);

        $saveQueueStarvationSettings->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue settings saved.')]);

        return back(fallback: route('queue.settings', $team));
    }

    /**
     * Update automatic queue voice announcements.
     */
    public function updateVoiceSettings(
        SaveQueueVoiceSettingsRequest $request,
        SaveQueueVoiceSettings $saveQueueVoiceSettings,
    ): RedirectResponse {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $settings = QueueSetting::resolveForTeam($team);
        Gate::authorize('update', $settings);

        $saveQueueVoiceSettings->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Voice announcement settings saved.')]);

        return back(fallback: route('queue.settings', $team));
    }

    /** Update automated queue operations alerts. */
    public function updateAlertSettings(
        SaveQueueAlertSettingsRequest $request,
        SaveQueueAlertSettings $saveQueueAlertSettings,
    ): RedirectResponse {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        Gate::authorize('update', QueueSetting::resolveForTeam($team));
        $saveQueueAlertSettings->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue alert settings saved.')]);

        return back(fallback: route('queue.settings', $team));
    }

    public function updateNotificationRules(
        SaveQueueNotificationRulesRequest $request,
        SaveQueueNotificationRules $save,
    ): RedirectResponse {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('update', QueueSetting::resolveForTeam($team));
        $save->handle($team, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer notification rules saved.')]);

        return back(fallback: route('queue.settings', $team));
    }

    /** @return array{enabled: bool, key: mixed, host: mixed, port: int, scheme: mixed} */
    protected function reverbConfig(): array
    {
        return [
            'enabled' => config('broadcasting.default') === 'reverb'
                && filled(config('broadcasting.connections.reverb.key')),
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host') ?: 'localhost',
            'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme') ?: 'http',
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function page(Request $request, string $component, array $extra = []): Response
    {
        Gate::authorize('viewAny', QueueSetting::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $settings = QueueSetting::resolveForTeam($team);

        return Inertia::render($component, [
            'permissions' => $request->user()->toQueuePermissions($team),
            'settings' => $settings->settings ?? [],
            ...$extra,
        ]);
    }
}
