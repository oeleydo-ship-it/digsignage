<?php

namespace Tests\Feature\Schedule;

use App\Actions\Player\BuildPlayerManifest;
use App\Actions\Schedule\ResolveSchedule;
use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Enums\ScheduleTargetType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_daypart_schedules(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $channel = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Breakfast']);
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => 'UTC',
        ]);

        $this->actingAs($user)
            ->post(route('schedules.store', $team), $this->payload($channel, $screen, [
                'name' => 'Breakfast Menu',
                'start_time' => '06:00',
                'end_time' => '11:00',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('schedules', [
            'team_id' => $team->id,
            'name' => 'Breakfast Menu',
            'start_time' => '06:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_higher_priority_wins_when_windows_overlap(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $breakfast = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Breakfast']);
        $promo = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Promo']);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($breakfast, $screen, [
            'name' => 'Breakfast Menu',
            'start_time' => '06:00',
            'end_time' => '11:00',
            'priority' => 100,
        ]))->assertRedirect();

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($promo, $screen, [
            'name' => 'Morning Promo',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'priority' => 200,
        ]))->assertRedirect();

        $resolved = app(ResolveSchedule::class)->handle(
            $screen->fresh(),
            CarbonImmutable::parse('2026-09-18 09:00:00', 'UTC'),
        );

        $this->assertSame('schedule', $resolved->source);
        $this->assertSame($promo->id, $resolved->channelId);
        $this->assertSame('Morning Promo', $resolved->scheduleName);
    }

    public function test_same_priority_overlap_is_rejected(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $breakfast = Channel::factory()->create(['team_id' => $team->id]);
        $lunch = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($breakfast, $screen, [
            'name' => 'Breakfast Menu',
            'start_time' => '06:00',
            'end_time' => '11:00',
            'priority' => 100,
        ]))->assertRedirect();

        $this->actingAs($user)
            ->from(route('schedules.index', $team))
            ->post(route('schedules.store', $team), $this->payload($lunch, $screen, [
                'name' => 'Brunch',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'priority' => 100,
            ]))
            ->assertSessionHasErrors('priority');
    }

    public function test_adjacent_dayparts_are_allowed(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $breakfast = Channel::factory()->create(['team_id' => $team->id]);
        $lunch = Channel::factory()->create(['team_id' => $team->id]);
        $dinner = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($breakfast, $screen, [
            'name' => 'Breakfast Menu',
            'start_time' => '06:00',
            'end_time' => '11:00',
        ]))->assertRedirect();

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($lunch, $screen, [
            'name' => 'Lunch Menu',
            'start_time' => '11:00',
            'end_time' => '16:00',
        ]))->assertRedirect();

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($dinner, $screen, [
            'name' => 'Dinner Menu',
            'start_time' => '16:00',
            'end_time' => '23:00',
        ]))->assertRedirect();

        $resolver = app(ResolveSchedule::class);
        $this->assertSame($breakfast->id, $resolver->handle($screen->fresh(), CarbonImmutable::parse('2026-09-18 10:59:00', 'UTC'))->channelId);
        $this->assertSame($lunch->id, $resolver->handle($screen->fresh(), CarbonImmutable::parse('2026-09-18 11:00:00', 'UTC'))->channelId);
        $this->assertSame($dinner->id, $resolver->handle($screen->fresh(), CarbonImmutable::parse('2026-09-18 18:00:00', 'UTC'))->channelId);
    }

    public function test_screen_timezone_is_used_for_dayparts(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => 'America/New_York',
        ]);
        $channel = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($channel, $screen, [
            'name' => 'Breakfast Menu',
            'timezone' => 'UTC',
            'start_time' => '06:00',
            'end_time' => '11:00',
        ]))->assertRedirect();

        $resolver = app(ResolveSchedule::class);
        $atUtcMorning = CarbonImmutable::parse('2026-09-18 14:00:00', 'UTC');
        $this->assertSame($channel->id, $resolver->handle($screen->fresh(), $atUtcMorning)->channelId);

        $atUtcNight = CarbonImmutable::parse('2026-09-18 04:00:00', 'UTC');
        $this->assertNull($resolver->handle($screen->fresh(), $atUtcNight)->scheduleId);
    }

    public function test_location_schedules_include_descendant_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $city = Location::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $building = Location::factory()->create([
            'team_id' => $team->id,
            'parent_id' => $city->id,
        ]);
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'location_id' => $building->id,
            'timezone' => null,
        ]);
        $channel = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('schedules.store', $team), [
                ...$this->payload($channel, $screen, ['name' => 'City Loop']),
                'targets' => [
                    [
                        'target_type' => ScheduleTargetType::Location->value,
                        'location_id' => $city->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $resolved = app(ResolveSchedule::class)->handle(
            $screen->fresh(['location.parent']),
            CarbonImmutable::parse('2026-09-18 08:00:00', 'UTC'),
        );

        $this->assertSame($channel->id, $resolved->channelId);
    }

    public function test_group_schedules_apply_to_member_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $group = ScreenGroup::factory()->create(['team_id' => $team->id]);
        $group->screens()->attach($screen);
        $channel = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('schedules.store', $team), [
                ...$this->payload($channel, $screen, ['name' => 'Lobby Group']),
                'targets' => [
                    [
                        'target_type' => ScheduleTargetType::ScreenGroup->value,
                        'screen_group_id' => $group->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            $channel->id,
            app(ResolveSchedule::class)->handle(
                $screen->fresh(),
                CarbonImmutable::parse('2026-09-18 08:00:00', 'UTC'),
            )->channelId,
        );
    }

    public function test_user_cannot_assign_another_teams_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $userA->currentTeam->id]);
        $channel = Channel::factory()->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA)
            ->from(route('schedules.index', $userA->currentTeam))
            ->post(route('schedules.store', $userA->currentTeam), $this->payload($channel, $screen))
            ->assertSessionHasErrors('channel_id');
    }

    public function test_user_cannot_update_another_teams_schedule(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'channel_id' => Channel::factory()->create(['team_id' => $userB->currentTeam->id])->id,
            'name' => 'Secret',
        ]);

        $this->actingAs($userA)
            ->patch(route('schedules.update', [$userA->currentTeam, $schedule]), [
                'name' => 'Stolen',
                'content_type' => ScheduleContentType::Channel->value,
                'channel_id' => $schedule->channel_id,
                'timezone' => 'UTC',
                'starts_on' => now()->toDateString(),
                'start_time' => '06:00',
                'end_time' => '11:00',
                'recurrence' => ScheduleRecurrence::Daily->value,
                'targets' => [
                    [
                        'target_type' => ScheduleTargetType::Screen->value,
                        'screen_id' => Screen::factory()->create(['team_id' => $userA->currentTeam->id])->id,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'name' => 'Secret',
        ]);
    }

    public function test_members_cannot_create_schedules(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('schedules.store', $team), [
                'name' => 'No Access',
            ])
            ->assertForbidden();
    }

    public function test_playlist_schedules_resolve_without_a_channel(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $playlist = Playlist::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('schedules.store', $team), [
                'name' => 'Direct Playlist',
                'content_type' => ScheduleContentType::Playlist->value,
                'playlist_id' => $playlist->id,
                'timezone' => 'UTC',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'start_time' => '00:00',
                'end_time' => '23:59',
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

        $resolved = app(ResolveSchedule::class)->handle(
            $screen->fresh(),
            CarbonImmutable::parse('2026-09-18 12:00:00', 'UTC'),
        );

        $this->assertSame($playlist->id, $resolved->playlistId);
        $this->assertNull($resolved->channelId);
    }

    public function test_schedule_timezone_is_used_when_the_screen_has_none(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => null,
            'location_id' => null,
        ]);
        $channel = Channel::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($channel, $screen, [
            'name' => 'Breakfast Menu',
            'timezone' => 'America/New_York',
            'start_time' => '06:00',
            'end_time' => '11:00',
        ]))->assertRedirect();

        $resolver = app(ResolveSchedule::class);

        $this->assertSame(
            $channel->id,
            $resolver->handle($screen->fresh(), CarbonImmutable::parse('2026-09-18 14:00:00', 'UTC'))->channelId,
        );
        $this->assertNull(
            $resolver->handle($screen->fresh(), CarbonImmutable::parse('2026-09-18 09:00:00', 'UTC'))->scheduleId,
        );
    }

    public function test_disabled_and_deleted_schedules_stop_applying(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $fallback = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Default']);
        $breakfast = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Breakfast']);
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => 'UTC',
            'current_channel_id' => $fallback->id,
        ]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($breakfast, $screen, [
            'name' => 'Breakfast Menu',
        ]))->assertRedirect();

        $schedule = Schedule::query()->where('team_id', $team->id)->where('name', 'Breakfast Menu')->firstOrFail();
        $at = CarbonImmutable::parse('2026-09-18 08:00:00', 'UTC');

        $this->assertSame($breakfast->id, app(ResolveSchedule::class)->handle($screen->fresh(), $at)->channelId);

        $this->actingAs($user)
            ->from(route('schedules.index', $team))
            ->patch(route('schedules.update', [$team, $schedule]), $this->payload($breakfast, $screen, [
                'name' => 'Breakfast Menu',
                'is_enabled' => false,
            ]))
            ->assertRedirect();

        $disabled = app(ResolveSchedule::class)->handle($screen->fresh(), $at);
        $this->assertNull($disabled->scheduleId);
        $this->assertSame($fallback->id, $disabled->channelId);

        $this->actingAs($user)
            ->from(route('schedules.index', $team))
            ->patch(route('schedules.update', [$team, $schedule]), $this->payload($breakfast, $screen, [
                'name' => 'Breakfast Menu',
                'is_enabled' => true,
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('schedules.index', $team))
            ->delete(route('schedules.destroy', [$team, $schedule]))
            ->assertRedirect();

        $this->assertNull(app(ResolveSchedule::class)->handle($screen->fresh(), $at)->scheduleId);
    }

    public function test_creating_a_schedule_stays_on_the_schedule_page(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $channel = Channel::factory()->create(['team_id' => $team->id]);
        $screen = Screen::factory()->create(['team_id' => $team->id, 'timezone' => 'UTC']);
        $index = route('schedules.index', $team);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->post(route('schedules.store', $team), $this->payload($channel, $screen))
            ->assertRedirect($index);
    }

    public function test_invalid_create_does_not_dump_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $index = route('schedules.index', $team);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->post(route('schedules.store', $team), ['name' => ''])
            ->assertRedirect($index)
            ->assertSessionHasErrors('name');
    }

    public function test_week_query_shifts_the_calendar_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('schedules.index', [
                'current_team' => $user->currentTeam,
                'week' => '2026-09-23',
                'timezone' => 'UTC',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedules/index')
                ->where('week.start', '2026-09-21')
                ->where('week.end', '2026-09-27')
                ->where('week.days.0.date', '2026-09-21')
                ->where('week.days.6.date', '2026-09-27')
                ->where('filters.timezone', 'UTC')
            );
    }

    public function test_player_manifest_uses_the_schedule_inside_the_window(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $fallback = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Lobby Loop']);
        $breakfast = Channel::factory()->create(['team_id' => $team->id, 'name' => 'Breakfast']);
        $screen = Screen::factory()->create([
            'team_id' => $team->id,
            'timezone' => 'UTC',
            'current_channel_id' => $fallback->id,
        ]);

        $this->actingAs($user)->post(route('schedules.store', $team), $this->payload($breakfast, $screen, [
            'name' => 'Breakfast Menu',
            'start_time' => '06:00',
            'end_time' => '11:00',
        ]))->assertRedirect();

        $inside = app(BuildPlayerManifest::class)->handle(
            $screen->fresh(['currentChannel']),
            CarbonImmutable::parse('2026-09-18 09:00:00', 'UTC'),
        );
        $this->assertSame('schedule', $inside['playback']['source']);
        $this->assertSame('Breakfast', $inside['playback']['channel']['name']);
        $this->assertSame('Breakfast Menu', $inside['playback']['schedule_name']);

        $outside = app(BuildPlayerManifest::class)->handle(
            $screen->fresh(['currentChannel']),
            CarbonImmutable::parse('2026-09-18 18:00:00', 'UTC'),
        );
        $this->assertSame('fallback', $outside['playback']['source']);
        $this->assertSame('Lobby Loop', $outside['playback']['channel']['name']);
        $this->assertNull($outside['playback']['schedule_id']);
    }

    public function test_calendar_is_isolated_to_the_current_team(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Schedule::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'channel_id' => Channel::factory()->create(['team_id' => $userB->currentTeam->id])->id,
            'name' => 'Other team window',
        ]);

        $this->actingAs($userA)
            ->withoutVite()
            ->get(route('schedules.index', $userA->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedules/index')
                ->where('schedules', fn ($schedules) => collect($schedules)->pluck('name')->doesntContain('Other team window'))
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(Channel $channel, Screen $screen, array $overrides = []): array
    {
        return [
            'name' => 'Breakfast Menu',
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
            ...$overrides,
        ];
    }
}
