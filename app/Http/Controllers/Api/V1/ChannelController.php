<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Channel\SaveChannel;
use App\Http\Requests\Channel\SaveChannelRequest;
use App\Models\Channel;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Channel::class);

        $channels = Channel::query()
            ->forTeam($this->team($request))
            ->with(['playlist:id,name'])
            ->withCount(['zones', 'screens'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Channel $channel) => PartnerApi::channel($channel));

        return PartnerApi::paginated($channels);
    }

    public function store(SaveChannelRequest $request, SaveChannel $save): JsonResponse
    {
        Gate::authorize('create', Channel::class);

        $channel = $save->handle($request->user(), $this->team($request), $request->validated());

        return PartnerApi::item(PartnerApi::channel($channel), 201);
    }

    public function show(Request $request, int $channel): JsonResponse
    {
        $model = $this->findForTeam($request, Channel::class, $channel);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::channel($model));
    }

    public function update(SaveChannelRequest $request, SaveChannel $save, int $channel): JsonResponse
    {
        $model = $this->findForTeam($request, Channel::class, $channel);
        Gate::authorize('update', $model);

        $model = $save->handle($request->user(), $this->team($request), $request->validated(), $model);

        return PartnerApi::item(PartnerApi::channel($model));
    }

    public function destroy(Request $request, int $channel): Response
    {
        $model = $this->findForTeam($request, Channel::class, $channel);
        Gate::authorize('delete', $model);
        $model->delete();

        return response()->noContent();
    }
}
