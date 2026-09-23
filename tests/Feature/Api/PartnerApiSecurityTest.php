<?php

namespace Tests\Feature\Api;

use App\Enums\ApiScope;
use App\Models\ApiToken;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_api_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        [, $plain] = ApiToken::issue(
            $user,
            $user->currentTeam,
            'Expired',
            [ApiScope::ScreensRead->value],
            now()->subMinute(),
        );

        $this->withToken($plain)
            ->getJson('/api/v1/screens')
            ->assertUnauthorized();
    }

    public function test_revoked_api_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        [$token, $plain] = ApiToken::issue(
            $user,
            $user->currentTeam,
            'Revoked',
            [ApiScope::ScreensRead->value],
        );

        $this->withToken($plain)
            ->getJson('/api/v1/screens')
            ->assertOk();

        $token->delete();

        $this->withToken($plain)
            ->getJson('/api/v1/screens')
            ->assertUnauthorized();
    }

    public function test_write_scope_grants_implicit_read_access(): void
    {
        $user = User::factory()->create();
        Screen::factory()->create(['team_id' => $user->currentTeam->id]);

        [, $plain] = ApiToken::issue(
            $user,
            $user->currentTeam,
            'Write only',
            [ApiScope::ScreensWrite->value],
        );

        $this->withToken($plain)
            ->getJson('/api/v1/screens')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_tokens_without_the_required_scope_cannot_access_other_resources(): void
    {
        $user = User::factory()->create();

        [, $plain] = ApiToken::issue(
            $user,
            $user->currentTeam,
            'Screens only',
            [ApiScope::ScreensRead->value],
        );

        $this->withToken($plain)->getJson('/api/v1/media')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/playlists')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/channels')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/schedules')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/analytics')->assertForbidden();
        $this->withToken($plain)->getJson('/api/v1/proof-of-play')->assertForbidden();
    }

    public function test_suspended_organization_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        [, $plain] = ApiToken::issue(
            $user,
            $team,
            'Suspended',
            [ApiScope::ScreensRead->value],
        );

        $team->forceFill(['suspended_at' => now()])->save();

        $this->withToken($plain)
            ->getJson('/api/v1/screens')
            ->assertForbidden()
            ->assertJsonPath('message', 'This organization is suspended.');
    }

    public function test_tokens_cannot_read_another_teams_resources(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $foreignPlaylist = Playlist::factory()->create();
        $foreignMedia = Media::factory()->create();

        Playlist::factory()->create(['team_id' => $team->id, 'name' => 'Own loop']);
        Media::factory()->create(['team_id' => $team->id, 'name' => 'Own asset']);

        [, $plain] = ApiToken::issue(
            $user,
            $team,
            'Reader',
            [ApiScope::PlaylistsRead->value, ApiScope::MediaRead->value],
        );

        $this->withToken($plain)
            ->getJson('/api/v1/playlists')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Own loop');

        $this->withToken($plain)
            ->getJson('/api/v1/playlists/'.$foreignPlaylist->id)
            ->assertNotFound();

        $this->withToken($plain)
            ->getJson('/api/v1/media')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Own asset');

        $this->withToken($plain)
            ->getJson('/api/v1/media/'.$foreignMedia->id)
            ->assertNotFound();
    }
}
