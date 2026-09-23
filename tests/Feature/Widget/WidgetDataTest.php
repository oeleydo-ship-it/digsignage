<?php

namespace Tests\Feature\Widget;

use App\Enums\ChannelType;
use App\Enums\DesignStatus;
use App\Enums\PlaylistItemType;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use App\Widgets\WidgetRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WidgetDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_page_defaults_use_an_embeddable_example_url(): void
    {
        $widget = app(WidgetRegistry::class)->find('web_page');

        $this->assertNotNull($widget);
        $this->assertSame('https://example.com', $widget->defaults()['url']);
        $this->assertFalse($widget->defaults()['fullscreen']);
    }

    public function test_ticker_defaults_are_transparent_with_sizable_text(): void
    {
        $widget = app(WidgetRegistry::class)->find('ticker');

        $this->assertNotNull($widget);
        $defaults = $widget->defaults();
        $this->assertSame('transparent', $defaults['background']);
        $this->assertSame(100, $defaults['background_opacity']);
        $this->assertSame('#ffffff', $defaults['color']);
        $this->assertSame(48, $defaults['fontSize']);
    }

    public function test_text_widgets_expose_a_font_size_control(): void
    {
        $registry = app(WidgetRegistry::class);

        foreach (['clock', 'date', 'weather', 'rss', 'alert_banner', 'menu_board'] as $key) {
            $widget = $registry->find($key);
            $this->assertNotNull($widget, $key);
            $this->assertArrayHasKey('fontSize', $widget->defaults(), $key);
        }
    }

    public function test_queue_widgets_are_registered_with_display_controls(): void
    {
        $registry = app(WidgetRegistry::class);
        $keys = [
            'queue_now_serving',
            'queue_recently_called',
            'queue_waiting_tickets',
            'queue_position',
            'queue_counter_number',
            'queue_service_name',
            'queue_estimated_wait',
            'queue_statistics',
            'queue_ticker',
            'queue_join_qr',
            'queue_status',
            'queue_board',
        ];

        foreach ($keys as $key) {
            $widget = $registry->find($key);

            $this->assertNotNull($widget, $key);
            $this->assertArrayHasKey('font_family', $widget->defaults(), $key);
            $this->assertArrayHasKey('background', $widget->defaults(), $key);
            $this->assertArrayHasKey('border_width', $widget->defaults(), $key);
            $this->assertArrayHasKey('animation', $widget->defaults(), $key);
            $this->assertArrayHasKey('service_id', $widget->defaults(), $key);
            $this->assertArrayHasKey('location_id', $widget->defaults(), $key);
            $this->assertArrayHasKey('counter_id', $widget->defaults(), $key);
            $this->assertArrayHasKey('sound', $widget->defaults(), $key);
            $this->assertArrayHasKey('voice', $widget->defaults(), $key);
        }
    }

    public function test_queue_widget_options_and_snapshot_are_scoped_to_the_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create([
            'team_id' => $team->id,
            'name' => 'Main lobby',
        ]);
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Registration',
            'average_service_duration_seconds' => 300,
        ]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Desk 4',
            'code' => 'D04',
            'status' => QueueCounterStatus::Busy,
        ]);
        $counter->services()->attach($service);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R004',
            'status' => QueueTicketStatus::Called,
            'counter_id' => $counter->id,
            'called_at' => now(),
            'customer_name' => 'Private Person',
            'customer_phone' => '+971500000000',
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R005',
            'status' => QueueTicketStatus::Waiting,
            'queue_position' => 1,
        ]);

        $definitions = app(WidgetRegistry::class)->toArrayForTeam($team);
        $definition = collect($definitions)->firstWhere('key', 'queue_now_serving');
        $serviceField = collect($definition['schema'])->firstWhere('name', 'service_id');

        $this->assertContains([
            'value' => (string) $service->id,
            'label' => 'Registration',
        ], $serviceField['options']);

        $payload = $this->actingAs($user)
            ->postJson(route('widgets.resolve', $team), [
                'timezone' => 'Asia/Dubai',
                'elements' => [[
                    'id' => 'queue-1',
                    'type' => 'queue_now_serving',
                    'props' => [
                        'service_id' => (string) $service->id,
                        'location_id' => (string) $location->id,
                        'counter_id' => (string) $counter->id,
                        'limit' => 5,
                    ],
                ]],
            ])
            ->assertOk()
            ->json('widgets.queue-1');

        $this->assertSame('R004', $payload['data']['now_serving'][0]['number']);
        $this->assertSame('Desk 4', $payload['data']['now_serving'][0]['counter']);
        $this->assertSame(1, $payload['data']['stats']['waiting']);
        $this->assertSame(5, $payload['data']['estimated_wait_minutes']);
        $this->assertStringContainsString('/join/'.$team->slug, $payload['data']['join_url']);
        $this->assertArrayNotHasKey('customer_name', $payload['data']['now_serving'][0]);
        $this->assertArrayNotHasKey('customer_phone', $payload['data']['now_serving'][0]);
    }

    public function test_web_page_reports_when_the_site_forbids_iframes(): void
    {
        Http::fake([
            'chatgpt.com/*' => Http::response('', 200, [
                'X-Frame-Options' => 'DENY',
            ]),
        ]);

        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->postJson(route('widgets.resolve', $user->currentTeam), [
                'timezone' => 'UTC',
                'elements' => [[
                    'id' => 'web-1',
                    'type' => 'web_page',
                    'props' => ['url' => 'https://chatgpt.com/'],
                ]],
            ])
            ->assertOk()
            ->json('widgets.web-1');

        $this->assertTrue($payload['data']['embed_blocked']);
    }

    public function test_unknown_widgets_are_rejected(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Widget->value,
                        'title' => 'Mystery',
                        'duration_seconds' => 10,
                        'widget_key' => 'not-a-widget',
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.widget_key');
    }

    public function test_widget_settings_are_normalized_and_stored(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Widget->value,
                        'title' => 'Weather',
                        'duration_seconds' => 15,
                        'widget_key' => 'weather',
                        'widget_settings' => [
                            'location' => 'Abu Dhabi',
                            'units' => 'fahrenheit',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $item = $playlist->items()->firstOrFail();
        $this->assertSame('weather', $item->widget_key);
        $this->assertSame('Abu Dhabi', $item->widget_settings['location']);
        $this->assertSame('fahrenheit', $item->widget_settings['units']);
    }

    public function test_manifest_includes_resolved_weather_data(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Dubai',
                    'latitude' => 25.2,
                    'longitude' => 55.3,
                ]],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 34,
                    'weather_code' => 0,
                ],
            ]),
        ]);

        [$screen, $token] = $this->pairedScreen();
        $this->attachWidget($screen, 'weather', [
            'location' => 'Dubai',
            'units' => 'celsius',
        ]);

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('widget', $item['type']);
        $this->assertSame('weather', $item['widget']['key']);
        $this->assertSame(34, $item['widget']['data']['temperature']);
        $this->assertSame('Dubai', $item['widget']['data']['place']);
        Http::assertSentCount(2);
    }

    public function test_private_rss_feeds_are_blocked(): void
    {
        Http::fake();

        [$screen, $token] = $this->pairedScreen();
        $this->attachWidget($screen, 'rss', [
            'feed_url' => 'http://127.0.0.1/feed.xml',
            'limit' => 5,
        ]);

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('The feed URL is not allowed.', $item['widget']['data']['error']);
        Http::assertNothingSent();
    }

    public function test_private_json_endpoints_are_blocked(): void
    {
        Http::fake();

        [$screen, $token] = $this->pairedScreen();
        $this->attachWidget($screen, 'json_api', [
            'url' => 'http://10.0.0.8/api.json',
            'title_path' => 'title',
            'body_path' => 'body',
        ]);

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('The endpoint is not allowed.', $item['widget']['data']['error']);
        Http::assertNothingSent();
    }

    public function test_booking_widget_marks_the_current_slot(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-18 09:15:00', 'Asia/Dubai'));

        [$screen, $token] = $this->pairedScreen();
        $this->attachWidget($screen, 'booking', [
            'heading' => 'Boardroom',
            'resource' => 'Boardroom A',
            'bookings' => "09:00-10:00 | Leadership standup | booked\n10:00-11:00 | Available | available",
        ]);

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('booking', $item['widget']['key']);
        $this->assertTrue($item['widget']['data']['slots'][0]['is_now']);
        $this->assertSame('Leadership standup', $item['widget']['data']['current']['title']);
        $this->assertSame('Available', $item['widget']['data']['next']['title']);

        CarbonImmutable::setTestNow();
    }

    public function test_designer_can_resolve_widget_data_for_preview(): void
    {
        Http::fake([
            'feeds.bbci.co.uk/*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item><title>Clinic update</title></item></channel></rss>',
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);

        $user = User::factory()->create();

        $payload = $this->actingAs($user)
            ->postJson(route('widgets.resolve', $user->currentTeam), [
                'timezone' => 'Asia/Dubai',
                'elements' => [
                    [
                        'id' => 'news-1',
                        'type' => 'news',
                        'props' => [
                            'feed_url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                            'limit' => 6,
                        ],
                    ],
                    [
                        'id' => 'alert-1',
                        'type' => 'alert_banner',
                        'props' => [
                            'severity' => 'warning',
                            'heading' => 'Notice',
                            'message' => 'Water off tonight',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->json('widgets');

        $this->assertSame('Clinic update', $payload['news-1']['data']['items'][0]['title']);
        $this->assertSame('alert_banner', $payload['alert-1']['key']);
        $this->assertSame('Notice', $payload['alert-1']['settings']['heading']);
    }

    public function test_chart_elements_are_hydrated_as_charts_widgets(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        $design = Design::factory()->create([
            'team_id' => $screen->team_id,
            'status' => DesignStatus::Published,
            'document' => [
                'width' => 1920,
                'height' => 1080,
                'background' => '#111827',
                'elements' => [[
                    'id' => 'chart-1',
                    'type' => 'chart',
                    'name' => 'Chart',
                    'x' => 0,
                    'y' => 0,
                    'width' => 800,
                    'height' => 400,
                    'rotation' => 0,
                    'opacity' => 1,
                    'zIndex' => 1,
                    'locked' => false,
                    'hidden' => false,
                    'props' => [
                        'title' => 'Occupancy',
                        'series' => "Lobby:42\nCafe:18",
                    ],
                ]],
            ],
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Design,
            'design_id' => $design->id,
            'title' => 'Board',
            'duration_seconds' => 12,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('charts', $item['document']['elements'][0]['widget']['key']);
        $this->assertSame('Occupancy', $item['document']['elements'][0]['widget']['settings']['title']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function attachWidget(Screen $screen, string $key, array $settings): void
    {
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Widget,
            'title' => $key,
            'duration_seconds' => 12,
            'url' => null,
            'widget_key' => $key,
            'widget_settings' => $settings,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'widget-device-token-'.fake()->uuid();
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'timezone' => 'Asia/Dubai',
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain];
    }
}
