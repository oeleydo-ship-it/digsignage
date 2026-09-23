<?php

namespace Tests\Feature\Security;

use App\Enums\ApiScope;
use App\Enums\AuditAction;
use App\Enums\TeamRole;
use App\Enums\WebhookEvent;
use App\Models\ApiToken;
use App\Models\Media;
use App\Models\Screen;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_browser_security_headers(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy') ?? '';

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('style-src', $csp);
        $this->assertStringContainsString('https://www.youtube.com', $csp);
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $csp);
        $this->assertMatchesRegularExpression('/frame-src[^;]*https:/', $csp);
        $this->assertMatchesRegularExpression('/child-src[^;]*https:/', $csp);
        $this->assertStringContainsString('https://api.open-meteo.com', $csp);
        $this->assertStringContainsString('https://geocoding-api.open-meteo.com', $csp);
    }

    public function test_failed_login_is_audited_for_the_users_organization(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'action' => AuditAction::UserLoginFailed->value,
        ]);
    }

    public function test_rotating_an_api_token_invalidates_the_previous_secret(): void
    {
        $user = User::factory()->create();
        [$token, $plain] = ApiToken::issue($user, $user->currentTeam, 'CMS', [ApiScope::ScreensRead->value]);

        $this->withToken($plain)->getJson('/api/v1/screens')->assertOk();

        $this->actingAs($user)
            ->post(route('integrations.tokens.rotate', $token))
            ->assertRedirect()
            ->assertSessionHas('plain_token');

        $newPlain = session('plain_token');
        $this->assertIsString($newPlain);
        $this->assertNotSame($plain, $newPlain);

        $this->withToken($plain)->getJson('/api/v1/screens')->assertUnauthorized();
        $this->withToken($newPlain)->getJson('/api/v1/screens')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $user->currentTeam->id,
            'action' => AuditAction::ApiTokenRotated->value,
            'resource_id' => $token->id,
        ]);
    }

    public function test_foreign_teams_cannot_rotate_api_tokens(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$token] = ApiToken::issue($owner, $owner->currentTeam, 'CMS', [ApiScope::ScreensRead->value]);

        $this->actingAs($intruder)
            ->post(route('integrations.tokens.rotate', $token))
            ->assertForbidden();
    }

    public function test_rotating_a_webhook_secret_is_audited(): void
    {
        $user = User::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'secret' => 'old-secret',
            'events' => [WebhookEvent::ScreenOnline->value],
        ]);

        $this->actingAs($user)
            ->post(route('integrations.webhooks.rotate', $endpoint))
            ->assertRedirect()
            ->assertSessionHas('plain_secret');

        $endpoint->refresh();
        $this->assertNotSame('old-secret', $endpoint->secret);
        $this->assertSame(session('plain_secret'), $endpoint->secret);

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $user->currentTeam->id,
            'action' => AuditAction::WebhookSecretRotated->value,
            'resource_id' => $endpoint->id,
        ]);
    }

    public function test_rotating_device_credentials_invalidates_the_previous_token(): void
    {
        $user = User::factory()->create();
        $plain = 'player-device-token';
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        $this->withToken($plain)->getJson('/api/player/v1/manifest')->assertOk();

        $this->actingAs($user)
            ->post(route('screens.rotate-credentials', [$user->currentTeam, $screen]))
            ->assertRedirect()
            ->assertSessionHas('plain_device_token');

        $newPlain = session('plain_device_token');
        $this->assertIsString($newPlain);
        $this->assertNotSame($plain, $newPlain);

        $this->withToken($plain)->getJson('/api/player/v1/manifest')->assertUnauthorized();
        $this->withToken($newPlain)->getJson('/api/player/v1/manifest')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $user->currentTeam->id,
            'action' => AuditAction::DeviceCredentialsRotated->value,
            'resource_id' => $screen->id,
        ]);
    }

    public function test_members_cannot_rotate_device_credentials(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $screen = Screen::factory()->paired()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('screens.rotate-credentials', [$team, $screen]))
            ->assertForbidden();
    }

    public function test_svg_originals_are_streamed_with_a_sandbox_csp(): void
    {
        Storage::fake('media');

        $user = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'mime_type' => 'image/svg+xml',
            'original_filename' => 'banner.svg',
            'storage_path' => $user->currentTeam->id.'/originals/banner.svg',
        ]);
        Storage::disk('media')->put($media->storage_path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->actingAs($user)
            ->get(route('media.file', [$user->currentTeam, $media]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'");
    }

    public function test_creating_a_screen_ignores_client_supplied_team_ids(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();

        $this->actingAs($user)
            ->post(route('screens.store', $user->currentTeam), [
                'name' => 'Lobby',
                'orientation' => 'landscape',
                'team_id' => $foreign->currentTeam->id,
                'user_id' => $foreign->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('screens', [
            'name' => 'Lobby',
            'team_id' => $user->currentTeam->id,
        ]);
        $this->assertDatabaseMissing('screens', [
            'name' => 'Lobby',
            'team_id' => $foreign->currentTeam->id,
        ]);
    }
}
