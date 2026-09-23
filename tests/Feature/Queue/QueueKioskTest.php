<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\Location;
use App\Models\QueueKiosk;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueKioskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Lobby kiosk',
            'location_id' => null,
            'printer_enabled' => false,
            'is_active' => true,
            'pin' => null,
            'branding' => [
                'logo_url' => null,
                'title' => 'WELCOME',
                'footer' => 'Thank you',
                'print_qr_code' => true,
                'colors' => [
                    'background' => '#0f172a',
                    'primary' => '#2563eb',
                    'text' => '#f8fafc',
                    'button_text' => '#ffffff',
                ],
            ],
        ], $overrides);
    }

    public function test_owners_can_create_and_list_kiosks_with_branding(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('queue.kiosks.store', $team), $this->payload())
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('queue.kiosks', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/kiosks')
                ->has('kiosks', 1)
                ->where('kiosks.0.name', 'Lobby kiosk')
                ->where('kiosks.0.branding.title', 'WELCOME')
                ->where('kiosks.0.branding.footer', 'Thank you')
                ->where('kiosks.0.branding.print_qr_code', true)
                ->where('kiosks.0.branding.colors.primary', '#2563eb')
                ->where('kiosks.0.printer_enabled', false)
                ->where('kiosks.0.is_active', true)
                ->where('permissions.canManageQueue', true));

        $this->assertDatabaseHas('queue_kiosks', [
            'team_id' => $team->id,
            'name' => 'Lobby kiosk',
        ]);
    }

    public function test_members_cannot_manage_kiosks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('queue.kiosks.store', $team), $this->payload())
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('queue.kiosks', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/kiosks')
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', false));
    }

    public function test_kiosks_are_isolated_between_teams(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        QueueKiosk::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Theirs',
        ]);

        $this->actingAs($userA)
            ->get(route('queue.kiosks', $userA->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('kiosks', 0));
    }

    public function test_guests_can_open_an_active_kiosk_by_token(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
        ]);
        $kiosk = QueueKiosk::factory()->create([
            'team_id' => $team->id,
            'branding' => [
                'title' => 'Please take a number',
                'footer' => 'Lobby',
                'colors' => ['primary' => '#111111'],
            ],
        ]);

        $this->get(route('queue.kiosk.serve', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/kiosk-serve')
                ->where('kiosk.branding.title', 'Please take a number')
                ->where('kiosk.branding.footer', 'Lobby')
                ->where('kiosk.team_name', $team->name)
                ->where('services.0.name', 'Registration')
                ->where('issuedTicket', null));
    }

    public function test_issuing_from_a_kiosk_increments_a001_with_kiosk_source(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'ticket_prefix' => 'A',
            'average_service_duration_seconds' => 120,
        ]);
        $kiosk = QueueKiosk::factory()->create(['team_id' => $team->id]);

        $this->post(route('queue.kiosk.tickets.store', $kiosk), [
            'queue_service_id' => $service->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'team_id' => $team->id,
            'queue_service_id' => $service->id,
            'number' => 'A001',
            'sequence' => 1,
            'status' => QueueTicketStatus::Waiting->value,
            'source' => QueueTicketSource::Kiosk->value,
        ]);

        $this->post(route('queue.kiosk.tickets.store', $kiosk), [
            'queue_service_id' => $service->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'number' => 'A002',
            'source' => QueueTicketSource::Kiosk->value,
        ]);

        $this->followingRedirects()
            ->from(route('queue.kiosk.serve', $kiosk))
            ->post(route('queue.kiosk.tickets.store', $kiosk), [
                'queue_service_id' => $service->id,
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/kiosk-serve')
                ->where('issuedTicket.number', 'A003')
                ->where('issuedTicket.people_ahead', 2)
                ->where('issuedTicket.estimated_wait_minutes', 4)
                ->where(
                    'issuedTicket.qr_value',
                    fn (string $value): bool => str_contains($value, 'Ticket: A003')
                        && str_contains($value, 'Service: Registration'),
                ));

        $this->assertSame(3, QueueTicket::query()->where('queue_service_id', $service->id)->count());
        $this->assertSame(4, $service->fresh()->next_sequence);
    }

    public function test_kiosk_ticket_isolation_rejects_another_teams_service(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $kiosk = QueueKiosk::factory()->create(['team_id' => $userA->currentTeam->id]);
        $foreignService = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'ticket_prefix' => 'Z',
        ]);

        $this->post(route('queue.kiosk.tickets.store', $kiosk), [
            'queue_service_id' => $foreignService->id,
        ])->assertSessionHasErrors('queue_service_id');

        $this->assertDatabaseMissing('queue_tickets', [
            'queue_service_id' => $foreignService->id,
        ]);
    }

    public function test_printed_ticket_qr_code_can_be_disabled_in_branding(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('queue.kiosks.store', $team), $this->payload([
                'branding' => [
                    ...$this->payload()['branding'],
                    'print_qr_code' => false,
                ],
            ]))
            ->assertRedirect();

        $kiosk = QueueKiosk::query()->where('team_id', $team->id)->firstOrFail();

        $this->assertFalse($kiosk->resolvedBranding()->printQrCode);

        $this->actingAs($user)
            ->get(route('queue.kiosks', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->where('kiosks.0.branding.print_qr_code', false));
    }

    public function test_inactive_kiosk_is_forbidden(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $kiosk = QueueKiosk::factory()->inactive()->create(['team_id' => $team->id]);

        $this->get(route('queue.kiosk.serve', $kiosk))->assertForbidden();

        $this->post(route('queue.kiosk.tickets.store', $kiosk), [
            'queue_service_id' => $service->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_kiosk_with_a_location_only_lists_services_there(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $here = Location::factory()->create(['team_id' => $team->id, 'name' => 'Dubai']);
        $there = Location::factory()->create(['team_id' => $team->id, 'name' => 'Sharjah']);
        $local = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'location_id' => $here->id,
        ]);
        QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Pharmacy',
            'location_id' => $there->id,
        ]);
        $kiosk = QueueKiosk::factory()->create([
            'team_id' => $team->id,
            'location_id' => $here->id,
        ]);

        $this->get(route('queue.kiosk.serve', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('services', 1)
                ->where('services.0.name', 'Registration'));

        $this->post(route('queue.kiosk.tickets.store', $kiosk), [
            'queue_service_id' => $local->id,
        ])->assertRedirect();
    }

    public function test_serve_page_does_not_require_staff_login(): void
    {
        $kiosk = QueueKiosk::factory()->create();

        $this->get(route('queue.kiosk.serve', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('queue/kiosk-serve'));

        $this->get(route('queue.kiosks', $kiosk->team))
            ->assertRedirect(route('login'));
    }
}
