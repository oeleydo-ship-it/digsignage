<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Actions\Signage\BulkUpdateScreens;
use App\Actions\Signage\PairDeviceRegistration;
use App\Actions\Signage\RotateDeviceCredentials;
use App\Actions\Signage\SaveScreen;
use App\Data\MonitoringThresholds;
use App\Enums\AuditAction;
use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signage\BulkUpdateScreensRequest;
use App\Http\Requests\Signage\PairScreenRequest;
use App\Http\Requests\Signage\SaveScreenRequest;
use App\Models\Location;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Support\ScreenHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    /**
     * Display a listing of screens.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Screen::class);

        $team = $request->user()->currentTeam;
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $locationId = $request->integer('location_id') ?: null;

        $thresholds = ScreenHealth::thresholds($team);

        $screens = Screen::query()
            ->forTeam($team)
            ->with(['location:id,name', 'groups:id,name', 'currentChannel:id,name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Screen $screen) => $this->screenPayload($screen, $thresholds));

        return Inertia::render('signage/screens/index', [
            'screens' => $screens,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'location_id' => $locationId,
            ],
            'locations' => Location::query()->forTeam($team)->orderBy('path')->orderBy('name')->get(['id', 'name', 'depth']),
            'groups' => ScreenGroup::query()->forTeam($team)->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(ScreenStatus::cases())->map(fn (ScreenStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'orientations' => collect(ScreenOrientation::cases())->map(fn (ScreenOrientation $orientation) => [
                'value' => $orientation->value,
                'label' => $orientation->label(),
            ]),
            'permissions' => $request->user()->toSignagePermissions($team),
            'plain_device_token' => $request->session()->get('plain_device_token'),
        ]);
    }

    /**
     * Store an unpaired screen.
     */
    public function store(SaveScreenRequest $request, SaveScreen $saveScreen): RedirectResponse
    {
        Gate::authorize('create', Screen::class);

        $saveScreen->handle($request->user()->currentTeam, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen created.')]);

        return back(fallback: route('screens.index', $request->user()->currentTeam));
    }

    /**
     * Pair a player registration code to a new screen.
     */
    public function pair(PairScreenRequest $request, PairDeviceRegistration $pairDevice): RedirectResponse
    {
        Gate::authorize('pair', Screen::class);

        $pairDevice->handle(
            $request->user(),
            $request->user()->currentTeam,
            $request->validated('code'),
            $request->safe()->except('code'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen paired.')]);

        return back(fallback: route('screens.index', $request->user()->currentTeam));
    }

    /**
     * Issue a new device token for a paired screen.
     */
    public function rotateCredentials(Request $request, string $current_team, Screen $screen, RotateDeviceCredentials $rotate): RedirectResponse
    {
        Gate::authorize('rotateCredentials', $screen);
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $token = $rotate->handle($request->user(), $screen);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device credentials rotated. Copy the new token now.')]);

        return back(fallback: route('screens.index', $request->user()->currentTeam))->with('plain_device_token', $token);
    }

    /**
     * Update the specified screen.
     */
    public function update(SaveScreenRequest $request, string $current_team, Screen $screen, SaveScreen $saveScreen): RedirectResponse
    {
        Gate::authorize('update', $screen);
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveScreen->handle($request->user()->currentTeam, $request->validated(), $screen);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen updated.')]);

        return back(fallback: route('screens.index', $request->user()->currentTeam));
    }

    /**
     * Remove the specified screen.
     */
    public function destroy(Request $request, string $current_team, Screen $screen): RedirectResponse
    {
        Gate::authorize('delete', $screen);
        abort_unless($screen->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        app(RecordOrganizationAudit::class)->handle(
            $request->user()->currentTeam,
            AuditAction::ScreenDeleted,
            $request->user(),
            'screen',
            $screen->id,
            ['name' => $screen->name],
            null,
        );

        $screen->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Screen deleted.')]);

        return back(fallback: route('screens.index', $request->user()->currentTeam));
    }

    /**
     * Apply a bulk action to screens.
     */
    public function bulk(BulkUpdateScreensRequest $request, BulkUpdateScreens $bulkUpdateScreens): RedirectResponse
    {
        Gate::authorize('bulkUpdate', Screen::class);

        $count = $bulkUpdateScreens->handle(
            $request->user()->currentTeam,
            $request->validated('screen_ids'),
            $request->validated('action'),
            $request->safe()->only(['location_id', 'screen_group_id']),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count screen updated.|:count screens updated.', $count, ['count' => $count]),
        ]);

        return back(fallback: route('screens.index', $request->user()->currentTeam));
    }

    /**
     * @return array<string, mixed>
     */
    protected function screenPayload(Screen $screen, MonitoringThresholds $thresholds): array
    {
        $health = ScreenHealth::snapshot($screen, $thresholds);
        $metadata = is_array($screen->metadata) ? $screen->metadata : [];
        $fallbackImage = $metadata['fallback_image'] ?? null;

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'description' => $screen->description,
            'location_id' => $screen->location_id,
            'location_name' => $screen->location?->name,
            'orientation' => $screen->orientation->value,
            'resolution_width' => $screen->resolution_width,
            'resolution_height' => $screen->resolution_height,
            'timezone' => $screen->timezone,
            'status' => $health['status'],
            'status_label' => $health['status_label'],
            'paired' => $screen->isPaired(),
            'device_uuid' => $screen->device_uuid,
            'last_seen_at' => $health['last_seen_at'],
            'app_version' => $health['app_version'],
            'current_channel' => $health['current_channel'],
            'current_content' => $health['current_content'],
            'storage_available' => $health['storage_available'],
            'storage_total' => $health['storage_total'],
            'last_error' => $health['last_error'],
            'fallback_image_url' => is_string($fallbackImage) && $fallbackImage !== '' ? $fallbackImage : null,
            'groups' => $screen->groups->map(fn (ScreenGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
            ]),
        ];
    }
}
