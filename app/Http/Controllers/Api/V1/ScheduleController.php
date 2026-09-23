<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Schedule\SaveSchedule;
use App\Http\Requests\Schedule\SaveScheduleRequest;
use App\Models\Schedule;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Schedule::class);

        $schedules = Schedule::query()
            ->forTeam($this->team($request))
            ->with('targets')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Schedule $schedule) => PartnerApi::schedule($schedule));

        return PartnerApi::paginated($schedules);
    }

    public function store(SaveScheduleRequest $request, SaveSchedule $save): JsonResponse
    {
        $schedule = $save->handle($request->user(), $this->team($request), $request->validated());

        return PartnerApi::item(PartnerApi::schedule($schedule), 201);
    }

    public function show(Request $request, int $schedule): JsonResponse
    {
        $model = $this->findForTeam($request, Schedule::class, $schedule);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::schedule($model));
    }

    public function update(SaveScheduleRequest $request, SaveSchedule $save, int $schedule): JsonResponse
    {
        $model = $this->findForTeam($request, Schedule::class, $schedule);
        $model = $save->handle($request->user(), $this->team($request), $request->validated(), $model);

        return PartnerApi::item(PartnerApi::schedule($model));
    }

    public function destroy(Request $request, int $schedule): Response
    {
        $model = $this->findForTeam($request, Schedule::class, $schedule);
        Gate::authorize('delete', $model);
        $model->delete();

        return response()->noContent();
    }
}
