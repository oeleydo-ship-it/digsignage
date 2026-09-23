<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/feature-flags/index', [
            'flags' => FeatureFlag::query()->orderBy('name')->get()->map(fn (FeatureFlag $flag) => [
                'id' => $flag->id,
                'key' => $flag->key,
                'name' => $flag->name,
                'description' => $flag->description,
                'enabled' => $flag->enabled,
            ]),
        ]);
    }

    public function update(Request $request, FeatureFlag $featureFlag, RecordPlatformAudit $audit): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $before = ['enabled' => $featureFlag->enabled];
        $featureFlag->update(['enabled' => $enabled]);

        $audit->handle(
            PlatformAuditAction::FeatureFlagUpdated,
            $request->user(),
            'feature_flag',
            $featureFlag->id,
            $before,
            ['enabled' => $enabled, 'key' => $featureFlag->key],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature flag updated.')]);

        return back();
    }
}
