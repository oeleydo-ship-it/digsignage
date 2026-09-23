<?php

namespace Tests\Feature\Authorization;

use App\Enums\ChannelType;
use App\Enums\LocationType;
use App\Enums\PlaylistItemType;
use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Enums\ScheduleTargetType;
use App\Enums\ScreenOrientation;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Emergency;
use App\Models\Media;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_manage_core_signage_resources(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $admin->switchTeam($team);

        $this->actingAs($admin)
            ->post(route('screens.store', $team), [
                'name' => 'Admin Screen',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('locations.store', $team), [
                'name' => 'Admin Location',
                'type' => LocationType::City->value,
            ])
            ->assertRedirect();

        $media = Media::factory()->create(['team_id' => $team->id, 'duration' => 30]);

        $this->actingAs($admin)
            ->post(route('playlists.store', $team), [
                'name' => 'Admin Playlist',
                'loop' => true,
                'items' => [
                    [
                        'type' => PlaylistItemType::Media->value,
                        'title' => 'Clip',
                        'duration_seconds' => 30,
                        'media_id' => $media->id,
                        'enabled' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('channels.store', $team), [
                'name' => 'Admin Channel',
                'type' => ChannelType::Playlist->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('screens', ['team_id' => $team->id, 'name' => 'Admin Screen']);
        $this->assertDatabaseHas('locations', ['team_id' => $team->id, 'name' => 'Admin Location']);
        $this->assertDatabaseHas('playlists', ['team_id' => $team->id, 'name' => 'Admin Playlist']);
        $this->assertDatabaseHas('channels', ['team_id' => $team->id, 'name' => 'Admin Channel']);
    }

    public function test_admins_can_manage_billing_but_cannot_delete_the_team(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $admin->switchTeam($team);

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('billing.index', $team))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('teams.destroy', $team))
            ->assertForbidden();
    }

    public function test_content_managers_can_create_content_but_cannot_start_emergencies(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($manager, ['role' => TeamRole::ContentManager->value]);
        $manager->switchTeam($team);

        $this->actingAs($manager)
            ->post(route('playlists.store', $team), [
                'name' => 'Manager Playlist',
                'loop' => true,
                'items' => [],
            ])
            ->assertRedirect();

        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $this->actingAs($manager)
            ->post(route('emergencies.store', $team), [
                'title' => 'Manager alert',
                'severity' => 'emergency',
                'screen_ids' => [$screen->id],
                'location_ids' => [],
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('emergencies.store', $team), [
                'title' => 'Owner alert',
                'severity' => 'emergency',
                'screen_ids' => [$screen->id],
                'location_ids' => [],
            ])
            ->assertRedirect();

        $emergency = Emergency::query()->where('title', 'Owner alert')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('emergencies.start', [$team, $emergency]), ['confirmed' => true])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('schedules.store', $team), [
                'name' => 'Manager Schedule',
                'content_type' => ScheduleContentType::Channel->value,
                'channel_id' => Channel::factory()->create(['team_id' => $team->id])->id,
                'timezone' => 'UTC',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'start_time' => '06:00',
                'end_time' => '11:00',
                'recurrence' => ScheduleRecurrence::Daily->value,
                'priority' => 100,
                'is_enabled' => true,
                'targets' => [
                    [
                        'target_type' => ScheduleTargetType::Screen->value,
                        'screen_id' => $screen->id,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_publishers_can_create_schedules_but_not_playlists(): void
    {
        $owner = User::factory()->create();
        $publisher = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($publisher, ['role' => TeamRole::Publisher->value]);
        $publisher->switchTeam($team);

        $channel = Channel::factory()->create(['team_id' => $team->id]);
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $this->actingAs($publisher)
            ->post(route('playlists.store', $team), [
                'name' => 'Publisher Playlist',
                'loop' => true,
                'items' => [],
            ])
            ->assertForbidden();

        $this->actingAs($publisher)
            ->post(route('schedules.store', $team), [
                'name' => 'Publisher Schedule',
                'content_type' => ScheduleContentType::Channel->value,
                'channel_id' => $channel->id,
                'timezone' => 'UTC',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'start_time' => '06:00',
                'end_time' => '11:00',
                'recurrence' => ScheduleRecurrence::Daily->value,
                'priority' => 100,
                'is_enabled' => true,
                'targets' => [
                    [
                        'target_type' => ScheduleTargetType::Screen->value,
                        'screen_id' => $screen->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schedules', [
            'team_id' => $team->id,
            'name' => 'Publisher Schedule',
        ]);
    }
}
