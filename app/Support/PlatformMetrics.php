<?php

namespace App\Support;

use App\Enums\PlanKey;
use App\Enums\ScreenStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Media;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformMetrics
{
    /**
     * Platform dashboard aggregates, cached briefly — these run fleet-wide
     * counts and sums and only platform admins see them.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        /** @var array<string, mixed> $metrics */
        $metrics = Cache::remember('platform:metrics:dashboard', 60, fn () => $this->resolveDashboard());

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveDashboard(): array
    {
        $thresholds = ScreenHealth::thresholds();
        $healthyCutoff = now()->subSeconds($thresholds->healthySeconds);

        $activeSubscriptions = Team::query()
            ->where('subscription_status', SubscriptionStatus::Active)
            ->whereNull('suspended_at')
            ->count();

        $mrrCents = 0;

        foreach (Team::query()
            ->where('subscription_status', SubscriptionStatus::Active)
            ->whereNull('suspended_at')
            ->get(['plan_key']) as $team) {
            $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);
            $mrrCents += $plan['price_cents'] ?? 0;
        }

        $online = Screen::query()
            ->where('status', '!=', ScreenStatus::Disabled)
            ->where('last_seen_at', '>=', $healthyCutoff)
            ->count();
        $screens = Screen::query()->count();

        return [
            'organizations' => Team::query()->count(),
            'active_subscriptions' => $activeSubscriptions,
            'mrr_cents' => $mrrCents,
            'screens' => $screens,
            'online_screens' => $online,
            'offline_screens' => max(0, $screens - $online - Screen::query()->where('status', ScreenStatus::Disabled)->count()),
            'storage_bytes' => (int) Media::query()->sum('file_size'),
            'bandwidth_bytes' => (int) Team::query()->sum('bandwidth_used_bytes'),
            'queue' => [
                'connection' => (string) config('queue.default'),
                'pending' => (int) DB::table('jobs')->count(),
                'failed' => (int) DB::table('failed_jobs')->count(),
            ],
            'users' => User::query()->count(),
        ];
    }
}
