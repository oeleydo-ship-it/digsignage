<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\StoreExternalMedia;
use App\Actions\Media\UpdateMedia;
use App\Http\Requests\Media\StoreExternalMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Models\Media;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $media = Media::query()
            ->forTeam($this->team($request))
            ->notArchived()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->through(fn (Media $item) => PartnerApi::media($item));

        return PartnerApi::paginated($media);
    }

    public function store(StoreExternalMediaRequest $request, StoreExternalMedia $store): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $tags = $request->validated('tags');
        $tagList = [];

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                if (is_string($tag)) {
                    $tagList[] = $tag;
                }
            }
        }

        $media = $store->handle(
            $request->user(),
            $this->team($request),
            $request->validated(),
            $tagList,
        );

        return PartnerApi::item(PartnerApi::media($media), 201);
    }

    public function show(Request $request, int $media): JsonResponse
    {
        $model = $this->findForTeam($request, Media::class, $media);
        Gate::authorize('view', $model);

        return PartnerApi::item(PartnerApi::media($model));
    }

    public function update(UpdateMediaRequest $request, UpdateMedia $update, int $media): JsonResponse
    {
        $model = $this->findForTeam($request, Media::class, $media);
        Gate::authorize('update', $model);

        $model = $update->handle($this->team($request), $model, $request->validated());

        return PartnerApi::item(PartnerApi::media($model));
    }

    public function destroy(Request $request, DeleteMedia $delete, int $media): Response
    {
        $model = $this->findForTeam($request, Media::class, $media);
        Gate::authorize('delete', $model);
        $delete->handle($model);

        return response()->noContent();
    }
}
