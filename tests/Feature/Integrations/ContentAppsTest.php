<?php

namespace Tests\Feature\Integrations;

use App\Enums\AuditAction;
use App\Enums\ChannelType;
use App\Enums\PlaylistItemType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentAppsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_save_content_apps_on_the_current_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->from(route('apps.edit', $team))
            ->patch(route('apps.update', $team), [
                'apps' => $this->payload([
                    'weather' => [
                        'enabled' => true,
                        'location' => 'Abu Dhabi',
                        'units' => 'celsius',
                    ],
                    'rss' => [
                        'enabled' => true,
                        'feed_url' => 'https://example.com/campus.xml',
                        'limit' => 4,
                    ],
                ]),
            ])
            ->assertRedirect(route('apps.edit', $team))
            ->assertSessionHasNoErrors();

        $integrations = $team->fresh()->settings['integrations'];
        $this->assertTrue($integrations['weather']['enabled']);
        $this->assertSame('Abu Dhabi', $integrations['weather']['location']);
        $this->assertSame('https://example.com/campus.xml', $integrations['rss']['feed_url']);
        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'action' => AuditAction::IntegrationsUpdated->value,
        ]);
    }

    public function test_private_feed_urls_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('apps.edit', $user->currentTeam))
            ->patch(route('apps.update', $user->currentTeam), [
                'apps' => $this->payload([
                    'rss' => [
                        'enabled' => true,
                        'feed_url' => 'http://127.0.0.1/feed.xml',
                    ],
                ]),
            ])
            ->assertSessionHasErrors('apps.rss.feed_url');
    }

    public function test_members_cannot_update_apps(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->patch(route('apps.update', $team), [
                'apps' => $this->payload([
                    'weather' => ['enabled' => true, 'location' => 'Sharjah'],
                ]),
            ])
            ->assertForbidden();
    }

    public function test_apps_are_isolated_between_organizations(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'geocoding-api.open-meteo.com')) {
                $name = (string) ($request['name'] ?? 'Dubai');

                return Http::response([
                    'results' => [[
                        'name' => $name,
                        'latitude' => 25.2,
                        'longitude' => 55.3,
                    ]],
                ]);
            }

            return Http::response([
                'current' => [
                    'temperature_2m' => 30,
                    'weather_code' => 0,
                ],
            ]);
        });

        $alpha = User::factory()->create();
        $beta = User::factory()->create();

        $this->actingAs($alpha)
            ->patch(route('apps.update', $alpha->currentTeam), [
                'apps' => $this->payload([
                    'weather' => [
                        'enabled' => true,
                        'location' => 'Abu Dhabi',
                        'units' => 'celsius',
                    ],
                ]),
            ])
            ->assertSessionHasNoErrors();

        [$screen, $token] = $this->pairedScreen($beta->currentTeam);
        $this->attachWidget($screen, 'weather', ['location' => '', 'units' => 'celsius']);

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertNotSame('Abu Dhabi', $item['widget']['data']['place'] ?? null);
        $this->assertNotSame('Abu Dhabi', $item['widget']['settings']['location'] ?? null);
    }

    public function test_empty_widget_props_fall_back_to_team_app_defaults(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Abu Dhabi',
                    'latitude' => 24.4,
                    'longitude' => 54.3,
                ]],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 31,
                    'weather_code' => 1,
                ],
            ]),
            'example.com/campus.xml' => Http::response(
                '<?xml version="1.0"?><rss><channel><item><title>Campus notice</title></item></channel></rss>',
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);

        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->patch(route('apps.update', $team), [
                'apps' => $this->payload([
                    'weather' => [
                        'enabled' => true,
                        'location' => 'Abu Dhabi',
                        'units' => 'celsius',
                    ],
                    'rss' => [
                        'enabled' => true,
                        'feed_url' => 'https://example.com/campus.xml',
                        'limit' => 3,
                    ],
                ]),
            ])
            ->assertSessionHasNoErrors();

        [$screen, $token] = $this->pairedScreen($team);
        $this->attachWidget($screen, 'weather', ['location' => '', 'units' => '']);
        $this->attachWidget($screen, 'rss', ['feed_url' => '', 'limit' => '']);

        $items = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items');

        $weather = collect($items)->firstWhere('widget_key', 'weather');
        $rss = collect($items)->firstWhere('widget_key', 'rss');

        $this->assertSame('Abu Dhabi', $weather['widget']['data']['place']);
        $this->assertSame(31, $weather['widget']['data']['temperature']);
        $this->assertSame('Campus notice', $rss['widget']['data']['items'][0]['title']);
    }

    public function test_json_api_token_is_not_returned_to_the_browser(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->patch(route('apps.update', $team), [
                'apps' => $this->payload([
                    'json_api' => [
                        'enabled' => true,
                        'url' => 'https://example.com/api.json',
                        'token' => 'super-secret-token',
                    ],
                ]),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'super-secret-token',
            $team->fresh()->settings['integrations']['json_api']['token'],
        );

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('apps.edit', $team))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('settings/apps')->where('canManage', true);

                $json = collect($page->toArray()['props']['apps'] ?? [])->firstWhere('key', 'json_api');

                $this->assertIsArray($json);
                $this->assertSame('', $json['values']['token']);
                $this->assertTrue($json['values']['has_token']);
                $this->assertSame('https://example.com/api.json', $json['values']['url']);
            });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides): array
    {
        $apps = [];

        foreach (['weather', 'rss', 'news', 'youtube', 'web_page', 'json_api', 'qr_code', 'calendar', 'booking', 'social_wall'] as $key) {
            $apps[$key] = array_merge(['enabled' => false], $overrides[$key] ?? []);
        }

        return $apps;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function attachWidget(Screen $screen, string $key, array $settings): void
    {
        $playlist = Playlist::query()->where('team_id', $screen->team_id)->whereNotNull('published_at')->first()
            ?? Playlist::factory()->published()->create(['team_id' => $screen->team_id]);

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

        if ($screen->current_channel_id === null) {
            $channel = Channel::factory()->create([
                'team_id' => $screen->team_id,
                'playlist_id' => $playlist->id,
                'type' => ChannelType::Playlist,
            ]);
            $screen->forceFill(['current_channel_id' => $channel->id])->save();
        }
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreen(Team $team): array
    {
        $plain = 'apps-device-token-'.fake()->uuid();
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => 'Asia/Dubai',
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain];
    }
}
