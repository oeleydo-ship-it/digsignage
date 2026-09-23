<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Signage\SaveScreen;
use App\Http\Requests\Signage\SaveScreenRequest;
use App\Models\Screen;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ScreenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Screen::class);

        $screens = Screen::query()
            ->forTeam($this->team($request))
            ->with(['location:id,name', 'groups:id,name', 'currentChannel:id,name'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Screen $screen) => PartnerApi::screen($screen));

        return PartnerApi::paginated($screens);
    }

    public function store(SaveScreenRequest $request, SaveScreen $saveScreen): JsonResponse
    {
        Gate::authorize('create', Screen::class);

        $screen = $saveScreen->handle($this->team($request), $request->validated());

        return PartnerApi::item(PartnerApi::screen($screen), 201);
    }

    public function show(Request $request, int $screen): JsonResponse
    {
        $model = $this->findForTeam($request, Screen::class, $screen);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::screen($model));
    }

    public function update(SaveScreenRequest $request, SaveScreen $saveScreen, int $screen): JsonResponse
    {
        $model = $this->findForTeam($request, Screen::class, $screen);
        Gate::authorize('update', $model);

        $model = $saveScreen->handle($this->team($request), $request->validated(), $model);

        return PartnerApi::item(PartnerApi::screen($model));
    }

    public function destroy(Request $request, int $screen): Response
    {
        $model = $this->findForTeam($request, Screen::class, $screen);
        Gate::authorize('delete', $model);
        $model->delete();

        return response()->noContent();
    }
}
