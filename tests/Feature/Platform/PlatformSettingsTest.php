<?php

namespace Tests\Feature\Platform;

use App\Enums\PlanKey;
use App\Enums\PlatformAuditAction;
use App\Models\Plan;
use App\Models\PlatformAudit;
use App\Models\PlatformSetting;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\BillingCatalog;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_open_general_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.settings.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->put(route('platform.settings.general'), ['name' => 'Hijack', 'allow_registration' => true])
            ->assertForbidden();
    }

    public function test_the_page_shows_settings_and_the_setup_checklist(): void
    {
        config(['billing.driver' => 'fake', 'mail.default' => 'log']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/settings/index')
                ->where('general.allow_registration', true)
                ->where('payments.driver', 'fake')
                ->where('payments.webhook_url', route('stripe.webhook'))
                ->where('mail.mailer', 'log')
                ->where('checklist', fn ($items) => collect($items)->firstWhere('key', 'mail')['done'] === false
                    && collect($items)->firstWhere('key', 'payments')['done'] === false));
    }

    public function test_the_platform_name_is_saved_and_used_everywhere(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('platform.settings.general'), [
                'name' => 'ScreenCloud Pro',
                'support_email' => 'help@example.com',
                'allow_registration' => true,
                'terms_url' => 'https://example.com/terms',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ScreenCloud Pro', config('app.name'));

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('name', 'ScreenCloud Pro')
                ->where('branding.support_email', 'help@example.com')
                ->where('branding.terms_url', 'https://example.com/terms'));

        $this->assertTrue(PlatformAudit::query()->where('action', PlatformAuditAction::PlatformSettingsUpdated)->exists());
    }

    public function test_saving_returns_to_the_settings_page_even_without_a_useful_referrer(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $headers = ['referer' => url('/')];

        // Success and validation failure both land back on the page, never on
        // the action-only URL (which would 405 on GET).
        $this->actingAs($admin)
            ->put(route('platform.settings.general'), ['name' => 'Acme', 'allow_registration' => true], $headers)
            ->assertRedirect(route('platform.settings.index'));

        $this->actingAs($admin)
            ->put(route('platform.settings.general'), ['name' => '', 'allow_registration' => true], $headers)
            ->assertRedirect(route('platform.settings.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_stripe_secrets_are_encrypted_write_only_and_kept_when_left_blank(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('platform.settings.payments'), $this->payments([
                'stripe_secret' => 'sk_test_abc123secret',
                'stripe_webhook_secret' => 'whsec_hook123',
            ]))
            ->assertSessionHasNoErrors();

        $stored = PlatformSetting::query()->findOrFail('payments.stripe_secret');
        $this->assertTrue($stored->encrypted);
        $this->assertStringNotContainsString('sk_test_abc123secret', (string) $stored->value);
        $this->assertSame('sk_test_abc123secret', config('billing.stripe.secret'));
        $this->assertSame('stripe', config('billing.driver'));

        // Saving again with blank secrets keeps them.
        $this->actingAs($admin)
            ->put(route('platform.settings.payments'), $this->payments(['currency' => 'EUR']))
            ->assertSessionHasNoErrors();

        $this->assertSame('sk_test_abc123secret', app(PlatformSettings::class)->get('payments.stripe_secret'));
        $this->assertSame('eur', config('billing.currency'));

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.has_secret', true)
                ->where('payments.secret_hint', '••••cret')
                ->missing('payments.stripe_secret'));

        $this->assertStringNotContainsString(
            'sk_test_abc123secret',
            (string) $this->actingAs($admin)->withoutVite()->get(route('platform.settings.index'))->getContent(),
        );
    }

    public function test_stripe_needs_a_secret_and_matching_key_modes(): void
    {
        config(['billing.stripe.secret' => null]);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.payments'), $this->payments(['stripe_key' => 'pk_live_abc']))
            ->assertSessionHasErrors('stripe_secret');

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.payments'), $this->payments([
                'stripe_key' => 'pk_live_abc',
                'stripe_secret' => 'sk_test_abc',
            ]))
            ->assertSessionHasErrors('stripe_key');
    }

    public function test_plans_are_wired_to_stripe_prices(): void
    {
        Plan::query()->updateOrCreate(['key' => PlanKey::Starter->value], ['name' => 'Starter', 'price_cents' => 2900]);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.payments'), $this->payments([
                'stripe_secret' => 'sk_test_abc',
                'prices' => [PlanKey::Starter->value => 'price_starter123'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('price_starter123', Plan::query()->where('key', PlanKey::Starter->value)->value('stripe_price_id'));
        $this->assertSame('price_starter123', BillingCatalog::plan(PlanKey::Starter)['stripe_price_id']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.payments'), $this->payments([
                'prices' => [PlanKey::Starter->value => 'not-a-price'],
            ]))
            ->assertSessionHasErrors('prices.starter');
    }

    public function test_verify_checks_the_stripe_account_and_each_plan_price(): void
    {
        Plan::query()->updateOrCreate(['key' => PlanKey::Starter->value], ['name' => 'Starter', 'price_cents' => 2900, 'stripe_price_id' => 'price_ok']);
        Plan::query()->updateOrCreate(['key' => PlanKey::Business->value], ['name' => 'Business', 'price_cents' => 9900, 'stripe_price_id' => 'price_wrong']);
        config(['billing.stripe.secret' => 'sk_test_abc', 'billing.currency' => 'usd']);

        Http::fake([
            'api.stripe.com/v1/account' => Http::response(['id' => 'acct_1', 'settings' => ['dashboard' => ['display_name' => 'Acme Signage']]]),
            'api.stripe.com/v1/prices/price_ok' => Http::response(['active' => true, 'currency' => 'usd', 'unit_amount' => 2900, 'type' => 'recurring']),
            'api.stripe.com/v1/prices/price_wrong' => Http::response(['active' => true, 'currency' => 'usd', 'unit_amount' => 5000, 'type' => 'recurring']),
        ]);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.settings.payments.test'))
            ->assertRedirect()
            ->assertInertiaFlash('paymentCheck.account', 'Acme Signage')
            ->assertInertiaFlash('paymentCheck.plans.starter.ok', true)
            ->assertInertiaFlash('paymentCheck.plans.business.ok', false)
            ->assertInertiaFlash('toast.type', 'warning');
    }

    public function test_smtp_settings_configure_the_mailer(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.mail'), [
                'mailer' => 'smtp',
                'host' => 'smtp.example.com',
                'port' => 465,
                'username' => 'mailer@example.com',
                'password' => 'app-password',
                'encryption' => 'ssl',
                'from_address' => 'no-reply@example.com',
                'from_name' => 'Signage',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('app-password', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
        $this->assertTrue(PlatformSetting::query()->findOrFail('mail.password')->encrypted);
    }

    public function test_smtp_needs_a_host(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create())
            ->put(route('platform.settings.mail'), [
                'mailer' => 'smtp',
                'encryption' => 'tls',
                'from_address' => 'no-reply@example.com',
                'from_name' => 'Signage',
            ])
            ->assertSessionHasErrors(['host', 'port']);
    }

    public function test_a_test_email_can_be_sent(): void
    {
        config(['mail.default' => 'array']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.settings.mail.test'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertCount(1, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_logo_and_favicon_uploads_brand_the_platform(): void
    {
        Storage::fake('public');
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.settings.branding'), [
                'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
                'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ])
            ->assertSessionHasNoErrors();

        $logo = (string) app(PlatformSettings::class)->get('branding.logo_path');
        Storage::disk('public')->assertExists($logo);

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.logo_url', Storage::disk('public')->url($logo)));

        $this->actingAs($admin)
            ->post(route('platform.settings.branding'), ['remove_logo' => true])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($logo);
        $this->assertNull(app(PlatformSettings::class)->get('branding.logo_path'));
    }

    public function test_logo_colour_and_name_visibility_are_saved_and_shared(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.logo_tone', 'original')
                ->where('branding.show_name', true));

        $this->actingAs($admin)
            ->post(route('platform.settings.branding'), ['logo_tone' => 'white', 'show_name' => false])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.settings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.logo_tone', 'white')
                ->where('branding.show_name', false)
                ->where('brand.logo_tone', 'white'));

        $this->actingAs($admin)
            ->post(route('platform.settings.branding'), ['logo_tone' => 'rainbow'])
            ->assertSessionHasErrors('logo_tone');
    }

    public function test_svg_logos_are_refused(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.settings.branding'), [
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_closing_sign_ups_blocks_registration_except_for_invitations(): void
    {
        app(PlatformSettings::class)->save(['general.allow_registration' => false]);

        $this->get(route('register'))->assertRedirect(route('login'));
        $this->post(route('register.store'), [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@example.com']);

        $invitation = TeamInvitation::factory()->create();

        $this->get(route('register', ['invitation' => $invitation->code]))->assertOk();

        $this->withoutVite()
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
    }

    public function test_queue_workers_pick_up_changed_settings(): void
    {
        $worker = new PlatformSettings;
        $worker->apply();

        // Another process (the web app) saves new settings.
        (new PlatformSettings)->save(['payments.currency' => 'gbp']);
        config(['billing.currency' => 'usd']);

        $worker->refreshIfChanged();

        $this->assertSame('gbp', config('billing.currency'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payments(array $overrides = []): array
    {
        return [
            'driver' => 'stripe',
            'currency' => 'USD',
            'trial_days' => 14,
            'stripe_key' => 'pk_test_abc',
            ...$overrides,
        ];
    }
}
