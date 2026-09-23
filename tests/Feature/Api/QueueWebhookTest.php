<?php

namespace Tests\Feature\Api;

use App\Actions\Queue\CallNextQueueTicket;
use App\Actions\Queue\CancelQueueTicket;
use App\Actions\Queue\IssueQueueTicket;
use App\Actions\Queue\OperateQueueDesk;
use App\Enums\QueueTicketSource;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class QueueWebhookTest extends TestCase
{
    use DatabaseMigrations;

    public function test_every_queue_lifecycle_event_creates_queued_tenant_delivery_logs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $registration = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'REG']);
        $billing = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'BIL']);
        $registrationCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'REG']);
        $billingCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'BIL']);
        $registrationCounter->services()->attach($registration);
        $billingCounter->services()->attach($billing);
        $endpoint = WebhookEndpoint::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'events' => $this->queueEvents(),
        ]);
        WebhookEndpoint::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'events' => [WebhookEvent::ScreenOnline->value],
        ]);
        WebhookEndpoint::factory()->create([
            'events' => $this->queueEvents(),
        ]);

        $issue = app(IssueQueueTicket::class);
        $operate = app(OperateQueueDesk::class);
        $first = $issue->handle($team, $registration, [
            'source' => QueueTicketSource::Api,
            'customer_name' => 'Never in payload',
            'customer_email' => 'private@example.test',
        ], $user);
        app(CallNextQueueTicket::class)->handle($registrationCounter, $user);
        $operate->transfer(
            $registrationCounter,
            $billing,
            $user,
            $billingCounter,
            'Registration complete',
        );
        app(CallNextQueueTicket::class)->handle($billingCounter, $user);
        $operate->complete($billingCounter, $user);

        $second = $issue->handle($team, $registration, ['source' => QueueTicketSource::Staff], $user);
        app(CancelQueueTicket::class)->handle($second, $user);

        $third = $issue->handle($team, $billing, ['source' => QueueTicketSource::Staff], $user);
        app(CallNextQueueTicket::class)->handle($billingCounter, $user);
        $operate->noShow($billingCounter, $user);

        $expected = [
            WebhookEvent::TicketCreated->value => 3,
            WebhookEvent::TicketCalled->value => 3,
            WebhookEvent::TicketServing->value => 3,
            WebhookEvent::TicketTransferred->value => 1,
            WebhookEvent::TicketCompleted->value => 1,
            WebhookEvent::TicketCancelled->value => 1,
            WebhookEvent::TicketNoShow->value => 1,
        ];

        foreach ($expected as $event => $count) {
            $this->assertSame($count, WebhookDelivery::query()
                ->where('webhook_endpoint_id', $endpoint->id)
                ->where('event', $event)
                ->count());
        }

        $this->assertDatabaseCount('webhook_deliveries', 13);
        $this->assertSame(13, WebhookDelivery::query()->where('status', WebhookDeliveryStatus::Pending)->count());
        Queue::assertPushed(DeliverWebhook::class, 13);

        $created = WebhookDelivery::query()
            ->where('event', WebhookEvent::TicketCreated)
            ->whereJsonContains('payload->data->id', $first->id)
            ->firstOrFail();
        $this->assertSame(QueueTicketSource::Api->value, $created->payload['data']['source']);
        $this->assertArrayNotHasKey('customer_name', $created->payload['data']);
        $this->assertArrayNotHasKey('customer_email', $created->payload['data']);

        $transfer = WebhookDelivery::query()
            ->where('event', WebhookEvent::TicketTransferred)
            ->firstOrFail();
        $this->assertSame($registration->id, $transfer->payload['data']['previous_service_id']);
        $this->assertSame($billing->id, $transfer->payload['data']['new_service_id']);
        $this->assertSame('Registration complete', $transfer->payload['data']['reason']);
    }

    public function test_rolled_back_ticket_does_not_leave_a_delivery_or_job(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        WebhookEndpoint::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'events' => [WebhookEvent::TicketCreated->value],
        ]);

        try {
            DB::transaction(function () use ($team, $service, $user): void {
                app(IssueQueueTicket::class)->handle(
                    $team,
                    $service,
                    ['source' => QueueTicketSource::Api],
                    $user,
                );

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback.', $exception->getMessage());
        }

        $this->assertDatabaseCount('queue_tickets', 0);
        $this->assertDatabaseCount('webhook_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_delivery_failure_is_logged_thrown_for_retry_and_can_succeed_later(): void
    {
        Http::fakeSequence()
            ->push('temporary failure', 503)
            ->push('', 204);
        $user = User::factory()->create();
        $endpoint = WebhookEndpoint::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'secret' => 'retry-secret',
            'events' => [WebhookEvent::TicketCompleted->value],
        ]);
        $delivery = $this->delivery($endpoint, WebhookEvent::TicketCompleted);
        $job = new DeliverWebhook($delivery);

        try {
            $job->handle();
            $this->fail('A non-success response must be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Webhook endpoint returned HTTP 503.', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(503, $delivery->response_code);
        $this->assertSame('temporary failure', $delivery->last_error);
        $this->assertSame(4, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());

        $job->handle();

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Delivered, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(204, $delivery->response_code);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($endpoint->refresh()->last_delivery_at);
        Http::assertSentCount(2);

        $job->handle();
        $this->assertSame(2, $delivery->refresh()->attempts);
        Http::assertSentCount(2);
    }

    public function test_rotated_secret_signs_queue_deliveries_and_events_are_documented(): void
    {
        Http::fake(['https://hooks.example.test/*' => Http::response('', 200)]);
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('integrations.webhooks.store'), [
                'url' => 'https://hooks.example.test/queue',
                'events' => [
                    WebhookEvent::TicketCreated->value,
                    WebhookEvent::TicketNoShow->value,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('plain_secret');
        $oldSecret = session('plain_secret');
        $this->assertIsString($oldSecret);
        $endpoint = WebhookEndpoint::query()->where('team_id', $user->currentTeam->id)->firstOrFail();
        $this->assertSame([
            WebhookEvent::TicketCreated->value,
            WebhookEvent::TicketNoShow->value,
        ], $endpoint->events);

        $this->actingAs($user)
            ->post(route('integrations.webhooks.rotate', $endpoint))
            ->assertRedirect()
            ->assertSessionHas('plain_secret');
        $secret = session('plain_secret');
        $this->assertIsString($secret);
        $this->assertNotSame($oldSecret, $secret);

        $delivery = $this->delivery($endpoint->refresh(), WebhookEvent::TicketCreated);
        (new DeliverWebhook($delivery))->handle();
        $body = json_encode($delivery->fresh()->payload, JSON_THROW_ON_ERROR);
        $expectedSignature = 'sha256='.hash_hmac('sha256', $body, $secret);

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-DigSignage-Event', 'ticket.created')
            && $request->hasHeader('X-DigSignage-Delivery', $delivery->uuid)
            && $request->hasHeader('X-DigSignage-Signature', $expectedSignature));

        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonFragment(['value' => WebhookEvent::TicketCreated->value])
            ->assertJsonFragment(['value' => WebhookEvent::TicketNoShow->value]);
    }

    /** @return list<string> */
    protected function queueEvents(): array
    {
        return [
            WebhookEvent::TicketCreated->value,
            WebhookEvent::TicketCalled->value,
            WebhookEvent::TicketServing->value,
            WebhookEvent::TicketCompleted->value,
            WebhookEvent::TicketTransferred->value,
            WebhookEvent::TicketCancelled->value,
            WebhookEvent::TicketNoShow->value,
        ];
    }

    protected function delivery(WebhookEndpoint $endpoint, WebhookEvent $event): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'team_id' => $endpoint->team_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => [
                'id' => 'delivery-payload-id',
                'event' => $event->value,
                'created_at' => now()->toIso8601String(),
                'data' => ['id' => 123, 'number' => 'A123'],
            ],
            'status' => WebhookDeliveryStatus::Pending,
        ]);
    }
}
