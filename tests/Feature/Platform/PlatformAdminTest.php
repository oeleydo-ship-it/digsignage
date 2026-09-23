<?php

namespace Tests\Feature\Platform;

use App\Enums\PlanFeature;
use App\Enums\PlatformAuditAction;
use App\Enums\ScreenOrientation;
use App\Enums\TeamRole;
use App\Models\Announcement;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_open_the_platform_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }

    public function test_platform_admins_can_view_the_console(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/dashboard')
                ->has('metrics.organizations')
                ->where('isPlatformAdmin', true));
    }

    public function test_impersonation_is_logged_shown_and_can_be_stopped(): void
    {
        $admin = User::factory()->platformAdmin()->create(['name' => 'Ada Admin']);
        $member = User::factory()->create(['name' => 'Sam Support']);

        $this->actingAs($admin)
            ->post(route('platform.users.impersonate', $member))
            ->assertRedirect();

        $this->assertAuthenticatedAs($member);
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->withoutVite()
            ->get(route('dashboard', $member->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isPlatformAdmin', false)
                ->where('impersonation.actor_name', 'Ada Admin')
                ->where('impersonation.target_name', 'Sam Support'));

        $this->assertDatabaseHas('platform_audits', [
            'actor_id' => $admin->id,
            'action' => PlatformAuditAction::ImpersonationStarted->value,
            'resource_id' => $member->id,
        ]);

        $this->post(route('platform.impersonation.stop'))->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_impersonation_blocks_deleting_an_organization(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $owner->switchTeam($team);

        $this->actingAs($admin)->post(route('platform.users.impersonate', $owner));

        $this->delete(route('teams.destroy', $team), ['name' => $team->name])->assertForbidden();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'deleted_at' => null]);
    }

    public function test_platform_admins_cannot_be_impersonated(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $other = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.users.impersonate', $other))
            ->assertForbidden();
    }

    public function test_suspended_organizations_cannot_add_screens(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($admin)
            ->post(route('platform.organizations.suspend', $team))
            ->assertRedirect();

        $this->assertNotNull($team->refresh()->suspended_at);

        $this->actingAs($owner->refresh())
            ->post(route('screens.store', $team), [
                'name' => 'Lobby',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_published_announcements_appear_on_the_organization_dashboard(): void
    {
        $user = User::factory()->create();
        Announcement::factory()->create([
            'title' => 'Storage maintenance',
            'body' => 'Uploads pause at midnight.',
            'published_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('dashboard', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('announcements.0.title', 'Storage maintenance'));
    }

    public function test_feature_flags_can_be_toggled(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $flag = FeatureFlag::query()->where('key', 'maintenance_banner')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('platform.feature-flags.update', $flag), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue($flag->refresh()->enabled);
        $this->assertDatabaseHas('platform_audits', [
            'action' => PlatformAuditAction::FeatureFlagUpdated->value,
            'resource_id' => $flag->id,
        ]);
    }

    public function test_platform_console_is_blocked_during_impersonation(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('platform.users.impersonate', $member));

        $this->get(route('platform.dashboard'))->assertForbidden();
    }

    public function test_platform_admins_can_open_every_console_section(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->withoutVite();

        foreach ([
            'platform.dashboard',
            'platform.organizations.index',
            'platform.users.index',
            'platform.subscriptions.index',
            'platform.plans.index',
            'platform.screens.index',
            'platform.usage.index',
            'platform.health.index',
            'platform.jobs.index',
            'platform.templates.index',
            'platform.feature-flags.index',
            'platform.announcements.index',
            'platform.audits.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_platform_admins_can_edit_plan_quotas_and_feature_gates(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->forceFill(['plan_key' => 'starter'])->save();

        Screen::factory()->count(2)->create(['team_id' => $team->id]);

        $this->actingAs($admin)
            ->patch(route('platform.plans.update', 'starter'), [
                'name' => 'Starter Plus',
                'screens' => 2,
                'storage_gb' => 10,
                'users' => 3,
                'bandwidth_gb' => 20,
                'price_usd' => 19,
                'features' => [
                    'multi_zone' => false,
                    'emergencies' => false,
                    'partner_api' => true,
                    'webhooks' => true,
                    'analytics' => true,
                    'proof_of_play' => true,
                    'designer' => true,
                    'schedules' => true,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'key' => 'starter',
            'name' => 'Starter Plus',
            'screens' => 2,
            'price_cents' => 1900,
        ]);

        $this->actingAs($owner->refresh())
            ->post(route('screens.store', $team), [
                'name' => 'Overflow',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('plan');

        $plan = Plan::query()->findOrFail('starter');
        $this->assertSame(1900, $plan->price_cents);
        $this->assertFalse($plan->allows(PlanFeature::MultiZone));
        $this->assertTrue($plan->allows(PlanFeature::PartnerApi));
    }

    public function test_regular_users_cannot_manage_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.plans.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('platform.plans.store'), [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_usd' => 49,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('platform.plans.update', 'starter'), [
                'name' => 'Hacked',
                'price_usd' => 1,
            ])
            ->assertForbidden();
    }

    public function test_platform_admins_can_create_a_plan_and_see_it_on_the_index(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('platform.plans.index'))
            ->post(route('platform.plans.store'), [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_usd' => 49,
                'screens' => 40,
                'storage_gb' => 80,
                'users' => 15,
                'bandwidth_gb' => 200,
                'features' => [
                    'multi_zone' => true,
                    'emergencies' => true,
                    'partner_api' => false,
                    'webhooks' => true,
                    'analytics' => true,
                    'proof_of_play' => true,
                    'designer' => true,
                    'schedules' => true,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', [
            'key' => 'pro',
            'name' => 'Pro',
            'price_cents' => 4900,
            'screens' => 40,
            'users' => 15,
        ]);

        $plan = Plan::query()->findOrFail('pro');
        $this->assertTrue($plan->allows(PlanFeature::MultiZone));
        $this->assertFalse($plan->allows(PlanFeature::PartnerApi));

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/plans/index')
                ->where(
                    'plans',
                    fn ($plans) => collect($plans)->contains(
                        fn ($item) => data_get($item, 'key') === 'pro'
                            && data_get($item, 'name') === 'Pro'
                            && (float) data_get($item, 'price_usd') === 49.0
                            && (int) data_get($item, 'screens') === 40,
                    ),
                ));
    }
}
