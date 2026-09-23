<?php

namespace App\Http\Controllers\Emergency;

use App\Actions\Emergency\ResolveEmergencyScreens;
use App\Actions\Emergency\SaveEmergency;
use App\Actions\Emergency\StartEmergencyBroadcast;
use App\Actions\Emergency\StopEmergencyBroadcast;
use App\Enums\EmergencySeverity;
use App\Enums\EmergencyTargetType;
use App\Enums\MediaType;
use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Emergency\SaveEmergencyRequest;
use App\Models\Emergency;
use App\Models\EmergencyAudit;
use App\Models\EmergencyDelivery;
use App\Models\EmergencyTarget;
use App\Models\Location;
use App\Models\Media;
use App\Models\Screen;
use App\Models\Team;
use App\Support\TeamQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyController extends Controller
{
    /**
     * List emergency broadcasts for the current team.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Emergency::class);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $emergencies = Emergency::query()
            ->forTeam($team)
            ->withCount('deliveries')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Emergency $emergency) => $this->listPayload($emergency));

        return Inertia::render('emergencies/index', [
            'emergencies' => $emergencies,
            'screens' => Screen::query()->forTeam($team)->orderBy('name')->get(['id', 'name', 'location_id'])
                ->map(fn (Screen $screen) => [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'location_id' => $screen->location_id,
                ])->values()->all(),
            'locations' => Location::query()->forTeam($team)->orderBy('path')->orderBy('name')->get(['id', 'name', 'depth'])
                ->map(fn (Location $location) => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'depth' => $location->depth,
                ])->values()->all(),
            'images' => $this->mediaOptions($team, MediaType::Image),
            'videos' => $this->mediaOptions($team, MediaType::Video),
            'severities' => collect(EmergencySeverity::cases())->map(fn (EmergencySeverity $severity) => [
                'value' => $severity->value,
                'label' => $severity->label(),
            ])->values()->all(),
            'permissions' => $request->user()->toEmergencyPermissions($team),
        ]);
    }

    /**
     * Store a draft broadcast.
     */
    public function store(SaveEmergencyRequest $request, SaveEmergency $saveEmergency): RedirectResponse
    {
        Gate::authorize('create', Emergency::class);

        $emergency = $saveEmergency->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Emergency draft saved.')]);

        return redirect()->route('emergencies.show', [$request->user()->currentTeam, $emergency]);
    }

    /**
     * Show a broadcast, delivery status, and audit trail.
     */
    public function show(Request $request, string $current_team, Emergency $emergency, ResolveEmergencyScreens $resolveScreens): Response
    {
        Gate::authorize('view', $emergency);
        $this->assertAccessible($request, $current_team, $emergency);

        $emergency->load(['targets.screen', 'targets.location', 'image', 'video', 'creator', 'starter', 'stopper']);
        $audience = $resolveScreens->handle($request->user()->currentTeam, $emergency);

        $deliveries = $emergency->deliveries()
            ->with('screen:id,name')
            ->get()
            ->map(fn (EmergencyDelivery $delivery) => [
                'id' => $delivery->id,
                'screen_id' => $delivery->screen_id,
                'screen_name' => $delivery->screen->name,
                'status' => $delivery->status->value,
                'status_label' => $delivery->status->label(),
                'sent_at' => $delivery->sent_at?->toIso8601String(),
                'acknowledged_at' => $delivery->acknowledged_at?->toIso8601String(),
                'completed_at' => $delivery->completed_at?->toIso8601String(),
            ]);

        $audits = $emergency->audits()
            ->with('user:id,name')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (EmergencyAudit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action->value,
                'action_label' => $audit->action->label(),
                'user' => $audit->user?->name,
                'screen_id' => $audit->screen_id,
                'payload' => $audit->payload,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return Inertia::render('emergencies/show', [
            'emergency' => $this->detailPayload($emergency, $audience->count()),
            'deliveries' => $deliveries,
            'audits' => $audits,
            'permissions' => $request->user()->toEmergencyPermissions($request->user()->currentTeam),
        ]);
    }

    /**
     * Update a draft or scheduled broadcast.
     */
    public function update(
        SaveEmergencyRequest $request,
        string $current_team,
        Emergency $emergency,
        SaveEmergency $saveEmergency,
    ): RedirectResponse {
        Gate::authorize('update', $emergency);
        $this->assertAccessible($request, $current_team, $emergency);

        $saveEmergency->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated(),
            $emergency,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Emergency saved.')]);

        return back();
    }

    /**
     * Start the broadcast after confirmation.
     */
    public function start(
        Request $request,
        string $current_team,
        Emergency $emergency,
        StartEmergencyBroadcast $start,
    ): RedirectResponse {
        Gate::authorize('start', $emergency);
        $this->assertAccessible($request, $current_team, $emergency);
        app(TeamQuota::class)->assertCanUseFeature($emergency->team, PlanFeature::Emergencies);

        $validated = $request->validate([
            'confirmed' => ['accepted'],
        ]);

        $start->handle($emergency, $request->user(), (bool) $validated['confirmed']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Emergency broadcast started.')]);

        return back();
    }

    /**
     * Stop the broadcast after confirmation.
     */
    public function stop(
        Request $request,
        string $current_team,
        Emergency $emergency,
        StopEmergencyBroadcast $stop,
    ): RedirectResponse {
        Gate::authorize('stop', $emergency);
        $this->assertAccessible($request, $current_team, $emergency);

        $validated = $request->validate([
            'confirmed' => ['accepted'],
        ]);

        $stop->handle($emergency, $request->user(), (bool) $validated['confirmed']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Emergency broadcast stopped.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(Emergency $emergency): array
    {
        return [
            'id' => $emergency->id,
            'title' => $emergency->title,
            'severity' => $emergency->severity->value,
            'severity_label' => $emergency->severity->label(),
            'status' => $emergency->status->value,
            'status_label' => $emergency->status->label(),
            'starts_at' => $emergency->starts_at?->toIso8601String(),
            'expires_at' => $emergency->expires_at?->toIso8601String(),
            'started_at' => $emergency->started_at?->toIso8601String(),
            'deliveries_count' => (int) $emergency->getAttribute('deliveries_count'),
            'updated_at' => $emergency->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPayload(Emergency $emergency, int $audienceCount): array
    {
        return [
            'id' => $emergency->id,
            'title' => $emergency->title,
            'message' => $emergency->message,
            'instructions' => $emergency->instructions,
            'background' => $emergency->background,
            'severity' => $emergency->severity->value,
            'severity_label' => $emergency->severity->label(),
            'status' => $emergency->status->value,
            'status_label' => $emergency->status->label(),
            'image_id' => $emergency->image_id,
            'image_name' => $emergency->image?->name,
            'video_id' => $emergency->video_id,
            'video_name' => $emergency->video?->name,
            'starts_at' => $emergency->starts_at?->toIso8601String(),
            'expires_at' => $emergency->expires_at?->toIso8601String(),
            'started_at' => $emergency->started_at?->toIso8601String(),
            'stopped_at' => $emergency->stopped_at?->toIso8601String(),
            'created_by' => $emergency->creator?->name,
            'started_by' => $emergency->starter?->name,
            'stopped_by' => $emergency->stopper?->name,
            'audience_count' => $audienceCount,
            'screen_ids' => $emergency->targets
                ->filter(fn (EmergencyTarget $target) => $target->target_type === EmergencyTargetType::Screen)
                ->pluck('screen_id')
                ->filter()
                ->values()
                ->all(),
            'location_ids' => $emergency->targets
                ->filter(fn (EmergencyTarget $target) => $target->target_type === EmergencyTargetType::Location)
                ->pluck('location_id')
                ->filter()
                ->values()
                ->all(),
            'targets' => $emergency->targets->map(fn (EmergencyTarget $target) => [
                'type' => $target->target_type->value,
                'label' => match ($target->target_type) {
                    EmergencyTargetType::Screen => $target->screen->name,
                    EmergencyTargetType::Location => $target->location->name,
                },
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function mediaOptions(Team $team, MediaType $type): array
    {
        return Media::query()
            ->forTeam($team)
            ->where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'name' => $media->name,
            ])
            ->values()
            ->all();
    }

    protected function assertAccessible(Request $request, string $currentTeam, Emergency $emergency): void
    {
        abort_unless($emergency->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
    }
}
