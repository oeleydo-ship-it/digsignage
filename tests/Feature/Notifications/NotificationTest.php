<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Actions\Signage\EvaluateScreenHealth;
use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\ScreenStatus;
use App\Enums\SignageAlert;
use App\Enums\TeamRole;
use App\Models\DeviceCommand;
use App\Models\Emergency;
use App\Models\InAppNotification;
use App\Models\Media;
use App\Models\NotificationChannelPreference;
use App\Models\NotificationSetting;
use App\Models\Screen;
use App\Models\User;
use App\Notifications\SignageAlertMail;
use App\Services\Media\ProcessMediaFile;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_evaluation_notifies_owners_and_posts_webhooks(): void
    {
        Notification::fake();
        Http::fake();

        [$screen, , $user] = $this->pairedScreen();
        $member = User::factory()->create();
        $screen->team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $settings = NotificationSetting::resolveForTeam($screen->team);
        $settings->update([
            'webhook_url' => 'https://example.com/hooks/signage',
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXX',
        ]);
        NotificationChannelPreference::factory()->create([
            'team_id' => $screen->team_id,
            'event' => SignageAlert::ScreenOffline,
            'email' => true,
            'in_app' => true,
            'webhook' => true,
            'slack' => true,
        ]);

        $screen->forceFill([
            'status' => ScreenStatus::Online,
            'last_seen_at' => now()->subMinutes(6),
        ])->save();

        app(EvaluateScreenHealth::class)->handle($screen->team);

        $this->assertDatabaseHas('in_app_notifications', [
            'team_id' => $screen->team_id,
            'user_id' => $user->id,
            'event' => SignageAlert::ScreenOffline->value,
        ]);
        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $member->id,
            'event' => SignageAlert::ScreenOffline->value,
        ]);

        Notification::assertSentTo($user, SignageAlertMail::class);
        Notification::assertNotSentTo($member, SignageAlertMail::class);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hooks/signage'
            && $request['event'] === SignageAlert::ScreenOffline->value);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hooks.slack.com'));
    }

    public function test_heartbeat_restores_and_reports_outdated_players(): void
    {
        [$screen, $token, $user] = $this->pairedScreen();
        NotificationSetting::resolveForTeam($screen->team)->update([
            'min_player_version' => '2.0.0',
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'player_version' => 'web-1.1',
                'storage_free' => 50,
                'storage_total' => 1000,
            ])
            ->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::ScreenRestored->value,
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::StorageLow->value,
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::PlayerOutdated->value,
        ]);
    }

    public function test_sync_failure_and_disabled_email_channel(): void
    {
        Notification::fake();
        [$screen, $token, $user] = $this->pairedScreen();
        $screen->forceFill(['status' => ScreenStatus::Online, 'last_seen_at' => now()])->save();

        NotificationChannelPreference::factory()->create([
            'team_id' => $screen->team_id,
            'event' => SignageAlert::SyncFailed,
            'email' => false,
            'in_app' => true,
            'webhook' => false,
            'slack' => false,
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'playing_offline' => true,
                'network_status' => 'offline',
            ])
            ->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::SyncFailed->value,
        ]);
        Notification::assertNotSentTo($user, SignageAlertMail::class);
    }

    public function test_media_processing_failure_creates_an_alert(): void
    {
        Storage::fake('media');
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'storage_path' => 'missing.bin',
            'name' => 'Broken clip',
        ]);

        app(ProcessMediaFile::class)->handle($media);

        $this->assertDatabaseHas('in_app_notifications', [
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'event' => SignageAlert::MediaProcessingFailed->value,
        ]);
    }

    public function test_emergency_start_and_stop_notify_administrators(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('emergencies.store', $user->currentTeam), [
                'title' => 'Weather lock',
                'message' => 'Stay inside.',
                'severity' => 'emergency',
                'screen_ids' => [$screen->id],
                'location_ids' => [],
            ])
            ->assertRedirect();

        $emergency = Emergency::query()->where('title', 'Weather lock')->firstOrFail();

        $this->actingAs($user)
            ->post(route('emergencies.start', [$user->currentTeam, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('in_app_notifications', [
            'event' => SignageAlert::EmergencyActivated->value,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('emergencies.stop', [$user->currentTeam, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('in_app_notifications', [
            'event' => SignageAlert::EmergencyStopped->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_subscription_issue_can_be_dispatched(): void
    {
        $user = User::factory()->create();

        app(DispatchSignageAlert::class)->queue(
            $user->currentTeam,
            SignageAlert::SubscriptionIssue,
            'Billing past due',
            'Update the payment method to keep screens online.',
            ['invoice' => 'in_test'],
        );

        $this->assertDatabaseHas('in_app_notifications', [
            'team_id' => $user->currentTeam->id,
            'event' => SignageAlert::SubscriptionIssue->value,
        ]);
    }

    public function test_administrators_configure_preferences_and_members_can_read_inbox(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $item = InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'title' => 'Screen offline: Lobby',
        ]);
        InAppNotification::factory()->create([
            'team_id' => User::factory()->create()->currentTeam->id,
            'user_id' => $member->id,
            'title' => 'Foreign secret',
        ]);

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('notifications.index', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifications/index')
                ->where('canManage', false)
                ->has('notifications', 1)
                ->where('notifications.0.title', 'Screen offline: Lobby'));

        $this->actingAs($member)
            ->patch(route('notifications.settings.update', $team), [
                'preferences' => [
                    SignageAlert::ScreenOffline->value => [
                        'email' => false,
                        'in_app' => true,
                        'webhook' => false,
                        'slack' => false,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('notifications.settings.update', $team), [
                'webhook_url' => 'https://example.com/hooks/signage',
                'slack_webhook_url' => '',
                'min_player_version' => '1.4.0',
                'preferences' => collect(SignageAlert::cases())->mapWithKeys(fn (SignageAlert $alert) => [
                    $alert->value => [
                        'email' => $alert === SignageAlert::ScreenOffline,
                        'in_app' => true,
                        'webhook' => $alert === SignageAlert::ScreenOffline,
                        'slack' => false,
                    ],
                ])->all(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_settings', [
            'team_id' => $team->id,
            'webhook_url' => 'https://example.com/hooks/signage',
            'min_player_version' => '1.4.0',
        ]);

        $this->actingAs($member)
            ->from(route('screens.index', $team))
            ->post(route('notifications.read', [$team, $item]))
            ->assertRedirect(route('screens.index', $team));

        $this->assertNotNull($item->fresh()->read_at);
    }

    public function test_header_shares_unread_count_and_recent_notifications(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $unread = InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'event' => SignageAlert::ScreenOffline,
            'title' => 'Screen offline: Lobby',
            'data' => ['screen_id' => $screen->id],
            'read_at' => null,
        ]);
        InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'event' => SignageAlert::SubscriptionIssue,
            'title' => 'Billing past due',
            'data' => [],
            'read_at' => now(),
        ]);
        InAppNotification::factory()->create([
            'team_id' => User::factory()->create()->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Other org',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('screens.commands.index', [$team, $screen]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unreadNotifications', 1)
                ->has('recentNotifications', 2)
                ->where('recentNotifications.0.title', 'Billing past due')
                ->where('recentNotifications.0.url', route('billing.index', $team))
                ->where('recentNotifications.1.id', $unread->id)
                ->where('recentNotifications.1.url', route('screens.commands.index', [$team, $screen])));
    }

    public function test_mark_all_read_stays_on_the_current_page(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->from(route('screens.commands.index', [$team, $screen]))
            ->post(route('notifications.read-all', $team))
            ->assertRedirect(route('screens.commands.index', [$team, $screen]));

        $this->assertSame(
            0,
            InAppNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        );
    }

    public function test_mark_read_honors_stay_on_page_header_instead_of_the_dashboard(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);
        $item = InAppNotification::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', route('screens.commands.index', [$team, $screen]))
            ->post(route('notifications.read', [$team, $item]))
            ->assertRedirect(route('screens.commands.index', [$team, $screen]));

        $this->assertNotNull($item->fresh()->read_at);
    }

    public function test_failed_device_command_notifies_administrators(): void
    {
        [$screen, $token, $user] = $this->pairedScreen();
        $command = DeviceCommand::factory()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'status' => DeviceCommandStatus::Sent,
            'command' => DeviceCommandType::Sync,
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/complete', [
                'status' => 'failed',
                'result' => ['error' => 'Player rejected sync.'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::DeviceCommandFailed->value,
            'body' => 'Player rejected sync.',
        ]);
    }

    public function test_completed_device_command_does_not_notify(): void
    {
        [$screen, $token, $user] = $this->pairedScreen();
        $command = DeviceCommand::factory()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'status' => DeviceCommandStatus::Sent,
            'command' => DeviceCommandType::Refresh,
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/complete', [
                'status' => 'completed',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $user->id,
            'event' => SignageAlert::DeviceCommandFailed->value,
        ]);
    }

    public function test_user_cannot_view_another_teams_notifications(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->get(route('notifications.index', $userB->currentTeam))
            ->assertForbidden();
    }

    public function test_private_webhook_urls_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notifications.settings.update', $user->currentTeam), [
                'webhook_url' => 'http://127.0.0.1/hook',
                'preferences' => collect(SignageAlert::cases())->mapWithKeys(fn (SignageAlert $alert) => [
                    $alert->value => ['email' => true, 'in_app' => true, 'webhook' => false, 'slack' => false],
                ])->all(),
            ])
            ->assertSessionHasErrors('webhook_url');
    }

    /**
     * @return array{0: Screen, 1: string, 2: User}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'notify-device-'.fake()->uuid();
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain, $user];
    }
}
