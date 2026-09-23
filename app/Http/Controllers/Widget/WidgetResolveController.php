<?php

namespace App\Http\Controllers\Widget;

use App\Actions\Widget\ResolveWidgetData;
use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\Template;
use App\Widgets\WidgetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WidgetResolveController extends Controller
{
    /**
     * Resolve live widget data for the designer and preview canvases.
     */
    public function __invoke(
        Request $request,
        WidgetRegistry $registry,
        ResolveWidgetData $resolveWidgetData,
    ): JsonResponse {
        abort_unless(
            Gate::allows('viewAny', Design::class) || Gate::allows('viewAny', Template::class),
            403,
        );

        $validated = $request->validate([
            'timezone' => ['nullable', 'string', 'max:64'],
            'elements' => ['required', 'array', 'max:80'],
            'elements.*.id' => ['required', 'string', 'max:64'],
            'elements.*.type' => ['required', 'string', 'max:64'],
            'elements.*.props' => ['nullable', 'array'],
        ]);

        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);

        $timezone = is_string($validated['timezone'] ?? null) && $validated['timezone'] !== ''
            ? $validated['timezone']
            : 'UTC';

        $widgets = [];

        foreach ($validated['elements'] as $element) {
            $key = $registry->canonicalKey((string) $element['type']);

            if (! $registry->has($key)) {
                continue;
            }

            $widgets[$element['id']] = $resolveWidgetData->handle(
                $team,
                $key,
                is_array($element['props'] ?? null) ? $element['props'] : [],
                $timezone,
            );
        }

        return response()->json(['widgets' => $widgets]);
    }
}
