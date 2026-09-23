<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Template;
use App\Support\PartnerApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Template::class);

        $user = $request->user();
        $team = $this->team($request);

        $templates = Template::query()
            ->visibleTo($user, $team)
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Template $template) => PartnerApi::template($template));

        return PartnerApi::paginated($templates);
    }

    public function show(Request $request, int $template): JsonResponse
    {
        Gate::authorize('viewAny', Template::class);

        $model = Template::query()
            ->visibleTo($request->user(), $this->team($request))
            ->whereKey($template)
            ->first();

        abort_if($model === null, 404);

        return PartnerApi::item(PartnerApi::template($model));
    }
}
