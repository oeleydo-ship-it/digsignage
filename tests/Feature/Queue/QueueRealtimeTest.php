<?php

namespace Tests\Feature\Queue;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\DesignStatus;
use App\Enums\EmergencySeverity;
use App\Enums\PlaylistItemType;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Events\QueueUpdated;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use App\Support\QueueBoardPresets;
use App\Support\QueueDisplayScreenResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueueRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_updates_reach_paired_team_players_without_resolving_each_manifest(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $registration = QueueService::factory()->create(['team_id' => $team->id]);
        $billing = QueueService::factory()->create(['team_id' => $team->id]);
        $registrationScreen = $this->queueScreen($team->id, $registration->id, 'registration-player');
        $billingScreen = $this->queueScreen($team->id, $billing->id, 'billing-player');
        $otherTeamScreen = Screen::factory()->paired()->create(['team_id' => User::factory()->create()->currentTeam->id]);
        Screen::factory()->paired()->create(['team_id' => $team->id]);
        $queueSettings = QueueSetting::resolveForTeam($team);
        $queueSettings->forceFill(['settings' => [
            ...$queueSettings->settings,
            'voice' => [
                'enabled' => true,
                'languages' => ['en-US', 'ar-AE'],
                'voice' => null,
                'speed' => 1.1,
                'volume' => 0.8,
                'repeat_count' => 2,
                'chime' => true,
            ],
        ]])->save();

        $screens = app(QueueDisplayScreenResolver::class)->forService($team->id, $registration->id);

        $this->assertSame([$registrationScreen->id], $screens->modelKeys());

        $event = new QueueUpdated(
            teamId: $team->id,
            serviceId: $registration->id,
            ticketId: 42,
            ticketNumber: 'R042',
            status: QueueTicketStatus::Called->value,
            counterId: 3,
            locationId: 8,
            counterName: 'Counter Four',
            calledAt: now()->toIso8601String(),
        );
        $channels = collect($event->broadcastOn())->map(fn ($channel) => (string) $channel)->all();

        $this->assertContains('queue.service.'.$registration->id, $channels);
        $this->assertContains('private-player.'.$registrationScreen->device_uuid, $channels);
        $this->assertContains('private-player.'.$billingScreen->device_uuid, $channels);
        $this->assertNotContains('private-player.'.$otherTeamScreen->device_uuid, $channels);
        $this->assertSame('R042', $event->broadcastWith()['ticket_number']);
        $this->assertSame('Counter Four', $event->broadcastWith()['counter_name']);
        $this->assertTrue($event->broadcastWith()['voice']['enabled']);
        $this->assertSame(['en-US', 'ar-AE'], $event->broadcastWith()['voice']['languages']);
        $this->assertArrayNotHasKey('customer_name', $event->broadcastWith());
    }

    public function test_rest_manifest_polling_refreshes_queue_data_when_reverb_is_unavailable(): void
    {
        Event::fake([QueueUpdated::class]);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service);
        $token = 'queue-realtime-player-token';
        $screen = $this->queueScreen($team->id, $service->id, 'realtime-player', $token);

        $before = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->json();

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A105',
            'status' => QueueTicketStatus::Called,
            'counter_id' => $counter->id,
            'called_at' => now(),
        ]);

        $after = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();
        $element = collect($after['playback']['playlist']['items'][0]['document']['elements'])
            ->firstWhere('type', 'queue_now_serving');

        $this->assertNotSame($before['version'], $after['version']);
        $this->assertSame('A105', $element['widget']['data']['now_serving'][0]['number']);
        $this->assertSame($screen->id, $after['screen']['id']);
    }

    public function test_call_next_broadcasts_to_the_player_and_invalidates_its_queue_manifest(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'name' => 'Counter One',
        ]);
        $counter->services()->attach($service);
        $token = 'queue-call-realtime-token';
        $screen = $this->queueScreen($team->id, $service->id, 'call-realtime-player', $token);
        $ticket = QueueTicket::factory()->forService($service)->create([
            'number' => 'A101',
            'sequence' => 101,
        ]);
        $before = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        Event::fake([QueueUpdated::class]);

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect();

        Event::assertDispatched(QueueUpdated::class, function (QueueUpdated $event) use ($counter, $screen, $service, $ticket): bool {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => (string) $channel);

            return $event->serviceId === $service->id
                && $event->ticketId === $ticket->id
                && $event->ticketNumber === 'A101'
                && $event->status === QueueTicketStatus::Called->value
                && $event->counterId === $counter->id
                && $event->counterName === 'Counter One'
                && $channels->contains('queue.service.'.$service->id)
                && $channels->contains('private-player.'.$screen->device_uuid);
        });

        $after = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();
        $nowServing = collect($after['playback']['playlist']['items'][0]['document']['elements'])
            ->firstWhere('type', 'queue_now_serving')['widget']['data']['now_serving'];

        $this->assertNotSame($before['version'], $after['version']);
        $this->assertSame('A101', $nowServing[0]['number']);
        $this->assertSame('Counter One', $nowServing[0]['counter']);
    }

    public function test_printing_a_waiting_ticket_keeps_the_serving_ticket_on_the_monitor(): void
    {
        Event::fake([QueueUpdated::class]);

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service);
        $token = 'queue-kiosk-monitor-token';
        $this->queueScreen($team->id, $service->id, 'kiosk-monitor-player', $token);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'status' => QueueTicketStatus::Serving,
            'counter_id' => $counter->id,
            'called_at' => now(),
        ]);

        $before = $this->withToken($token)->getJson('/api/player/v1/manifest')->assertOk()->json();
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'source' => QueueTicketSource::Kiosk,
        ]);
        $after = $this->withToken($token)->getJson('/api/player/v1/manifest')->assertOk()->json();
        $elements = collect($after['playback']['playlist']['items'][0]['document']['elements']);
        $queue = $elements->firstWhere('type', 'queue_now_serving')['widget']['data'];

        $this->assertNotSame($before['version'], $after['version']);
        $this->assertSame('A001', $queue['now_serving'][0]['number']);
        $this->assertSame('A002', $queue['waiting'][0]['number']);
    }

    public function test_advancing_one_counter_keeps_both_counters_visible_on_the_player(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $firstCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'C01']);
        $secondCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'C02']);
        $firstCounter->services()->attach($service);
        $secondCounter->services()->attach($service);
        $screen = $this->queueScreen($team->id, $service->id, 'two-counter-player', 'two-counter-token');

        foreach (['A001', 'A002', 'A003'] as $index => $number) {
            QueueTicket::factory()->forService($service)->create([
                'number' => $number,
                'sequence' => $index + 1,
                'created_at' => now()->subMinutes(3 - $index),
            ]);
        }

        Event::fake([QueueUpdated::class]);

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $firstCounter]))
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $secondCounter]))
            ->assertRedirect();
        $beforeAdvance = $this->withToken('two-counter-token')
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();
        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $firstCounter]))
            ->assertRedirect();

        $this->assertSame('A003', $firstCounter->fresh()->currentTicket()?->number);
        $this->assertSame('A002', $secondCounter->fresh()->currentTicket()?->number);

        $manifest = $this->withToken('two-counter-token')
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();
        $elements = collect($manifest['playback']['playlist']['items'][0]['document']['elements']);
        $nowServing = $elements->firstWhere('type', 'queue_now_serving')['widget']['data']['now_serving'];

        $this->assertEqualsCanonicalizing(['A002', 'A003'], array_column($nowServing, 'number'));
        $this->assertNotSame($beforeAdvance['version'], $manifest['version']);
        $this->assertSame(2, $elements->firstWhere('type', 'queue_statistics')['widget']['data']['stats']['serving']);
        $this->assertSame($screen->id, $manifest['screen']['id']);
    }

    public function test_queue_display_refreshes_to_the_latest_state_after_an_emergency_stops(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service);
        $token = 'queue-emergency-recovery-token';
        $screen = $this->queueScreen($team->id, $service->id, 'emergency-recovery-player', $token);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A100',
            'status' => QueueTicketStatus::Called,
            'counter_id' => $counter->id,
            'called_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->post(route('emergencies.store', $team), [
                'title' => 'Queue hall alert',
                'message' => 'Please wait for instructions.',
                'severity' => EmergencySeverity::Emergency->value,
                'background' => '#b91c1c',
                'screen_ids' => [$screen->id],
                'location_ids' => [],
            ])
            ->assertRedirect();

        $emergency = $team->emergencies()->where('title', 'Queue hall alert')->firstOrFail();

        $this->actingAs($user)
            ->post(route('emergencies.start', [$team, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->assertJsonPath('playback.source', 'emergency')
            ->assertJsonPath('emergency.id', $emergency->id);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A101',
            'status' => QueueTicketStatus::Called,
            'counter_id' => $counter->id,
            'called_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('emergencies.stop', [$team, $emergency]), ['confirmed' => true])
            ->assertRedirect();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->assertJsonPath('emergency', null)
            ->json();
        $nowServing = collect($manifest['playback']['playlist']['items'][0]['document']['elements'])
            ->firstWhere('type', 'queue_now_serving')['widget']['data']['now_serving'];

        $this->assertSame('A101', $nowServing[0]['number']);
    }

    protected function queueScreen(
        int $teamId,
        int $serviceId,
        string $uuid,
        ?string $token = null,
    ): Screen {
        $design = Design::factory()->create([
            'team_id' => $teamId,
            'status' => DesignStatus::Published,
            'published_at' => now(),
            'document' => QueueBoardPresets::document('classic', ['service_id' => $serviceId]),
        ]);
        $playlist = Playlist::factory()->published()->create(['team_id' => $teamId]);
        PlaylistItem::factory()->create([
            'team_id' => $teamId,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Design,
            'design_id' => $design->id,
            'url' => null,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $teamId,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
            'status' => ChannelStatus::Published,
            'published_at' => now(),
        ]);
        $plain = $token ?? 'token-'.$uuid;

        return Screen::factory()->create([
            'team_id' => $teamId,
            'current_channel_id' => $channel->id,
            'device_uuid' => $uuid,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);
    }
}
