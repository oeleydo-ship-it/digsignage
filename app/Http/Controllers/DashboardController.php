<?php

namespace App\Http\Controllers;

use App\Models\Screen;
use App\Models\TeamInvitation;
use App\Support\BillingCatalog;
use App\Support\ScreenHealth;
use App\Support\TeamQuota;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TeamQuota $quota): Response
    {
        $email = strtolower($request->user()->email);
        $team = $request->user()->currentTeam;

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $monitoring = [
            'counts' => [
                'healthy' => 0,
                'warning' => 0,
                'offline' => 0,
                'disabled' => 0,
            ],
            'screens' => [],
            'thresholds' => null,
        ];

        if ($team !== null && $request->user()->can('viewAny', Screen::class)) {
            $thresholds = ScreenHealth::thresholds($team);
            $screens = Screen::query()
                ->forTeam($team)
                ->with(['currentChannel:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Screen $screen) => ScreenHealth::snapshot($screen, $thresholds));

            $monitoring = [
                'counts' => [
                    'healthy' => $screens->where('status', 'online')->count(),
                    'warning' => $screens->where('status', 'warning')->count(),
                    'offline' => $screens->where('status', 'offline')->count(),
                    'disabled' => $screens->where('status', 'disabled')->count(),
                ],
                'screens' => $screens->take(8)->values(),
                'thresholds' => [
                    'healthy_seconds' => $thresholds->healthySeconds,
                    'warning_seconds' => $thresholds->warningSeconds,
                ],
            ];
        }

        $plan = null;

        if ($team !== null) {
            $catalog = BillingCatalog::plan($team->plan_key);
            $usage = $quota->usage($team);

            $plan = [
                'key' => $team->plan_key->value,
                'name' => $catalog['name'],
                'price_cents' => $catalog['price_cents'],
                'status_label' => $team->subscription_status->label(),
                'screens' => $usage['screens'],
                'screens_limit' => $usage['screens_limit'],
                'users' => $usage['users'],
                'users_limit' => $usage['users_limit'],
            ];
        }

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'monitoring' => $monitoring,
            'plan' => $plan,
        ]);
    }
}
