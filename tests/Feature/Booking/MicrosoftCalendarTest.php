<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Models\CalendarConnection;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MicrosoftCalendarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Graph events keyed by mailbox, served by the fake calendarView.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $events = [];

    protected bool $rejectSignIn = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
        $this->fakeGraph();
    }

    protected function fakeGraph(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'login.microsoftonline.com')) {
                return $this->rejectSignIn
                    ? Http::response([
                        'error' => 'invalid_client',
                        'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: abc",
                    ], 401)
                    : Http::response(['access_token' => 'token-123', 'expires_in' => 3600]);
            }

            if (str_contains($url, '/places/microsoft.graph.room')) {
                return Http::response(['value' => [
                    ['id' => 'r1', 'displayName' => 'Boardroom', 'emailAddress' => 'Boardroom@contoso.com', 'capacity' => 12, 'building' => 'HQ'],
                    ['id' => 'r2', 'displayName' => 'Huddle', 'emailAddress' => 'huddle@contoso.com', 'capacity' => 4],
                ]]);
            }

            if (preg_match('#/users/([^/]+)/calendarView#', $url, $match)) {
                return Http::response(['value' => $this->events[urldecode($match[1])] ?? []]);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/events')) {
                return Http::response(['id' => 'created-event-1', 'changeKey' => 'ck-1'], 201);
            }

            if ($request->method() === 'PATCH' && str_contains($url, '/events/')) {
                return Http::response(['id' => 'created-event-1', 'changeKey' => 'ck-2']);
            }

            if ($request->method() === 'DELETE' && str_contains($url, '/events/')) {
                return Http::response(null, 204);
            }

            if (str_contains($url, 'graph.microsoft.com')) {
                return Http::response(['error' => ['message' => 'Unexpected request '.$url]], 500);
            }

            // Anything else (such as Inertia's SSR renderer) is not ours to fake.
            return null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function graphEvent(string $id, string $subject, string $start, string $end, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'changeKey' => $id.'-v1',
            'subject' => $subject,
            'start' => ['dateTime' => "2026-09-28T{$start}:00.0000000", 'timeZone' => 'UTC'],
            'end' => ['dateTime' => "2026-09-28T{$end}:00.0000000", 'timeZone' => 'UTC'],
            'organizer' => ['emailAddress' => ['name' => 'Priya Shah', 'address' => 'priya@contoso.com']],
            'isCancelled' => false,
            'showAs' => 'busy',
        ], $overrides);
    }

    protected function linkedRoom(): MeetingRoom
    {
        $connection = CalendarConnection::factory()->create();

        return MeetingRoom::factory()->linkedToMicrosoft($connection)->create();
    }

    public function test_admins_save_credentials_with_the_secret_encrypted(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($owner)
            ->put(route('bookings.microsoft.update', $team), [
                'tenant_id' => 'contoso.onmicrosoft.com',
                'client_id' => '9b2c8f3e-1111-2222-3333-444455556666',
                'client_secret' => 'super-secret-value',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $connection = CalendarConnection::query()->sole();
        $this->assertSame('super-secret-value', $connection->client_secret);
        $this->assertNotSame('super-secret-value', DB::table('calendar_connections')->value('client_secret'));

        // A blank secret on a later save keeps the stored one.
        $this->actingAs($owner)
            ->put(route('bookings.microsoft.update', $team), [
                'tenant_id' => 'contoso.onmicrosoft.com',
                'client_id' => '9b2c8f3e-1111-2222-3333-444455556666',
                'client_secret' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('super-secret-value', $connection->refresh()->client_secret);
        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('bookings.microsoft.show', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/microsoft')
                ->where('connection.has_secret', true)
                ->missing('connection.client_secret'))
            ->assertDontSee('super-secret-value');
    }

    public function test_the_room_picker_lists_tenant_room_mailboxes(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        CalendarConnection::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)
            ->getJson(route('bookings.microsoft.rooms', $team))
            ->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->assertJsonPath('rooms.0.name', 'Boardroom')
            ->assertJsonPath('rooms.0.capacity', 12);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth2/v2.0/token')
            && $request['grant_type'] === 'client_credentials'
            && $request['scope'] === 'https://graph.microsoft.com/.default');
    }

    public function test_importing_rooms_links_mailboxes_and_pulls_their_meetings(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        CalendarConnection::factory()->create(['team_id' => $team->id]);
        $existing = MeetingRoom::factory()->create(['team_id' => $team->id, 'name' => 'Huddle space']);
        $this->events['boardroom@contoso.com'] = [$this->graphEvent('evt-1', 'Quarterly review', '09:00', '10:00')];

        $this->actingAs($owner)
            ->post(route('bookings.microsoft.import', $team), [
                'rooms' => [
                    ['email' => 'Boardroom@contoso.com', 'name' => 'Boardroom', 'capacity' => 12],
                    ['email' => 'huddle@contoso.com', 'name' => 'Huddle', 'meeting_room_id' => $existing->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $boardroom = MeetingRoom::query()->where('name', 'Boardroom')->sole();
        $this->assertSame('boardroom@contoso.com', $boardroom->external_calendar_id);
        $this->assertSame('huddle@contoso.com', $existing->refresh()->external_calendar_id);

        $booking = RoomBooking::query()->where('meeting_room_id', $boardroom->id)->sole();
        $this->assertSame('Quarterly review', $booking->title);
        $this->assertSame(RoomBookingSource::Microsoft365, $booking->source);
        $this->assertSame('Priya Shah', $booking->organizer_name);
    }

    public function test_sync_follows_outlook_changes_and_cancellations(): void
    {
        $room = $this->linkedRoom();
        $mailbox = (string) $room->external_calendar_id;
        $this->events[$mailbox] = [
            $this->graphEvent('evt-1', 'Design review', '09:00', '10:00'),
            $this->graphEvent('evt-2', 'Retro', '14:00', '15:00'),
            $this->graphEvent('evt-free', 'Focus time', '16:00', '17:00', ['showAs' => 'free']),
        ];

        $this->artisan('bookings:sync-microsoft')->assertSuccessful();
        $this->assertSame(2, RoomBooking::query()->blocking()->count());

        // Outlook moves one meeting and deletes the other.
        $this->events[$mailbox] = [
            $this->graphEvent('evt-1', 'Design review (moved)', '11:00', '12:00', ['changeKey' => 'evt-1-v2']),
        ];

        $this->artisan('bookings:sync-microsoft')->assertSuccessful();

        $moved = RoomBooking::query()->where('external_id', 'evt-1')->sole();
        $this->assertSame('Design review (moved)', $moved->title);
        $this->assertSame('11:00', $moved->starts_at->utc()->format('H:i'));
        $this->assertSame(RoomBookingStatus::Cancelled, RoomBooking::query()->where('external_id', 'evt-2')->sole()->status);
        $this->assertNotNull($room->calendarConnection->refresh()->last_synced_at);
    }

    public function test_bookings_made_here_are_written_to_the_room_calendar(): void
    {
        $room = $this->linkedRoom();
        $owner = User::factory()->create();
        $team = $room->team;
        $team->members()->attach($owner, ['role' => 'owner']);
        $owner->switchTeam($team);

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), [
                'meeting_room_id' => $room->id,
                'title' => 'Vendor demo',
                'date' => '2026-09-28',
                'start_time' => '13:00',
                'end_time' => '14:00',
                'organizer_email' => 'host@contoso.com',
            ])
            ->assertSessionHasNoErrors();

        $booking = RoomBooking::query()->sole();
        $this->assertSame('created-event-1', $booking->external_id);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/users/'.rawurlencode((string) $room->external_calendar_id).'/events')
            && $request['subject'] === 'Vendor demo'
            && $request['start']['dateTime'] === '2026-09-28T13:00:00'
            && $request['start']['timeZone'] === 'UTC'
            && $request['attendees'][0]['emailAddress']['address'] === 'host@contoso.com');

        $this->actingAs($owner)->post(route('bookings.cancel', [$team, $booking]))->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/events/created-event-1'));
        $this->assertSame(RoomBookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_a_meeting_booked_in_outlook_seconds_ago_blocks_the_slot(): void
    {
        $room = $this->linkedRoom();
        $owner = User::factory()->create();
        $team = $room->team;
        $team->members()->attach($owner, ['role' => 'owner']);
        $owner->switchTeam($team);
        $this->events[(string) $room->external_calendar_id] = [
            $this->graphEvent('evt-new', 'Just booked in Outlook', '13:30', '14:30'),
        ];

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), [
                'meeting_room_id' => $room->id,
                'title' => 'Vendor demo',
                'date' => '2026-09-28',
                'start_time' => '13:00',
                'end_time' => '14:00',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('room_bookings', 0);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/events'));
    }

    public function test_failed_sign_in_is_reported_on_the_connection(): void
    {
        $this->rejectSignIn = true;

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $connection = CalendarConnection::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)->post(route('bookings.microsoft.test', $team))->assertRedirect();

        $this->assertStringContainsString('Invalid client secret', (string) $connection->refresh()->last_error);
        $this->assertStringNotContainsString('Trace ID', (string) $connection->last_error);
    }
}
