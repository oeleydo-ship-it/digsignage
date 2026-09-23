<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function index(): Response
    {
        $storage = Media::query()
            ->selectRaw('team_id, coalesce(sum(file_size), 0) as bytes, count(*) as files')
            ->groupBy('team_id')
            ->pluck('bytes', 'team_id');
        $files = Media::query()
            ->selectRaw('team_id, count(*) as files')
            ->groupBy('team_id')
            ->pluck('files', 'team_id');

        $organizations = Team::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'plan_key', 'bandwidth_used_bytes'])
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'plan_name' => $team->plan_key->label(),
                'storage_bytes' => (int) ($storage[$team->id] ?? 0),
                'media_files' => (int) ($files[$team->id] ?? 0),
                'bandwidth_bytes' => (int) $team->bandwidth_used_bytes,
            ]);

        return Inertia::render('platform/usage/index', [
            'organizations' => $organizations,
            'totals' => [
                'storage_bytes' => $organizations->sum('storage_bytes'),
                'media_files' => $organizations->sum('media_files'),
                'bandwidth_bytes' => $organizations->sum('bandwidth_bytes'),
            ],
        ]);
    }
}
