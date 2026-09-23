<?php

namespace App\Support;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Enums\SignageAlert;
use App\Enums\SubscriptionStatus;
use App\Models\Media;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TeamQuota
{
    /**
     * Usage summary for dashboard and billing displays.
     *
     * Cached briefly per team; enforcement checks below always query live.
     * Invalidated on screen, media, membership, and invitation writes.
     *
     * @return array{
     *     screens: int,
     *     screens_limit: int|null,
     *     users: int,
     *     users_limit: int|null,
     *     storage_bytes: int,
     *     storage_limit_bytes: int|null,
     *     bandwidth_bytes: int,
     *     bandwidth_limit_bytes: int|null,
     *     advanced: bool,
     *     features: array<string, bool>
     * }
     */
    public function usage(Team $team): array
    {
        return Cache::remember(
            'team-usage:'.$team->id,
            60,
            fn () => $this->resolveUsage($team),
        );
    }

    public static function forgetUsageCache(int $teamId): void
    {
        try {
            Cache::forget('team-usage:'.$teamId);
        } catch (\Throwable) {
            // Cache store unavailable.
        }
    }

    /**
     * @return array{
     *     screens: int,
     *     screens_limit: int|null,
     *     users: int,
     *     users_limit: int|null,
     *     storage_bytes: int,
     *     storage_limit_bytes: int|null,
     *     bandwidth_bytes: int,
     *     bandwidth_limit_bytes: int|null,
     *     advanced: bool,
     *     features: array<string, bool>
     * }
     */
    protected function resolveUsage(Team $team): array
    {
        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);

        return [
            'screens' => $team->screens()->count(),
            'screens_limit' => $plan['screens'],
            'users' => $this->userSeats($team),
            'users_limit' => $plan['users'],
            'storage_bytes' => (int) Media::query()->forTeam($team)->sum('file_size'),
            'storage_limit_bytes' => BillingCatalog::bytesFromGigabytes($plan['storage_gb']),
            'bandwidth_bytes' => (int) $team->bandwidth_used_bytes,
            'bandwidth_limit_bytes' => BillingCatalog::bytesFromGigabytes($plan['bandwidth_gb']),
            'advanced' => $plan['advanced'],
            'features' => $plan['features'],
        ];
    }

    public function allowsMutations(Team $team): bool
    {
        if ($team->suspended_at !== null) {
            return false;
        }

        $status = $team->subscription_status ?? SubscriptionStatus::Trialing;

        if ($status === SubscriptionStatus::Trialing) {
            return $team->trial_ends_at === null || $team->trial_ends_at->isFuture();
        }

        if ($status === SubscriptionStatus::Active) {
            return true;
        }

        return $status === SubscriptionStatus::Canceled
            && $team->subscription_ends_at !== null
            && $team->subscription_ends_at->isFuture();
    }

    public function assertMutationsAllowed(Team $team): void
    {
        if (! $this->allowsMutations($team)) {
            throw ValidationException::withMessages([
                'plan' => $team->suspended_at !== null
                    ? __('This organization is suspended. Contact support.')
                    : __('Your subscription is not active. Update billing to add screens, users, or media.'),
            ]);
        }
    }

    public function assertCanAddScreen(Team $team): void
    {
        $this->assertMutationsAllowed($team);

        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);

        if ($plan['screens'] !== null && $team->screens()->count() >= $plan['screens']) {
            throw ValidationException::withMessages([
                'plan' => __('This plan allows :limit screens. Upgrade to add more.', ['limit' => $plan['screens']]),
            ]);
        }
    }

    public function assertCanAddUser(Team $team): void
    {
        $this->assertMutationsAllowed($team);

        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);

        if ($plan['users'] !== null && $this->userSeats($team) >= $plan['users']) {
            throw ValidationException::withMessages([
                'plan' => __('This plan allows :limit users. Upgrade to invite more people.', ['limit' => $plan['users']]),
            ]);
        }
    }

    public function assertCanAcceptInvitation(Team $team, int $userId): void
    {
        if ($team->memberships()->where('user_id', $userId)->exists()) {
            return;
        }

        $this->assertMutationsAllowed($team);

        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);

        if ($plan['users'] !== null && $team->memberships()->count() >= $plan['users']) {
            throw ValidationException::withMessages([
                'plan' => __('This plan allows :limit users. Upgrade to add more people.', ['limit' => $plan['users']]),
            ]);
        }
    }

    public function assertCanStoreBytes(Team $team, int $incomingBytes): void
    {
        $this->assertMutationsAllowed($team);

        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);
        $limit = BillingCatalog::bytesFromGigabytes($plan['storage_gb']);

        if ($limit === null) {
            return;
        }

        $used = (int) Media::query()->forTeam($team)->sum('file_size');

        if ($used + $incomingBytes > $limit) {
            throw ValidationException::withMessages([
                'files' => __('This upload would exceed the :limit GB storage included in your plan.', [
                    'limit' => $plan['storage_gb'],
                ]),
            ]);
        }
    }

    public function assertCanUseAdvanced(Team $team): void
    {
        $this->assertCanUseFeature($team, PlanFeature::MultiZone, 'type', __('Multi-zone channels are not included in this plan.'));
    }

    public function assertCanUseFeature(Team $team, PlanFeature $feature, string $field = 'plan', ?string $message = null): void
    {
        $this->assertMutationsAllowed($team);

        if (BillingCatalog::allows($team->plan_key ?? PlanKey::Starter, $feature)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $message ?? __(':feature is not included in this plan. Upgrade or ask the platform administrator to enable it.', [
                'feature' => $feature->label(),
            ]),
        ]);
    }

    public function allowsFeature(Team $team, PlanFeature $feature): bool
    {
        return BillingCatalog::allows($team->plan_key ?? PlanKey::Starter, $feature);
    }

    public function recordBandwidth(Team $team, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        Team::query()->whereKey($team->id)->increment('bandwidth_used_bytes', $bytes);
        $team->refresh();

        $plan = BillingCatalog::plan($team->plan_key ?? PlanKey::Starter);
        $limit = BillingCatalog::bytesFromGigabytes($plan['bandwidth_gb']);

        if ($limit === null || (int) $team->bandwidth_used_bytes <= $limit) {
            return;
        }

        app(DispatchSignageAlert::class)->queue(
            $team,
            SignageAlert::SubscriptionIssue,
            __('Bandwidth limit reached'),
            __('Players are still serving content, but this organization has used the bandwidth included in the current plan.'),
            ['bandwidth_used_bytes' => $team->bandwidth_used_bytes],
            'bandwidth-limit-'.$team->id,
            86400,
        );
    }

    protected function userSeats(Team $team): int
    {
        $members = $team->memberships()->count();
        $pending = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereNull('accepted_at')
            ->count();

        return $members + $pending;
    }
}
