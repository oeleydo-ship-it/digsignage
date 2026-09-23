<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Signage\SyncScreenGroupScreens;
use App\Http\Requests\Signage\SaveScreenGroupRequest;
use App\Http\Requests\Signage\SyncScreenGroupScreensRequest;
use App\Models\ScreenGroup;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ScreenGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScreenGroup::class);

        $groups = ScreenGroup::query()
            ->forTeam($this->team($request))
            ->with(['screens:id,name'])
            ->withCount('screens')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (ScreenGroup $group) => PartnerApi::screenGroup($group));

        return PartnerApi::paginated($groups);
    }

    public function store(SaveScreenGroupRequest $request): JsonResponse
    {
        Gate::authorize('create', ScreenGroup::class);

        $group = $this->team($request)->screenGroups()->create($request->validated());

        return PartnerApi::item(PartnerApi::screenGroup($group), 201);
    }

    public function show(Request $request, int $screenGroup): JsonResponse
    {
        $model = $this->findForTeam($request, ScreenGroup::class, $screenGroup);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::screenGroup($model));
    }

    public function update(SaveScreenGroupRequest $request, int $screenGroup): JsonResponse
    {
        $model = $this->findForTeam($request, ScreenGroup::class, $screenGroup);
        Gate::authorize('update', $model);
        $model->update($request->validated());

        return PartnerApi::item(PartnerApi::screenGroup($model->refresh()));
    }

    public function sync(SyncScreenGroupScreensRequest $request, SyncScreenGroupScreens $sync, int $screenGroup): JsonResponse
    {
        $model = $this->findForTeam($request, ScreenGroup::class, $screenGroup);
        Gate::authorize('update', $model);

        $screenIds = $request->validated('screen_ids');
        $screenIds = is_array($screenIds) ? array_map(intval(...), $screenIds) : [];

        $ids = [];

        foreach ($screenIds as $id) {
            $ids[] = (int) $id;
        }

        $model = $sync->handle($this->team($request), $model, $ids);

        return PartnerApi::item(PartnerApi::screenGroup($model));
    }

    public function destroy(Request $request, int $screenGroup): Response
    {
        $model = $this->findForTeam($request, ScreenGroup::class, $screenGroup);
        Gate::authorize('delete', $model);
        $model->delete();

        return response()->noContent();
    }
}
