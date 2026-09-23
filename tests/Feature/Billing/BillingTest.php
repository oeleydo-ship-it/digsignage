<?php

namespace Tests\Feature\Billing;

use App\Billing\BillingGateway;
use App\Enums\ChannelType;
use App\Enums\PlanKey;
use App\Enums\PlaylistItemType;
use App\Enums\ScreenOrientation;
use App\Enums\SignageAlert;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_organizations_start_on_a_starter_trial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('teams.store'), ['name' => 'Retail Co'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', [
            'name' => 'Retail Co',
            'plan_key' => PlanKey::Starter->value,
            'subscription_status' => SubscriptionStatus::Trialing->value,
        ]);
    }

    public function test_owners_can_view_billing_and_members_cannot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('billing.index', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index')
                ->has('plans', 3)
                ->where('subscription.plan_key', PlanKey::Enterprise->value));

        $this->actingAs($member)
            ->get(route('billing.index', $team))
            ->assertForbidden();
    }

    public function test_billing_page_only_shows_the_current_teams_invoices(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        Invoice::factory()->create([
            'team_id' => $team->id,
            'number' => 'INV-OWN-1',
        ]);
        Invoice::factory()->create([
            'number' => 'INV-FOREIGN-1',
        ]);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('billing.index', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index')
                ->has('invoices', 1)
                ->where('invoices.0.number', 'INV-OWN-1'));
    }

    public function test_starter_plan_blocks_an_eleventh_screen(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        Screen::factory()->count(10)->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('screens.store', $team), [
                'name' => 'Overflow',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('plan');

        $this->assertSame(10, $team->screens()->count());
    }

    public function test_past_due_subscriptions_can_view_but_not_create_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill(['subscription_status' => SubscriptionStatus::PastDue])->save();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('screens.index', $team))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('screens.store', $team), [
                'name' => 'Lobby',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_canceled_access_continues_until_the_period_ends(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Canceled,
            'subscription_ends_at' => now()->addWeek(),
        ])->save();

        $this->actingAs($user)
            ->post(route('screens.store', $team), [
                'name' => 'Still Allowed',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('screens', [
            'team_id' => $team->id,
            'name' => 'Still Allowed',
        ]);
    }

    public function test_starter_plan_blocks_extra_invitations(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        for ($i = 0; $i < 4; $i++) {
            $member = User::factory()->create();
            $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        }

        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'sixth@example.com',
                'role' => TeamRole::Member->value,
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_storage_quota_is_enforced_on_upload(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        Media::factory()->create([
            'team_id' => $team->id,
            'file_size' => 25 * 1024 * 1024 * 1024,
        ]);

        $this->actingAs($user)
            ->post(route('media.store', $team), [
                'files' => [UploadedFile::fake()->image('too-much.png', 32, 32)],
            ])
            ->assertSessionHasErrors('files');
    }

    public function test_starter_plan_cannot_create_multi_zone_channels(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
        ])->save();

        $this->actingAs($user)
            ->post(route('channels.store', $team), [
                'name' => 'Wall',
                'type' => ChannelType::Advanced->value,
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_downgrade_keeps_existing_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        Screen::factory()->count(12)->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('billing.swap', $team), ['plan_key' => PlanKey::Starter->value])
            ->assertRedirect(route('billing.index', $team));

        $team->refresh();
        $this->assertSame(PlanKey::Starter, $team->plan_key);
        $this->assertSame(12, $team->screens()->count());
    }

    public function test_fake_checkout_activates_a_plan_and_records_a_discounted_invoice(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $redirect = $this->actingAs($user)
            ->post(route('billing.checkout', $team), [
                'plan_key' => PlanKey::Business->value,
                'coupon_code' => 'SAVE20',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get($redirect->headers->get('Location'))
            ->assertRedirect(route('billing.index', $team));

        $team->refresh();
        $this->assertSame(PlanKey::Business, $team->plan_key);
        $this->assertSame(SubscriptionStatus::Active, $team->subscription_status);
        $this->assertSame('SAVE20', $team->coupon_code);

        $invoice = Invoice::query()->where('team_id', $team->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(7920, $invoice->amount_cents);
    }

    public function test_cancel_and_coupon_endpoints_update_the_subscription(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('billing.coupon', $team), ['coupon_code' => 'LAUNCH50'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('billing.cancel', $team))
            ->assertRedirect();

        $team->refresh();
        $this->assertSame('LAUNCH50', $team->coupon_code);
        $this->assertSame(SubscriptionStatus::Canceled, $team->subscription_status);
        $this->assertTrue($team->subscription_ends_at?->isFuture());

        $this->actingAs($user)
            ->post(route('billing.resume', $team))
            ->assertRedirect();

        $this->assertSame(SubscriptionStatus::Active, $team->refresh()->subscription_status);
    }

    public function test_failed_payment_webhook_marks_the_organization_past_due(): void
    {
        config([
            'billing.driver' => 'stripe',
            'billing.stripe.secret' => 'sk_test_123',
            'billing.stripe.webhook_secret' => 'whsec_test',
        ]);
        $this->app->forgetInstance(BillingGateway::class);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_customer_id' => 'cus_failed'])->save();

        $payload = [
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_failed',
                    'customer' => 'cus_failed',
                    'amount_due' => 2900,
                    'currency' => 'usd',
                    'status' => 'open',
                    'hosted_invoice_url' => 'https://invoice.stripe.test/in_failed',
                ],
            ],
        ];
        $raw = json_encode($payload);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_test');

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ],
            $raw,
        )->assertOk();

        $this->assertSame(SubscriptionStatus::PastDue, $team->refresh()->subscription_status);
        $this->assertDatabaseHas('in_app_notifications', [
            'team_id' => $team->id,
            'event' => SignageAlert::SubscriptionIssue->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'stripe_id' => 'in_failed',
            'status' => 'open',
        ]);
    }

    public function test_player_still_receives_media_when_bandwidth_is_exhausted(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('team/clip.bin', 'clip-bytes');

        $user = User::factory()->create();
        $plain = 'player-device-token';
        $team = $user->currentTeam;
        $team->forceFill([
            'plan_key' => PlanKey::Starter,
            'subscription_status' => SubscriptionStatus::Active,
            'bandwidth_used_bytes' => 50 * 1024 * 1024 * 1024,
        ])->save();

        $screen = Screen::factory()->paired()->create([
            'team_id' => $team->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);
        $playlist = Playlist::factory()->published()->create(['team_id' => $team->id]);
        $media = Media::factory()->create([
            'team_id' => $team->id,
            'storage_path' => 'team/clip.bin',
            'checksum' => hash('sha256', 'clip-bytes'),
            'file_size' => 10,
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $team->id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Media,
            'media_id' => $media->id,
            'url' => null,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $team->id,
            'playlist_id' => $playlist->id,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $this->withToken($plain)
            ->get('/api/player/v1/assets/media/'.$media->id)
            ->assertOk();

        $this->assertSame((50 * 1024 * 1024 * 1024) + 10, $team->refresh()->bandwidth_used_bytes);
        $this->assertDatabaseHas('in_app_notifications', [
            'team_id' => $team->id,
            'event' => SignageAlert::SubscriptionIssue->value,
        ]);
    }
}
