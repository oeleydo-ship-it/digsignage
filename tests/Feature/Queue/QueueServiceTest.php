<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Enums\TeamRole;
use App\Models\Location;
use App\Models\QueueService;
use App\Models\User;
use App\Support\QueueOpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Registration',
            'code' => 'REG',
            'ticket_prefix' => 'A',
            'description' => 'Front desk',
            'location_id' => null,
            'opening_hours' => QueueOpeningHours::defaults(),
            'average_service_duration_seconds' => 300,
            'max_queue_capacity' => 50,
            'numbering_reset' => QueueNumberingReset::Daily->value,
            'default_priority' => 0,
            'priority_rules' => [],
            'display_color' => '#2563eb',
            'queue_strategy' => QueueStrategy::Fifo->value,
            'is_active' => true,
        ], $overrides);
    }

    public function test_owners_can_create_and_list_services(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create([
            'team_id' => $team->id,
            'name' => 'Lobby',
        ]);

        $this->actingAs($user)
            ->post(route('queue.services.store', $team), $this->payload([
                'location_id' => $location->id,
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('queue.services', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/services')
                ->has('services', 1)
                ->where('services.0.name', 'Registration')
                ->where('services.0.code', 'REG')
                ->where('services.0.ticket_prefix', 'A')
                ->where('services.0.location_name', 'Lobby')
                ->where('services.0.next_ticket_number', 'A001')
                ->where('services.0.queue_strategy', 'fifo')
                ->where('permissions.canManageQueue', true));

        $this->assertDatabaseHas('queue_services', [
            'team_id' => $team->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
            'average_service_duration_seconds' => 300,
        ]);
    }

    public function test_code_and_prefix_are_unique_per_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        QueueService::factory()->create([
            'team_id' => $team->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);

        $this->actingAs($user)
            ->post(route('queue.services.store', $team), $this->payload([
                'name' => 'Other',
                'code' => 'REG',
                'ticket_prefix' => 'Z',
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($user)
            ->post(route('queue.services.store', $team), $this->payload([
                'name' => 'Other',
                'code' => 'OTH',
                'ticket_prefix' => 'A',
            ]))
            ->assertSessionHasErrors('ticket_prefix');
    }

    public function test_another_team_may_reuse_the_same_code_and_prefix(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        QueueService::factory()->create([
            'team_id' => $userA->currentTeam->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);

        $this->actingAs($userB)
            ->post(route('queue.services.store', $userB->currentTeam), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('queue_services', [
            'team_id' => $userB->currentTeam->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);
    }

    public function test_members_cannot_create_services(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('queue.services.store', $team), $this->payload())
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('queue.services', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', false));
    }

    public function test_user_cannot_update_another_teams_service(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $serviceB = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret',
            'code' => 'SEC',
            'ticket_prefix' => 'S',
        ]);

        $this->actingAs($userA)
            ->patch(route('queue.services.update', [$userA->currentTeam, $serviceB]), $this->payload([
                'name' => 'Hijacked',
                'code' => 'HIJ',
                'ticket_prefix' => 'H',
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('queue_services', [
            'id' => $serviceB->id,
            'name' => 'Secret',
            'code' => 'SEC',
        ]);
    }

    public function test_location_must_belong_to_the_same_team(): void
    {
        $user = User::factory()->create();
        $foreign = Location::factory()->create();

        $this->actingAs($user)
            ->post(route('queue.services.store', $user->currentTeam), $this->payload([
                'location_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('location_id');
    }

    public function test_owners_can_deactivate_and_delete_services(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Billing',
            'code' => 'BIL',
            'ticket_prefix' => 'B',
        ]);

        $this->actingAs($user)
            ->patch(route('queue.services.update', [$team, $service]), $this->payload([
                'name' => 'Billing',
                'code' => 'BIL',
                'ticket_prefix' => 'B',
                'is_active' => false,
            ]))
            ->assertRedirect();

        $this->assertFalse($service->fresh()->is_active);

        $this->actingAs($user)
            ->delete(route('queue.services.destroy', [$team, $service]))
            ->assertRedirect();

        $this->assertDatabaseMissing('queue_services', [
            'id' => $service->id,
        ]);
    }

    public function test_next_ticket_number_previews_a_period_rollover(): void
    {
        $service = QueueService::factory()->create([
            'ticket_prefix' => 'A',
            'numbering_reset' => QueueNumberingReset::Daily,
            'next_sequence' => 42,
            'sequence_period' => '2026-09-20',
        ]);

        $this->assertSame('A042', $service->nextTicketNumber(CarbonImmutable::parse('2026-09-20')));
        $this->assertSame('A001', $service->nextTicketNumber(CarbonImmutable::parse('2026-09-21')));
        $this->assertSame(42, $service->fresh()->next_sequence);
    }
}
