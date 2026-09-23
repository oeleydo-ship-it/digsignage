<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\SelectNextQueueTicket;
use App\Enums\QueueStrategy;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueuePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifo_selects_the_oldest_waiting_ticket(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'queue_strategy' => QueueStrategy::Fifo,
            'ticket_prefix' => 'A',
        ]);

        $older = QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'priority' => 0,
            'created_at' => now()->subMinutes(10),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'priority' => 100,
            'created_at' => now()->subMinutes(1),
        ]);

        $next = app(SelectNextQueueTicket::class)->handle($team, $service);

        $this->assertNotNull($next);
        $this->assertSame($older->id, $next->id);
    }

    public function test_priority_strategy_selects_the_highest_weight_first(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'queue_strategy' => QueueStrategy::Priority,
            'ticket_prefix' => 'A',
        ]);

        $normal = QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'priority' => 0,
            'created_at' => now()->subMinutes(10),
        ]);
        $vip = QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'priority' => 30,
            'created_at' => now()->subMinutes(1),
        ]);

        $next = app(SelectNextQueueTicket::class)->handle($team, $service);

        $this->assertNotNull($next);
        $this->assertSame($vip->id, $next->id);
        $this->assertNotSame($normal->id, $next->id);
    }

    public function test_starvation_lets_an_old_normal_ticket_beat_a_newer_vip(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'queue_strategy' => QueueStrategy::Priority,
            'ticket_prefix' => 'A',
            'starvation' => [
                'max_priority_wait_seconds' => 60,
            ],
        ]);

        $normal = QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'priority' => 0,
            'created_at' => now()->subSeconds(120),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'priority' => 30,
            'created_at' => now()->subSeconds(10),
        ]);

        $next = app(SelectNextQueueTicket::class)->handle($team, $service);

        $this->assertNotNull($next);
        $this->assertSame($normal->id, $next->id);
    }

    public function test_visiting_settings_seeds_default_priorities_per_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('queue.settings', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/settings')
                ->has('priorities', 5)
                ->where('priorities.0.name', 'Normal')
                ->where('priorities.0.weight', 0)
                ->where('priorities.2.name', 'Senior')
                ->where('priorities.2.weight', 20)
                ->where('priorities.4.name', 'Emergency')
                ->where('priorities.4.weight', 100));

        $this->assertSame(5, QueuePriority::query()->where('team_id', $team->id)->count());
        $this->assertDatabaseHas('queue_priorities', [
            'team_id' => $team->id,
            'code' => 'senior',
            'weight' => 20,
        ]);
    }

    public function test_custom_priorities_are_isolated_per_team(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->get(route('queue.settings', $userA->currentTeam))
            ->assertOk();

        $this->actingAs($userA)
            ->post(route('queue.priorities.store', $userA->currentTeam), [
                'name' => 'Gold desk',
                'weight' => 40,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_priorities', [
            'team_id' => $userA->currentTeam->id,
            'name' => 'Gold desk',
            'weight' => 40,
        ]);

        $this->actingAs($userB)
            ->get(route('queue.settings', $userB->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('priorities', 5)
                ->where('priorities.0.name', 'Normal')
                ->missing('priorities.5'));

        $this->assertDatabaseMissing('queue_priorities', [
            'team_id' => $userB->currentTeam->id,
            'name' => 'Gold desk',
        ]);

        $foreign = QueuePriority::query()
            ->where('team_id', $userA->currentTeam->id)
            ->where('name', 'Gold desk')
            ->firstOrFail();

        $this->actingAs($userB)
            ->patch(route('queue.priorities.update', [$userB->currentTeam, $foreign]), [
                'name' => 'Hijacked',
                'weight' => 1,
            ])
            ->assertForbidden();
    }

    public function test_cannot_delete_the_last_remaining_priority(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('queue.settings', $team))
            ->assertOk();

        $priorities = QueuePriority::query()->where('team_id', $team->id)->get();
        $keep = $priorities->first();

        foreach ($priorities->skip(1) as $priority) {
            $this->actingAs($user)
                ->delete(route('queue.priorities.destroy', [$team, $priority]))
                ->assertRedirect();
        }

        $this->assertSame(1, QueuePriority::query()->where('team_id', $team->id)->count());

        $this->actingAs($user)
            ->delete(route('queue.priorities.destroy', [$team, $keep]))
            ->assertSessionHasErrors('priority');

        $this->assertDatabaseHas('queue_priorities', [
            'id' => $keep->id,
        ]);
    }

    public function test_members_cannot_manage_priorities(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($owner)
            ->get(route('queue.settings', $team))
            ->assertOk();

        $priority = QueuePriority::query()->where('team_id', $team->id)->firstOrFail();

        $this->actingAs($member)
            ->post(route('queue.priorities.store', $team), [
                'name' => 'Express',
                'weight' => 15,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('queue.priorities.update', [$team, $priority]), [
                'name' => $priority->name,
                'weight' => 99,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('queue.priorities.destroy', [$team, $priority]))
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('queue.settings.update', $team), [
                'max_priority_wait_seconds' => 120,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('queue.settings', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', false)
                ->has('priorities', 5));
    }

    public function test_issuing_a_ticket_copies_the_priority_weight(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
            'queue_strategy' => QueueStrategy::Priority,
        ]);

        $this->actingAs($user)
            ->get(route('queue.settings', $team))
            ->assertOk();

        $senior = QueuePriority::query()
            ->where('team_id', $team->id)
            ->where('code', 'senior')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
                'queue_priority_id' => $senior->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'queue_service_id' => $service->id,
            'queue_priority_id' => $senior->id,
            'priority' => 20,
            'status' => QueueTicketStatus::Waiting->value,
        ]);
    }

    public function test_team_starvation_settings_can_be_saved(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->patch(route('queue.settings.update', $team), [
                'max_priority_wait_seconds' => 300,
                'promote_after_seconds' => 90,
            ])
            ->assertRedirect();

        $settings = QueueSetting::resolveForTeam($team);

        $this->assertSame(300, $settings->settings['starvation']['max_priority_wait_seconds']);
        $this->assertSame(90, $settings->settings['starvation']['promote_after_seconds']);
    }
}
