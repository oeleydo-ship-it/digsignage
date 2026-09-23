<?php

namespace Tests\Feature\Emergency;

use App\Actions\Emergency\ActivateScheduledEmergencies;
use App\Actions\Emergency\ExpireEmergencies;
use App\Actions\Player\BuildPlayerManifest;
use App\Enums\DeviceCommandType;
use App\Enums\EmergencyDeliveryStatus;
use App\Enums\EmergencySeverity;
use App\Enums\EmergencyStatus;
use App\Enums\MediaType;
use App\Enums\TeamRole;
use App\Models\DeviceCommand;
use App\Models\Emergency;
use App\Models\EmergencyDelivery;
use App\Models\Location;
use App\Models\Media;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_a_draft_emergency(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('emergencies.store', $user->currentTeam), $this->payload($screen, [
                'title' => 'Severe weather',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('emergencies', [
            'team_id' => $user->currentTeam->id,
            'title' => 'Severe weather',
            'status' => EmergencyStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('emergency_audits', [
            'action' => 'created',
        ]);
    }

    public function test_start_requires_confirmation_and_overrides_playback(): void
    {
        [$user, $screen, $token] = $this->pairedOwnerScreen();
        $emergency = $this->draftFor($user, $screen, 'Lockdown');

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $emergency]))
            ->assertSessionHasErrors('confirmed');

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $emergency]), [
                'confirmed' => true,
            ])
            ->assertRedirect();

        $this->assertSame(EmergencyStatus::Active, $emergency->fresh()->status);
        $this->assertDatabaseHas('device_commands', [
            'screen_id' => $screen->id,
            'command' => DeviceCommandType::EmergencyStart->value,
        ]);

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('emergency', $manifest['playback']['source']);
        $this->assertSame('Lockdown', $manifest['emergency']['title']);
    }

    public function test_location_targets_include_descendant_screens_only(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $parent = Location::factory()->create(['team_id' => $team->id, 'name' => 'Campus']);
        $child = Location::factory()->create([
            'team_id' => $team->id,
            'parent_id' => $parent->id,
            'name' => 'Building A',
        ]);
        $other = Location::factory()->create(['team_id' => $team->id, 'name' => 'Other site']);
        $inside = Screen::factory()->create([
            'team_id' => $team->id,
            'location_id' => $child->id,
            'name' => 'Lobby',
        ]);
        $outside = Screen::factory()->create([
            'team_id' => $team->id,
            'location_id' => $other->id,
            'name' => 'Warehouse',
        ]);

        $this->actingAs($user)
            ->post(route('emergencies.store', $team), [
                'title' => 'Campus alert',
                'severity' => EmergencySeverity::Critical->value,
                'location_ids' => [$parent->id],
                'screen_ids' => [],
            ])
            ->assertRedirect();

        $emergency = Emergency::query()->where('title', 'Campus alert')->firstOrFail();

        $this->actingAs($user)
            ->post(route('emergencies.start', [$team, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('emergency_deliveries', [
            'emergency_id' => $emergency->id,
            'screen_id' => $inside->id,
        ]);
        $this->assertDatabaseMissing('emergency_deliveries', [
            'emergency_id' => $emergency->id,
            'screen_id' => $outside->id,
        ]);
    }

    public function test_members_can_view_but_cannot_start(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        $screen = Screen::factory()->create(['team_id' => $team->id]);
        $emergency = $this->draftFor($owner, $screen, 'Silent alarm');

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('emergencies.index', $team))
            ->assertOk();

        $this->actingAs($member)
            ->post(route('emergencies.start', [$team, $emergency]), ['confirmed' => true])
            ->assertForbidden();
    }

    public function test_emergencies_are_isolated_by_team(): void
    {
        $user = User::factory()->create();
        $foreignUser = User::factory()->create();
        $foreignScreen = Screen::factory()->create(['team_id' => $foreignUser->currentTeam->id]);
        $foreign = $this->draftFor($foreignUser, $foreignScreen, 'Secret');

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('emergencies.show', [$user->currentTeam, $foreign]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $foreign]), ['confirmed' => true])
            ->assertForbidden();
    }

    public function test_foreign_media_cannot_be_attached(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $user->currentTeam->id]);
        $foreign = Media::factory()->create(['type' => MediaType::Image]);

        $this->actingAs($user)
            ->post(route('emergencies.store', $user->currentTeam), $this->payload($screen, [
                'image_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('image_id');
    }

    public function test_player_acknowledgement_updates_delivery_status(): void
    {
        [$user, $screen, $token] = $this->pairedOwnerScreen();
        $emergency = $this->draftFor($user, $screen, 'Ack me');

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $command = DeviceCommand::query()->where('screen_id', $screen->id)->latest('id')->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/ack')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/complete', [
                'status' => 'completed',
                'result' => ['action' => 'emergency-start'],
            ])
            ->assertOk();

        $delivery = EmergencyDelivery::query()->where('emergency_id', $emergency->id)->firstOrFail();
        $this->assertSame(EmergencyDeliveryStatus::Acknowledged, $delivery->status);
        $this->assertNotNull($delivery->acknowledged_at);
        $this->assertDatabaseHas('emergency_audits', [
            'emergency_id' => $emergency->id,
            'action' => 'acknowledged',
            'screen_id' => $screen->id,
        ]);
    }

    public function test_stop_clears_the_manifest_override(): void
    {
        [$user, $screen, $token] = $this->pairedOwnerScreen();
        $emergency = $this->draftFor($user, $screen, 'All clear soon');

        $this->actingAs($user)->post(route('emergencies.start', [$user->currentTeam, $emergency]), [
            'confirmed' => true,
        ]);

        $this->actingAs($user)
            ->post(route('emergencies.stop', [$user->currentTeam, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->assertSame(EmergencyStatus::Stopped, $emergency->fresh()->status);
        $this->assertDatabaseHas('device_commands', [
            'screen_id' => $screen->id,
            'command' => DeviceCommandType::EmergencyStop->value,
        ]);

        $manifest = app(BuildPlayerManifest::class)->handle($screen->fresh());
        $this->assertNull($manifest['emergency']);
        $this->assertNotSame('emergency', $manifest['playback']['source']);
    }

    public function test_scheduled_broadcasts_activate_and_expire(): void
    {
        [$user, $screen] = $this->pairedOwnerScreen();
        $emergency = $this->draftFor($user, $screen, 'Timed');
        $emergency->forceFill([
            'starts_at' => now()->addHour(),
            'expires_at' => now()->addHours(2),
        ])->save();

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->assertSame(EmergencyStatus::Scheduled, $emergency->fresh()->status);

        $this->travel(61)->minutes();
        $this->assertSame(1, app(ActivateScheduledEmergencies::class)->handle());
        $this->assertSame(EmergencyStatus::Active, $emergency->fresh()->status);

        $this->travel(61)->minutes();
        $this->assertSame(1, app(ExpireEmergencies::class)->handle());
        $this->assertSame(EmergencyStatus::Expired, $emergency->fresh()->status);
    }

    public function test_emergency_outranks_critical_on_the_same_screen(): void
    {
        [$user, $screen] = $this->pairedOwnerScreen();
        $critical = $this->draftFor($user, $screen, 'Critical only');
        $critical->forceFill(['severity' => EmergencySeverity::Critical])->save();
        $emergency = $this->draftFor($user, $screen, 'True emergency');

        $this->actingAs($user)->post(route('emergencies.start', [$user->currentTeam, $critical]), ['confirmed' => true]);
        $this->actingAs($user)->post(route('emergencies.start', [$user->currentTeam, $emergency]), ['confirmed' => true]);

        $manifest = app(BuildPlayerManifest::class)->handle($screen->fresh());
        $this->assertSame($emergency->id, $manifest['emergency']['id']);
        $this->assertSame('emergency', $manifest['playback']['source']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(Screen $screen, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Alert',
            'message' => 'Follow staff directions.',
            'instructions' => 'Remain indoors.',
            'severity' => EmergencySeverity::Emergency->value,
            'background' => '#b91c1c',
            'screen_ids' => [$screen->id],
            'location_ids' => [],
        ], $overrides);
    }

    protected function draftFor(User $user, Screen $screen, string $title): Emergency
    {
        $this->actingAs($user)
            ->post(route('emergencies.store', $user->currentTeam), $this->payload($screen, [
                'title' => $title,
            ]))
            ->assertRedirect();

        return Emergency::query()->where('title', $title)->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Screen, 2: string}
     */
    protected function pairedOwnerScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'player-device-token';
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$user, $screen, $plain];
    }
}
