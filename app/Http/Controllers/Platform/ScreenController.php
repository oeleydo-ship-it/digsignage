<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Support\ScreenHealth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $thresholds = ScreenHealth::thresholds();

        $screens = Screen::query()
            ->with(['team:id,name,slug', 'currentChannel:id,name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Screen $screen) use ($thresholds) {
                $snapshot = ScreenHealth::snapshot($screen, $thresholds);

                return [
                    ...$snapshot,
                    'team' => [
                        'name' => $screen->team->name,
                        'slug' => $screen->team->slug,
                    ],
                ];
            });

        return Inertia::render('platform/screens/index', [
            'filters' => ['search' => $search],
            'screens' => $screens,
        ]);
    }
}
