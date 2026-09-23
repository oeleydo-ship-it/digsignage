<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    public function stop(Request $request, RecordPlatformAudit $audit): RedirectResponse
    {
        $actor = Impersonation::actor($request);

        abort_unless($actor !== null, 403);

        $target = $request->user();

        $audit->handle(
            PlatformAuditAction::ImpersonationStopped,
            $actor,
            'user',
            $target?->id,
            null,
            ['email' => $target?->email],
        );

        Auth::login($actor);
        $request->session()->forget(Impersonation::SESSION_KEY);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Impersonation ended.')]);

        return to_route('platform.dashboard');
    }
}
