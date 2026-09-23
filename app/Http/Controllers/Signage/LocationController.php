<?php

namespace App\Http\Controllers\Signage;

use App\Actions\Signage\DeleteLocation;
use App\Actions\Signage\SaveLocation;
use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signage\SaveLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Location::class);

        $team = $request->user()->currentTeam;

        $locations = Location::query()
            ->forTeam($team)
            ->withCount(['children', 'screens'])
            ->orderBy('path')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location) => $this->locationPayload($location));

        return Inertia::render('signage/locations/index', [
            'locations' => $locations,
            'types' => collect(LocationType::cases())->map(fn (LocationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'permissions' => $request->user()->toSignagePermissions($team),
        ]);
    }

    /**
     * Store a newly created location.
     */
    public function store(SaveLocationRequest $request, SaveLocation $saveLocation): RedirectResponse
    {
        Gate::authorize('create', Location::class);

        $saveLocation->handle($request->user()->currentTeam, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location created.')]);

        return back();
    }

    /**
     * Update the specified location.
     */
    public function update(SaveLocationRequest $request, string $current_team, Location $location, SaveLocation $saveLocation): RedirectResponse
    {
        Gate::authorize('update', $location);
        abort_unless($location->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $saveLocation->handle($request->user()->currentTeam, $request->validated(), $location);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location updated.')]);

        return back();
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Request $request, string $current_team, Location $location, DeleteLocation $deleteLocation): RedirectResponse
    {
        Gate::authorize('delete', $location);

        abort_unless($location->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);

        $deleteLocation->handle($location);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function locationPayload(Location $location): array
    {
        return [
            'id' => $location->id,
            'parent_id' => $location->parent_id,
            'type' => $location->type->value,
            'type_label' => $location->type->label(),
            'name' => $location->name,
            'description' => $location->description,
            'address' => $location->address,
            'timezone' => $location->timezone,
            'path' => $location->path,
            'depth' => $location->depth,
            'tags' => $location->tags ?? [],
            'children_count' => $location->children_count,
            'screens_count' => $location->screens_count,
        ];
    }
}
