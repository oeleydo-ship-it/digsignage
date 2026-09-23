<?php

namespace Tests\Feature\Performance;

use App\Actions\Analytics\QueryAnalytics;
use App\Actions\Signage\SyncScreenGroupScreens;
use App\Enums\PlanKey;
use App\Enums\SignageAlert;
use App\Models\Announcement;
use App\Models\Emergency;
use App\Models\InAppNotification;
use App\Models\Plan;
use App\Models\PlayerPlaybackEvent;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Models\User;
use App\Support\AnalyticsFilters;
use App\Support\BillingCatalog;
use App\Support\PlayerManifestCache;
use App\Support\ScopedCacheVersion;
use App\Support\TeamQuota;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_lookups_are_cached_and_invalidated_on_write(): void
    {
        // Warm the cache with the seeded (or default) starter plan.
        $original = BillingCatalog::plan(PlanKey::Starter)['name'];

        $plan = Plan::query()->findOrFail(PlanKey::Starter->value);
        $plan->forceFill(['name' => 'Renamed Starter'])->save();

        // The model event must have forgotten the cached lookup.
        $this->assertSame('Renamed Starter', BillingCatalog::plan(PlanKey::Starter)['name']);

        $plan->forceFill(['name' => $original])->save();

        $this->assertSame($original, BillingCatalog::plan(PlanKey::Starter)['name']);
    }

    public function test_team_usage_is_cached_and_invalidated_on_screen_changes(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $quota = app(TeamQuota::class);

        $this->assertSame(0, $quota->usage($team)['screens']);

        Screen::factory()->create(['team_id' => $team->id]);

        $this->assertSame(1, $quota->usage($team)['screens']);
    }

    public function test_unread_notification_badge_is_cached_and_invalidated(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertInertia(fn ($page) => $page->where('unreadNotifications', 0));

        InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'event' => SignageAlert::ScreenOffline,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
    }

    public function test_published_announcements_are_cached_and_invalidated(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertInertia(fn ($page) => $page->has('announcements', 0));

        Announcement::factory()->create([
            'title' => 'Scheduled maintenance',
            'published_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertInertia(fn ($page) => $page
                ->has('announcements', 1)
                ->where('announcements.0.title', 'Scheduled maintenance'));
    }

    public function test_analytics_report_is_cached_and_invalidated_by_new_telemetry(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $filters = $this->analyticsFilters($team);
        $query = app(QueryAnalytics::class);

        $this->assertSame(0, $query->handle($filters)['content']['plays']);

        PlayerPlaybackEvent::factory()->create([
            'team_id' => $team->id,
            'screen_id' => $screen->id,
            'played_at' => now(),
            'started_at' => now(),
        ]);

        $this->assertSame(1, $query->handle($this->analyticsFilters($team))['content']['plays']);
    }

    public function test_emergencies_index_paginates(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        Emergency::factory()->count(20)->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->get(route('emergencies.index', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('emergencies/index')
                ->has('emergencies.data', 15)
                ->where('emergencies.total', 20)
                ->where('emergencies.last_page', 2));
    }

    public function test_manifest_cache_bumps_when_group_membership_changes(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);
        $group = ScreenGroup::factory()->create(['team_id' => $team->id]);

        $before = ScopedCacheVersion::get(PlayerManifestCache::SCOPE, $team->id);

        app(SyncScreenGroupScreens::class)->handle($team, $group, [$screen->id]);

        $after = ScopedCacheVersion::get(PlayerManifestCache::SCOPE, $team->id);

        $this->assertGreaterThan($before, $after);
    }

    protected function analyticsFilters(Team $team): AnalyticsFilters
    {
        $from = CarbonImmutable::now()->subDays(6)->startOfDay();
        $until = CarbonImmutable::now()->endOfDay();

        return new AnalyticsFilters($team, [
            'screen_id' => null,
            'location_id' => null,
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
        ], $from, $until);
    }
}
