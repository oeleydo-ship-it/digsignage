<?php

namespace Tests\Feature\Billing;

use App\Actions\Queue\EvaluateApproachingQueueAppointments;
use App\Actions\Queue\IssueQueueTicket;
use App\Actions\Widget\ResolveWidgetData;
use App\Enums\ApiScope;
use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Enums\QueueAppointmentStatus;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Enums\QueueTicketSource;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEvent;
use App\Models\ApiToken;
use App\Models\Plan;
use App\Models\QueueAppointment;
use App\Models\QueueKiosk;
use App\Models\QueueNotificationRule;
use App\Models\QueueService;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\BillingCatalog;
use App\Widgets\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueuePlanControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetPlanCaches();
    }

    protected function tearDown(): void
    {
        $this->forgetPlanCaches();
        parent::tearDown();
    }

    public function test_plan_without_queue_entitlement_hides_and_blocks_the_module(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();
        $kiosk = QueueKiosk::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->get(route('queue.overview', $team))
            ->assertForbidden();

        $this->get(route('queue.virtual.show', $team))
            ->assertForbidden();

        $this->get(route('queue.kiosk.serve', $kiosk))
            ->assertForbidden();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('dashboard', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('queuePermissions', null));

        $widgets = app(WidgetRegistry::class)->toArrayForTeam($team);
        $this->assertFalse(collect($widgets)->contains(
            fn (array $widget) => str_starts_with($widget['key'], 'queue_'),
        ));

        $resolved = app(ResolveWidgetData::class)->handle($team, 'queue_now_serving', []);
        $this->assertSame('Queue Management is not included in this plan.', $resolved['data']['error']);
    }

    public function test_custom_feature_map_enables_queue_without_checking_the_plan_name(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();
        $features = BillingCatalog::defaults(PlanKey::Starter)['features'];
        $features[PlanFeature::QueueManagement->value] = true;
        $features[PlanFeature::Analytics->value] = false;
        $this->storePlan(PlanKey::Starter, 'Any configurable plan name', $features);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('queue.overview', $team))
            ->assertOk();

        $this->get(route('queue.reports', $team))
            ->assertForbidden();

        $this->assertTrue(collect(app(WidgetRegistry::class)->toArrayForTeam($team))->contains(
            fn (array $widget) => $widget['key'] === 'queue_now_serving',
        ));

        $features[PlanFeature::Analytics->value] = true;
        $this->storePlan(PlanKey::Starter, 'Renamed again', $features);

        $this->get(route('queue.reports', $team))
            ->assertOk();
    }

    public function test_queue_partner_api_requires_both_api_and_queue_entitlements(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();
        [, $plain] = ApiToken::issue($user, $team, 'Queue reader', [ApiScope::QueueRead->value]);

        $this->withToken($plain)
            ->getJson('/api/v1/queue/services')
            ->assertForbidden()
            ->assertJsonPath('message', 'Queue Management is not included in this plan.');
    }

    public function test_queue_webhooks_stop_when_the_webhooks_entitlement_is_disabled(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $features = BillingCatalog::defaults(PlanKey::Enterprise)['features'];
        $features[PlanFeature::QueueManagement->value] = true;
        $features[PlanFeature::Webhooks->value] = false;
        $this->storePlan(PlanKey::Enterprise, 'Enterprise without webhooks', $features);
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        WebhookEndpoint::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'events' => [WebhookEvent::TicketCreated->value],
        ]);

        app(IssueQueueTicket::class)->handle($team, $service, [
            'source' => QueueTicketSource::Staff,
        ], $user);

        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_scheduled_queue_work_skips_organizations_without_the_module(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        QueueAppointment::factory()->create([
            'team_id' => $team->id,
            'queue_service_id' => $service->id,
            'scheduled_at' => now()->addMinutes(30),
            'status' => QueueAppointmentStatus::Scheduled,
        ]);
        QueueNotificationRule::query()->create([
            'team_id' => $team->id,
            'event' => QueueNotificationEvent::AppointmentApproaching,
            'channel' => QueueNotificationChannel::Email,
            'is_enabled' => true,
            'minutes_before' => 60,
        ]);

        $this->assertSame(0, app(EvaluateApproachingQueueAppointments::class)->handle());
        $this->assertDatabaseCount('queue_notification_deliveries', 0);
    }

    /**
     * @param  array<string, bool>  $features
     */
    protected function storePlan(PlanKey $key, string $name, array $features): void
    {
        $defaults = BillingCatalog::defaults($key);

        Plan::query()->updateOrCreate(['key' => $key->value], [
            'name' => $name,
            'screens' => $defaults['screens'],
            'storage_gb' => $defaults['storage_gb'],
            'users' => $defaults['users'],
            'bandwidth_gb' => $defaults['bandwidth_gb'],
            'price_cents' => $defaults['price_cents'],
            'stripe_price_id' => $defaults['stripe_price_id'],
            'features' => $features,
        ]);
        BillingCatalog::forgetPlanCache($key->value);
    }

    protected function forgetPlanCaches(): void
    {
        foreach (PlanKey::cases() as $key) {
            BillingCatalog::forgetPlanCache($key->value);
        }
    }
}
