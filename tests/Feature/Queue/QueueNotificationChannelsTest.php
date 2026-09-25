<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\DispatchQueueCustomerNotification;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Enums\TeamRole;
use App\Jobs\SendQueueCustomerNotification;
use App\Models\QueueNotificationDelivery;
use App\Models\QueueNotificationRule;
use App\Models\QueuePushSubscription;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueNotifications\QueueNotificationProviderRegistry;
use App\Services\QueueNotifications\WebPushSender;
use App\Support\QueueNotificationChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    /** A fake Twilio account SID, built at runtime so secret scanners ignore it. */
    private static function sid(): string
    {
        return 'AC'.str_repeat('0f', 16);
    }

    public function test_twilio_credentials_are_saved_encrypted_and_never_sent_to_the_page(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($owner)
            ->put(route('queue.settings.notification-channels.update', [$team, 'sms']), [
                'driver' => 'twilio',
                'twilio_sid' => self::sid(),
                'twilio_token' => 'secret-token-123',
                'twilio_from' => '+15550001111',
            ])
            ->assertSessionHasNoErrors();

        $raw = json_encode(QueueSetting::query()->where('team_id', $team->id)->value('settings'));
        $this->assertStringNotContainsString('secret-token-123', (string) $raw);

        $response = $this->actingAs($owner)->withoutVite()->get(route('queue.settings', $team));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('customerNotifications.providers.sms.driver', 'twilio')
            ->where('customerNotifications.providers.sms.has_twilio_token', true)
            ->where('customerNotifications.providers.sms.status.ready', true)
            ->missing('customerNotifications.providers.sms.twilio_token'));
        $this->assertStringNotContainsString('secret-token-123', (string) $response->getContent());

        // Saving again with a blank token keeps it.
        $this->actingAs($owner)
            ->put(route('queue.settings.notification-channels.update', [$team, 'sms']), [
                'driver' => 'twilio',
                'twilio_sid' => self::sid(),
                'twilio_from' => '+15550002222',
            ])
            ->assertSessionHasNoErrors();

        $config = app(QueueNotificationChannels::class)->config($team->id, QueueNotificationChannel::Sms);
        $this->assertSame('secret-token-123', $config['twilio_token']);
        $this->assertSame('+15550002222', $config['twilio_from']);
    }

    public function test_members_without_settings_access_cannot_change_channels(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->put(route('queue.settings.notification-channels.update', [$team, 'sms']), ['driver' => 'webhook', 'webhook_url' => 'https://evil.test'])
            ->assertForbidden();
    }

    public function test_sms_is_delivered_through_twilio_with_a_normalized_number(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        [$ticket, $teamId] = $this->ticket(['customer_phone' => '00 971 (50) 123-4567']);
        $this->configure($ticket, 'sms', ['driver' => 'twilio', 'twilio_sid' => self::sid(), 'twilio_token' => 'tok', 'twilio_from' => '+15550001111']);

        $delivery = $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Sms);

        $this->assertSame('sent', $delivery->status, (string) $delivery->error);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.self::sid().'/Messages.json'
            && $request['To'] === '+971501234567'
            && $request['From'] === '+15550001111'
            && str_contains((string) $request['Body'], 'has been called')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::sid().':tok')));
    }

    public function test_whatsapp_uses_twilio_whatsapp_addresses_or_meta_templates(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid']]]),
        ]);
        [$ticket] = $this->ticket(['customer_phone' => '+971501234567']);
        $this->configure($ticket, 'whatsapp', ['driver' => 'twilio', 'twilio_sid' => self::sid(), 'twilio_token' => 'tok', 'twilio_from' => '+15550001111']);

        $this->assertSame('sent', $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::WhatsApp, 'a')->status);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.twilio.com')
            && $request['To'] === 'whatsapp:+971501234567'
            && $request['From'] === 'whatsapp:+15550001111');

        $this->configure($ticket, 'whatsapp', ['driver' => 'meta', 'meta_phone_number_id' => '1234567890', 'meta_token' => 'EAAB', 'meta_template' => 'queue_update', 'meta_template_language' => 'en_US']);

        $this->assertSame('sent', $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::WhatsApp, 'b')->status);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
            && $request['to'] === '971501234567'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'queue_update'
            && str_contains((string) $request['template']['components'][0]['parameters'][0]['text'], 'has been called')
            && $request->hasHeader('Authorization', 'Bearer EAAB'));
    }

    public function test_webhooks_are_signed_with_the_secret(): void
    {
        Http::fake(['gateway.test/*' => Http::response(['ok' => true])]);
        [$ticket] = $this->ticket(['customer_phone' => '+15550009999']);
        $this->configure($ticket, 'sms', ['driver' => 'webhook', 'webhook_url' => 'https://gateway.test/send', 'webhook_secret' => 'shh']);

        $this->assertSame('sent', $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Sms)->status);
        Http::assertSent(fn (Request $request) => $request->header('X-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), 'shh')
            && $request['destination'] === '+15550009999');
    }

    public function test_bad_numbers_fail_with_a_clear_reason(): void
    {
        Http::fake();
        [$ticket] = $this->ticket(['customer_phone' => '0501234567']);
        $this->configure($ticket, 'sms', ['driver' => 'twilio', 'twilio_sid' => self::sid(), 'twilio_token' => 'tok', 'twilio_from' => '+15550001111']);

        $delivery = $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Sms);

        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString('country code', (string) $delivery->error);
        Http::assertNothingSent();
    }

    public function test_channels_that_are_not_set_up_are_skipped_without_queueing(): void
    {
        Queue::fake();
        [$ticket, $teamId] = $this->ticket(['customer_phone' => '+15550009999']);
        $this->rule($teamId, QueueNotificationEvent::TicketCreated, QueueNotificationChannel::Sms);

        app(DispatchQueueCustomerNotification::class)->forTicket($ticket, QueueNotificationEvent::TicketCreated);

        $delivery = QueueNotificationDelivery::query()->sole();
        $this->assertSame('skipped', $delivery->status);
        $this->assertSame('SMS is not set up.', $delivery->error);
        Queue::assertNothingPushed();
    }

    public function test_custom_templates_are_used_in_messages(): void
    {
        Queue::fake();
        [$ticket, $teamId] = $this->ticket(['customer_email' => 'guest@example.com', 'customer_name' => 'Priya']);
        $owner = User::query()->whereHas('teams', fn ($query) => $query->whereKey($teamId))->firstOrFail();

        $this->actingAs($owner)
            ->put(route('queue.settings.notification-templates.update', $ticket->team), [
                'templates' => [
                    'ticket_called' => ['subject' => 'Your turn, {name}', 'body' => 'Hi {name}, go to {counter} with ticket {ticket}.'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->rule($teamId, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Email);
        app(DispatchQueueCustomerNotification::class)->forTicket($ticket, QueueNotificationEvent::TicketCalled, 'call');

        $payload = QueueNotificationDelivery::query()->sole()->payload;
        $this->assertSame('Your turn, Priya', $payload['subject']);
        $this->assertSame('Hi Priya, go to the counter with ticket '.$ticket->number.'.', $payload['body']);
    }

    public function test_test_sends_report_success_and_failure(): void
    {
        Http::fake(['api.twilio.com/*' => Http::sequence()->push(['sid' => 'SM1'], 201)->push(['message' => 'The From number is not valid'], 400)]);
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        app(QueueNotificationChannels::class)->save($team, QueueNotificationChannel::Sms, ['driver' => 'twilio', 'twilio_sid' => self::sid(), 'twilio_token' => 'tok', 'twilio_from' => '+15550001111']);

        $this->actingAs($owner)
            ->post(route('queue.settings.notification-channels.test', [$team, 'sms']), ['destination' => '+15550009999'])
            ->assertInertiaFlash('toast.type', 'success');

        $this->actingAs($owner)
            ->post(route('queue.settings.notification-channels.test', [$team, 'sms']), ['destination' => '+15550009999'])
            ->assertInertiaFlash('toast.type', 'error');

        $this->actingAs($owner)
            ->post(route('queue.settings.notification-channels.test', [$team, 'sms']), ['destination' => 'not a phone'])
            ->assertSessionHasErrors('destination');
    }

    public function test_failed_deliveries_can_be_retried_by_their_team_only(): void
    {
        Queue::fake();
        [$ticket, $teamId] = $this->ticket(['customer_phone' => '+15550009999']);
        $this->configure($ticket, 'sms', ['driver' => 'webhook', 'webhook_url' => 'https://gateway.test/send']);
        $delivery = QueueNotificationDelivery::query()->create([
            'team_id' => $teamId,
            'queue_ticket_id' => $ticket->id,
            'event' => QueueNotificationEvent::TicketCalled,
            'channel' => QueueNotificationChannel::Sms,
            'destination' => '+15550009999',
            'status' => 'failed',
            'dedupe_key' => 'x',
            'payload' => ['subject' => 's', 'body' => 'b'],
            'error' => 'Timeout',
        ]);
        $owner = User::query()->whereHas('teams', fn ($query) => $query->whereKey($teamId))->firstOrFail();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('queue.settings.notification-deliveries.retry', [$stranger->currentTeam, $delivery]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('queue.settings.notification-deliveries.retry', [$ticket->team, $delivery]))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame('pending', $delivery->refresh()->status);
        Queue::assertPushed(SendQueueCustomerNotification::class, 1);
    }

    public function test_customers_can_turn_on_push_for_their_ticket_and_receive_it(): void
    {
        [$ticket, $teamId] = $this->ticket(['public_token' => 'tok-abc123']);
        $this->rule($teamId, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Push);

        $sender = new class extends WebPushSender
        {
            /** @var list<array<string, mixed>> */
            public array $sent = [];

            public function __construct() {}

            public function publicKey(): string
            {
                return 'BPublicKey';
            }

            public function send(Collection $subscriptions, array $notification): int
            {
                $this->sent[] = ['count' => $subscriptions->count(), ...$notification];

                return $subscriptions->count();
            }
        };
        $this->app->instance(WebPushSender::class, $sender);

        $this->withoutVite()
            ->get(route('queue.virtual.ticket', 'tok-abc123'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('push.enabled', true)
                ->where('push.public_key', 'BPublicKey'));

        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'BKey', 'auth' => 'authsecret'],
        ];

        // Only real browser push services are accepted.
        $this->postJson(route('queue.virtual.push.subscribe', 'tok-abc123'), [...$subscription, 'endpoint' => 'https://internal.example.test/hook'])
            ->assertUnprocessable();

        $this->postJson(route('queue.virtual.push.subscribe', 'tok-abc123'), $subscription)->assertOk();
        $this->postJson(route('queue.virtual.push.subscribe', 'tok-abc123'), $subscription)->assertOk();
        $this->assertSame(1, QueuePushSubscription::query()->count());

        $delivery = $this->notify($ticket, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Push, 'push', rule: false);

        $this->assertSame('sent', $delivery->status, (string) $delivery->error);
        $this->assertSame(1, $sender->sent[0]['count']);
        $this->assertSame(route('queue.virtual.ticket', 'tok-abc123'), $sender->sent[0]['url']);

        $this->deleteJson(route('queue.virtual.push.unsubscribe', 'tok-abc123'), ['endpoint' => $subscription['endpoint']])->assertOk();
        $this->assertSame(0, QueuePushSubscription::query()->count());
    }

    public function test_push_is_skipped_when_the_customer_did_not_subscribe(): void
    {
        Queue::fake();
        [$ticket, $teamId] = $this->ticket(['public_token' => 'tok-xyz']);
        $this->rule($teamId, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Push);

        app(DispatchQueueCustomerNotification::class)->forTicket($ticket, QueueNotificationEvent::TicketCalled);

        $delivery = QueueNotificationDelivery::query()->sole();
        $this->assertSame('skipped', $delivery->status);
        $this->assertSame('The customer has not turned on notifications.', $delivery->error);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{QueueTicket, int}
     */
    protected function ticket(array $attributes = []): array
    {
        $user = User::factory()->create();
        $service = QueueService::factory()->create(['team_id' => $user->currentTeam->id]);
        $ticket = QueueTicket::factory()->forService($service)->create();
        $ticket->forceFill($attributes)->saveQuietly();

        return [$ticket->refresh(), $user->currentTeam->id];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configure(QueueTicket $ticket, string $channel, array $config): void
    {
        app(QueueNotificationChannels::class)->save($ticket->team, QueueNotificationChannel::from($channel), $config);
    }

    protected function rule(int $teamId, QueueNotificationEvent $event, QueueNotificationChannel $channel): void
    {
        QueueNotificationRule::query()->updateOrCreate(
            ['team_id' => $teamId, 'event' => $event, 'channel' => $channel],
            ['is_enabled' => true],
        );
    }

    /**
     * Dispatch the event and run the delivery job synchronously.
     */
    protected function notify(QueueTicket $ticket, QueueNotificationEvent $event, QueueNotificationChannel $channel, string $occurrence = 'once', bool $rule = true): QueueNotificationDelivery
    {
        Queue::fake();

        if ($rule) {
            $this->rule($ticket->team_id, $event, $channel);
        }

        app(DispatchQueueCustomerNotification::class)->forTicket($ticket, $event, $occurrence);
        $delivery = QueueNotificationDelivery::query()->where('channel', $channel)->latest('id')->firstOrFail();

        if ($delivery->status === 'pending') {
            try {
                (new SendQueueCustomerNotification($delivery->id))->handle(app(QueueNotificationProviderRegistry::class));
            } catch (\Throwable) {
                // The job records the failure on the delivery.
            }
        }

        return $delivery->refresh();
    }
}
