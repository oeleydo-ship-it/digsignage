<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Signage\DeleteLocation;
use App\Actions\Signage\SaveLocation;
use App\Http\Requests\Signage\SaveLocationRequest;
use App\Models\Location;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Location::class);

        $locations = Location::query()
            ->forTeam($this->team($request))
            ->orderBy('path')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Location $location) => PartnerApi::location($location));

        return PartnerApi::paginated($locations);
    }

    public function store(SaveLocationRequest $request, SaveLocation $saveLocation): JsonResponse
    {
        Gate::authorize('create', Location::class);

        $location = $saveLocation->handle($this->team($request), $request->validated());

        return PartnerApi::item(PartnerApi::location($location), 201);
    }

    public function show(Request $request, int $location): JsonResponse
    {
        $model = $this->findForTeam($request, Location::class, $location);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::location($model));
    }

    public function update(SaveLocationRequest $request, SaveLocation $saveLocation, int $location): JsonResponse
    {
        $model = $this->findForTeam($request, Location::class, $location);
        Gate::authorize('update', $model);

        $model = $saveLocation->handle($this->team($request), $request->validated(), $model);

        return PartnerApi::item(PartnerApi::location($model));
    }

    public function destroy(Request $request, DeleteLocation $deleteLocation, int $location): Response
    {
        $model = $this->findForTeam($request, Location::class, $location);
        Gate::authorize('delete', $model);
        $deleteLocation->handle($model);

        return response()->noContent();
    }
}
