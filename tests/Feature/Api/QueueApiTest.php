<?php

namespace Tests\Feature\Api;

use App\Enums\ApiScope;
use App\Enums\AuditAction;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_read_api_is_scoped_tenant_isolated_and_logged(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'average_service_duration_seconds' => 180,
        ]);
        QueueTicket::factory()->forService($service)->create();
        $foreign = QueueTicket::factory()->create();
        [$token, $plain] = ApiToken::issue($user, $team, 'Queue reader', [ApiScope::QueueRead->value]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.23'])
            ->withToken($plain)
            ->getJson('/api/v1/queue/services')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Registration')
            ->assertJsonPath('data.0.waiting_count', 1)
            ->assertJsonPath('data.0.estimated_wait_seconds', 180);

        $this->withToken($plain)
            ->getJson('/api/v1/queue/tickets/'.$foreign->id)
            ->assertNotFound();

        $this->withToken($plain)
            ->postJson('/api/v1/queue/tickets', ['queue_service_id' => $service->id])
            ->assertForbidden();

        $log = AuditLog::query()
            ->where('action', AuditAction::QueueApiRequested)
            ->where('resource_type', 'api_token')
            ->where('resource_id', $token->id)
            ->where('ip_address', '198.51.100.23')
            ->firstOrFail();

        $this->assertSame('GET', $log->after['method']);
        $this->assertSame('/api/v1/queue/services', $log->after['path']);
        $this->assertSame(200, $log->after['status']);
    }

    public function test_queue_write_api_validates_issues_and_cancels_tickets(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id, 'ticket_prefix' => 'A']);
        [, $plain] = ApiToken::issue($user, $team, 'Queue writer', [ApiScope::QueueWrite->value]);

        $this->withToken($plain)
            ->postJson('/api/v1/queue/tickets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('queue_service_id');

        $validationLog = AuditLog::query()
            ->where('action', AuditAction::QueueApiRequested)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(422, $validationLog->after['status']);

        $response = $this->withToken($plain)
            ->postJson('/api/v1/queue/tickets', [
                'queue_service_id' => $service->id,
                'customer_name' => 'API Customer',
                'customer_email' => 'api@example.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.number', 'A001')
            ->assertJsonPath('data.status', QueueTicketStatus::Waiting->value)
            ->assertJsonPath('data.source', QueueTicketSource::Api->value)
            ->assertJsonPath('data.customer.email', 'api@example.test');

        $ticket = QueueTicket::query()
            ->where('team_id', $team->id)
            ->where('number', 'A001')
            ->firstOrFail();
        $this->assertSame(QueueTicketSource::Api, $ticket->source);

        $this->withToken($plain)
            ->getJson('/api/v1/queue/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('data.events.0.type', 'created');

        $this->withToken($plain)
            ->postJson('/api/v1/queue/tickets/'.$ticket->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', QueueTicketStatus::Cancelled->value);

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'action' => AuditAction::QueueTicketCreated->value,
            'resource_id' => $ticket->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::QueueTicketCancelled->value,
            'resource_id' => $ticket->id,
        ]);
    }

    public function test_queue_api_operates_recall_transfer_and_complete_lifecycle(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $registration = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'REG']);
        $billing = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'BIL']);
        $registrationCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'REG']);
        $billingCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'BIL']);
        $registrationCounter->services()->attach($registration);
        $billingCounter->services()->attach($billing);
        $ticket = QueueTicket::factory()->forService($registration)->create();
        $nextTicket = QueueTicket::factory()->forService($registration)->create([
            'number' => 'REG999',
            'sequence' => 999,
            'created_at' => now()->addSecond(),
        ]);
        [, $plain] = ApiToken::issue($user, $team, 'Queue operator', [ApiScope::QueueWrite->value]);

        $this->withToken($plain)
            ->postJson('/api/v1/tickets/'.$ticket->id.'/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket');

        $this->withToken($plain)
            ->postJson('/api/v1/counters/'.$registrationCounter->id.'/next')
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.status', QueueTicketStatus::Serving->value);

        $this->withToken($plain)
            ->postJson('/api/v1/counters/'.$registrationCounter->id.'/next')
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id);
        $this->assertSame(QueueTicketStatus::Waiting, $nextTicket->fresh()->status);

        $this->withToken($plain)
            ->postJson('/api/v1/tickets/'.$ticket->id.'/recall')
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id);

        $this->withToken($plain)
            ->postJson('/api/v1/tickets/'.$ticket->id.'/transfer', [
                'queue_service_id' => $billing->id,
                'counter_id' => $billingCounter->id,
                'reason' => 'Registration complete',
            ])
            ->assertOk()
            ->assertJsonPath('data.queue_service_id', $billing->id)
            ->assertJsonPath('data.status', QueueTicketStatus::Waiting->value);

        $this->withToken($plain)
            ->postJson('/api/v1/counters/'.$billingCounter->id.'/next')
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id);

        $this->withToken($plain)
            ->postJson('/api/v1/tickets/'.$ticket->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', QueueTicketStatus::Completed->value);

        foreach ([
            AuditAction::QueueTicketCalled,
            AuditAction::QueueTicketRecalled,
            AuditAction::QueueTicketTransferred,
            AuditAction::QueueTicketCompleted,
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action->value,
                'resource_type' => 'queue_ticket',
                'resource_id' => $ticket->id,
                'user_id' => $user->id,
            ]);
        }
    }

    public function test_ticket_issue_api_returns_the_original_ticket_for_an_idempotent_replay(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'I',
        ]);
        [, $plain] = ApiToken::issue($user, $team, 'Reliable issuer', [ApiScope::QueueWrite->value]);
        $payload = ['queue_service_id' => $service->id];

        $first = $this->withHeader('Idempotency-Key', 'api-request-99')
            ->withToken($plain)
            ->postJson('/api/v1/queue/tickets', $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'api-request-99')
            ->withToken($plain)
            ->postJson('/api/v1/queue/tickets', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_ticket_events', 1);
    }

    public function test_queue_status_returns_live_tenant_metrics_without_customer_pii(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        QueueTicket::factory()->forService($service)->create(['customer_name' => 'Private Name']);
        [, $plain] = ApiToken::issue($user, $team, 'Queue reader', [ApiScope::QueueRead->value]);

        $this->withToken($plain)
            ->getJson('/api/v1/queue/status')
            ->assertOk()
            ->assertJsonPath('data.stats.waiting', 1)
            ->assertJsonPath('data.stats.longest_waiting.service_name', $service->name)
            ->assertJsonMissingPath('data.stats.longest_waiting.customer_name')
            ->assertJsonPath('data.services.0.id', $service->id);
    }

    public function test_queue_api_uses_the_partner_token_rate_limit(): void
    {
        config(['partner.rate_per_minute' => 2]);
        $user = User::factory()->create();
        [, $plain] = ApiToken::issue(
            $user,
            $user->currentTeam,
            'Rate limited queue reader',
            [ApiScope::QueueRead->value],
        );

        $this->withToken($plain)->getJson('/api/v1/queue/services')->assertOk();
        $this->withToken($plain)->getJson('/api/v1/queue/status')->assertOk();
        $this->withToken($plain)->getJson('/api/v1/queue/services')->assertTooManyRequests();
    }

    public function test_token_scope_does_not_bypass_the_owners_role_permissions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::ReportingUser->value]);
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        [, $plain] = ApiToken::issue($member, $team, 'Read-only user token', [ApiScope::QueueWrite->value]);

        $this->withToken($plain)
            ->getJson('/api/v1/queue/services')
            ->assertOk();

        $this->withToken($plain)
            ->postJson('/api/v1/queue/tickets', ['queue_service_id' => $service->id])
            ->assertForbidden();

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_openapi_documents_queue_endpoints_and_scopes(): void
    {
        $response = $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonFragment(['value' => ApiScope::QueueRead->value])
            ->assertJsonFragment(['value' => ApiScope::QueueWrite->value]);

        $paths = $response->json('paths');
        $this->assertSame('List queue services', $paths['/api/v1/queue/services']['get']['summary']);
        $this->assertSame('Issue a queue ticket', $paths['/api/v1/queue/tickets']['post']['summary']);
    }
}
