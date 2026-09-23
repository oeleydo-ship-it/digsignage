<?php

namespace Tests\Feature\Api;

use App\Actions\Player\RecordPlayerHeartbeat;
use App\Enums\ApiScope;
use App\Enums\LocationType;
use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use App\Enums\TeamRole;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\ApiToken;
use App\Models\Screen;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_is_public(): void
    {
        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.version', '1.0.0');
    }

    public function test_requests_without_a_token_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/screens')->assertUnauthorized();
    }

    public function test_tokens_are_scoped_and_tenant_isolated(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $foreign = Screen::factory()->create();
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'name' => 'Lobby',
        ]);

        [$readToken, $readPlain] = ApiToken::issue($user, $team, 'Read', [ApiScope::ScreensRead->value]);
        [$writeToken, $writePlain] = ApiToken::issue($user, $team, 'Write', [ApiScope::LocationsWrite->value]);

        $this->assertNotNull($readToken->id);
        $this->assertNotNull($writeToken->id);

        $this->withToken($readPlain)
            ->getJson('/api/v1/screens')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Lobby');

        $this->withToken($readPlain)
            ->getJson('/api/v1/screens/'.$foreign->id)
            ->assertNotFound();

        $this->withToken($readPlain)
            ->postJson('/api/v1/screens', [
                'name' => 'Denied',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertForbidden();

        $this->withToken($writePlain)
            ->getJson('/api/v1/locations')
            ->assertOk();

        $this->withToken($writePlain)
            ->postJson('/api/v1/locations', [
                'name' => 'Dubai',
                'type' => LocationType::City->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Dubai');
    }

    public function test_owners_can_create_tokens_from_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('integrations.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/api'));

        $this->actingAs($user)
            ->post(route('integrations.tokens.store'), [
                'name' => 'CMS',
                'scopes' => [ApiScope::AnalyticsRead->value],
            ])
            ->assertRedirect()
            ->assertSessionHas('plain_token');

        $this->assertDatabaseHas('api_tokens', [
            'team_id' => $user->currentTeam->id,
            'name' => 'CMS',
        ]);
    }

    public function test_members_cannot_manage_integrations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->get(route('integrations.edit'))
            ->assertForbidden();
    }

    public function test_webhooks_are_signed_and_dispatched_when_a_screen_comes_online(): void
    {
        Http::fake([
            'https://hooks.example.test/*' => Http::response('ok', 200),
        ]);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'status' => ScreenStatus::Offline,
        ]);

        WebhookEndpoint::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'url' => 'https://hooks.example.test/signage',
            'secret' => 'hook-secret',
            'events' => [WebhookEvent::ScreenOnline->value],
        ]);

        app(RecordPlayerHeartbeat::class)->handle($screen, [
            'player_version' => '1.0.0',
        ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'team_id' => $team->id,
            'event' => WebhookEvent::ScreenOnline->value,
            'status' => WebhookDeliveryStatus::Delivered->value,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.example.test/signage'
                && $request->hasHeader('X-DigSignage-Event', 'screen.online')
                && str_starts_with((string) $request->header('X-DigSignage-Signature')[0], 'sha256=');
        });
    }
}
