<?php

namespace Tests\Feature\Player;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\TeamRole;
use App\Events\DeviceCommandIssued;
use App\Models\Channel;
use App\Models\DeviceCommand;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DeviceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_dispatch_commands_to_paired_screens(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        [$user, $screen] = $this->pairedOwnerScreen();

        $this->actingAs($user)
            ->from(route('screens.commands.index', [$user->currentTeam, $screen]))
            ->post(route('screens.commands.store', [$user->currentTeam, $screen]), [
                'command' => DeviceCommandType::Sync->value,
            ])
            ->assertRedirect(route('screens.commands.index', [$user->currentTeam, $screen]));

        $this->assertDatabaseHas('device_commands', [
            'screen_id' => $screen->id,
            'team_id' => $screen->team_id,
            'command' => DeviceCommandType::Sync->value,
            'status' => DeviceCommandStatus::Sent->value,
        ]);

        Event::assertDispatched(DeviceCommandIssued::class);
    }

    public function test_sync_does_not_fall_back_to_the_dashboard(): void
    {
        Event::fake([DeviceCommandIssued::class]);

        [$user, $screen] = $this->pairedOwnerScreen();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('screens.commands.store', [$team, $screen]), [
                'command' => DeviceCommandType::Sync->value,
            ])
            ->assertRedirect(route('screens.commands.index', [$team, $screen]));
    }

    public function test_command_history_page_is_shown_for_the_screen_team(): void
    {
        [$user, $screen] = $this->pairedOwnerScreen();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('screens.commands.index', [$user->currentTeam, $screen]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('signage/screens/commands')
                ->where('screen.id', $screen->id)
                ->where('canCommand', true));
    }

    public function test_members_cannot_send_commands(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $screen = Screen::factory()->paired()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('screens.commands.store', [$team, $screen]), [
                'command' => DeviceCommandType::Refresh->value,
            ])
            ->assertForbidden();
    }

    public function test_commands_cannot_target_another_teams_screen(): void
    {
        [$user] = $this->pairedOwnerScreen();
        $foreign = Screen::factory()->paired()->create();

        $this->actingAs($user)
            ->post(route('screens.commands.store', [$user->currentTeam, $foreign]), [
                'command' => DeviceCommandType::Reload->value,
            ])
            ->assertForbidden();
    }

    public function test_change_channel_updates_the_screen_and_rejects_foreign_channels(): void
    {
        [$user, $screen, $token] = $this->pairedOwnerScreen();
        $channel = Channel::factory()->create(['team_id' => $screen->team_id, 'name' => 'Lobby']);
        $foreign = Channel::factory()->create(['name' => 'Secret']);

        $this->actingAs($user)
            ->post(route('screens.commands.store', [$user->currentTeam, $screen]), [
                'command' => DeviceCommandType::ChangeChannel->value,
                'payload' => ['channel_id' => $foreign->id],
            ])
            ->assertSessionHasErrors('payload.channel_id');

        $this->actingAs($user)
            ->from(route('screens.commands.index', [$user->currentTeam, $screen]))
            ->post(route('screens.commands.store', [$user->currentTeam, $screen]), [
                'command' => DeviceCommandType::ChangeChannel->value,
                'payload' => ['channel_id' => $channel->id],
            ])
            ->assertRedirect(route('screens.commands.index', [$user->currentTeam, $screen]));

        $this->assertSame($channel->id, $screen->fresh()->current_channel_id);

        $command = DeviceCommand::query()->where('screen_id', $screen->id)->latest('id')->first();
        $this->assertNotNull($command);

        $this->withToken($token)
            ->getJson('/api/player/v1/commands')
            ->assertOk()
            ->assertJsonPath('commands.0.command', 'change-channel')
            ->assertJsonPath('commands.0.payload.channel_id', $channel->id);
    }

    public function test_player_polls_acks_and_completes_commands(): void
    {
        [, $screen, $token] = $this->pairedOwnerScreen();
        $command = DeviceCommand::factory()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'command' => DeviceCommandType::ClearCache,
            'status' => DeviceCommandStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken($token)
            ->getJson('/api/player/v1/commands')
            ->assertOk()
            ->assertJsonPath('commands.0.command_id', $command->id);

        $this->assertSame(DeviceCommandStatus::Sent, $command->fresh()->status);

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/ack')
            ->assertOk()
            ->assertJsonPath('status', 'acknowledged');

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/complete', [
                'status' => 'completed',
                'result' => ['action' => 'clear-cache'],
            ])
            ->assertOk();

        $command->refresh();
        $this->assertSame(DeviceCommandStatus::Completed, $command->status);
        $this->assertSame('clear-cache', $command->result['action']);
        $this->assertNotNull($command->acknowledged_at);
        $this->assertNotNull($command->completed_at);
    }

    public function test_player_cannot_complete_another_screens_command(): void
    {
        [, , $token] = $this->pairedOwnerScreen();
        $foreign = DeviceCommand::factory()->create([
            'command' => DeviceCommandType::Sync,
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$foreign->id.'/ack')
            ->assertNotFound();
    }

    public function test_expired_commands_are_not_delivered(): void
    {
        [, $screen, $token] = $this->pairedOwnerScreen();
        $command = DeviceCommand::factory()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'status' => DeviceCommandStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($token)
            ->getJson('/api/player/v1/commands')
            ->assertOk()
            ->assertJson(['commands' => []]);

        $this->assertSame(DeviceCommandStatus::Expired, $command->fresh()->status);
    }

    public function test_device_can_authorize_its_reverb_channel(): void
    {
        [, $screen, $token] = $this->pairedOwnerScreen();

        config([
            'broadcasting.connections.reverb.key' => 'player-key',
            'broadcasting.connections.reverb.secret' => 'player-secret',
        ]);

        $channel = 'private-player.'.$screen->device_uuid;

        $this->withToken($token)
            ->postJson('/api/player/v1/broadcasting/auth', [
                'channel_name' => $channel,
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonPath('auth', 'player-key:'.hash_hmac('sha256', '1234.5678:'.$channel, 'player-secret'));

        $this->withToken($token)
            ->postJson('/api/player/v1/broadcasting/auth', [
                'channel_name' => 'private-player.other-device',
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
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
