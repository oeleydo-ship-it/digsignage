<?php

namespace Tests\Feature\Signage;

use App\Enums\TeamRole;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FallbackImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_settings_page_renders_for_the_current_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('player-fallback.edit', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/player-fallback')
                ->where('fallbackImage', null)
                ->where('canManage', true));
    }

    public function test_owner_can_upload_a_team_fallback_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('player-fallback.update', $team), [
                'image' => UploadedFile::fake()->image('fallback.jpg', 1920, 1080),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $url = $team->fresh()->settings['fallback_image'] ?? null;

        $this->assertIsString($url);
        $this->assertStringStartsWith('/storage/'.$team->id.'/fallback/', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_uploading_a_new_team_fallback_image_replaces_the_old_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('player-fallback.update', $team), [
                'image' => UploadedFile::fake()->image('first.png'),
            ])
            ->assertSessionHasNoErrors();

        $first = $team->fresh()->settings['fallback_image'];

        $this->actingAs($user)
            ->post(route('player-fallback.update', $team), [
                'image' => UploadedFile::fake()->image('second.webp'),
            ])
            ->assertSessionHasNoErrors();

        $second = $team->fresh()->settings['fallback_image'];

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing(substr($first, strlen('/storage/')));
        Storage::disk('public')->assertExists(substr($second, strlen('/storage/')));
    }

    public function test_fallback_image_validation_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('player-fallback.update', $user->currentTeam), [
                'image' => UploadedFile::fake()->create('notes.txt', 12, 'text/plain'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertArrayNotHasKey('fallback_image', $user->currentTeam->fresh()->settings ?? []);
    }

    public function test_fallback_image_validation_rejects_oversized_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('player-fallback.update', $user->currentTeam), [
                'image' => UploadedFile::fake()->image('huge.jpg', 4000, 3000)->size(6000),
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_fallback_image_requires_a_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('player-fallback.update', $user->currentTeam), [])
            ->assertSessionHasErrors('image');
    }

    public function test_members_cannot_manage_the_team_fallback_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('player-fallback.update', $team), [
                'image' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('player-fallback.destroy', $team))
            ->assertForbidden();
    }

    public function test_fallback_image_routes_are_denied_across_tenants(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $foreign = User::factory()->create();

        $this->actingAs($foreign)
            ->post(route('player-fallback.update', $owner->currentTeam), [
                'image' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();

        $this->assertArrayNotHasKey('fallback_image', $owner->currentTeam->fresh()->settings ?? []);
    }

    public function test_removing_the_team_fallback_image_clears_settings_and_deletes_the_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('player-fallback.update', $team), [
                'image' => UploadedFile::fake()->image('fallback.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $url = $team->fresh()->settings['fallback_image'];

        $this->actingAs($user)
            ->delete(route('player-fallback.destroy', $team))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('fallback_image', $team->fresh()->settings ?? []);
        Storage::disk('public')->assertMissing(substr($url, strlen('/storage/')));
    }

    public function test_manifest_includes_the_team_fallback_image(): void
    {
        Storage::fake('public');

        [$screen, $token, $user] = $this->pairedScreen();

        $this->actingAs($user)
            ->post(route('player-fallback.update', $user->currentTeam), [
                'image' => UploadedFile::fake()->image('fallback.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $url = $user->currentTeam->fresh()->settings['fallback_image'];

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame($url, $manifest['fallback']['image_url']);
        $this->assertSame($screen->name, $manifest['fallback']['screen_name']);
    }

    public function test_manifest_has_no_fallback_image_by_default(): void
    {
        [, $token] = $this->pairedScreen();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertNull($manifest['fallback']['image_url']);
    }

    public function test_screen_fallback_image_overrides_the_team_default_in_the_manifest(): void
    {
        Storage::fake('public');

        [$screen, $token, $user] = $this->pairedScreen();
        $team = $user->currentTeam;
        $team->forceFill(['settings' => ['fallback_image' => '/storage/'.$team->id.'/fallback/team-default.jpg']])->save();

        $this->actingAs($user)
            ->post(route('screens.fallback-image.update', [$team->slug, $screen->id]), [
                'image' => UploadedFile::fake()->image('screen.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $override = $screen->fresh()->metadata['fallback_image'] ?? null;

        $this->assertIsString($override);
        $this->assertStringStartsWith('/storage/'.$team->id.'/fallback/', $override);
        Storage::disk('public')->assertExists(substr($override, strlen('/storage/')));

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame($override, $manifest['fallback']['image_url']);
    }

    public function test_screen_fallback_image_can_be_removed_to_restore_the_team_default(): void
    {
        Storage::fake('public');

        [$screen, $token, $user] = $this->pairedScreen();
        $team = $user->currentTeam;
        $team->forceFill(['settings' => ['fallback_image' => '/storage/'.$team->id.'/fallback/team-default.jpg']])->save();

        $this->actingAs($user)
            ->post(route('screens.fallback-image.update', [$team->slug, $screen->id]), [
                'image' => UploadedFile::fake()->image('screen.png'),
            ])
            ->assertSessionHasNoErrors();

        $override = $screen->fresh()->metadata['fallback_image'];

        $this->actingAs($user)
            ->delete(route('screens.fallback-image.destroy', [$team->slug, $screen->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertArrayNotHasKey('fallback_image', $screen->fresh()->metadata ?? []);
        Storage::disk('public')->assertMissing(substr($override, strlen('/storage/')));

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('/storage/'.$team->id.'/fallback/team-default.jpg', $manifest['fallback']['image_url']);
    }

    public function test_screen_fallback_image_cannot_be_set_for_another_teams_screen(): void
    {
        Storage::fake('public');

        [$screen, , $user] = $this->pairedScreen();
        $foreign = User::factory()->create();
        $foreignScreen = Screen::factory()->create(['team_id' => $foreign->currentTeam->id]);

        // A foreign team's slug is rejected by the membership middleware.
        $this->actingAs($user)
            ->post(route('screens.fallback-image.update', [$foreign->currentTeam->slug, $foreignScreen->id]), [
                'image' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();

        // A foreign screen id under the user's own team slug is rejected by the policy.
        $this->actingAs($user)
            ->post(route('screens.fallback-image.update', [$user->currentTeam->slug, $foreignScreen->id]), [
                'image' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();

        $this->assertArrayNotHasKey('fallback_image', $foreignScreen->fresh()->metadata ?? []);
        $this->assertArrayNotHasKey('fallback_image', $screen->fresh()->metadata ?? []);
    }

    public function test_members_cannot_set_a_screen_fallback_image(): void
    {
        Storage::fake('public');

        [$screen, , $owner] = $this->pairedScreen();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('screens.fallback-image.update', [$team->slug, $screen->id]), [
                'image' => UploadedFile::fake()->image('nope.png'),
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Screen, 1: string, 2: User}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'player-device-token';
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain, $user];
    }
}
