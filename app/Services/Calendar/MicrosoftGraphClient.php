<?php

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin Microsoft Graph client for room calendars.
 *
 * Uses the OAuth 2.0 client-credentials flow, so the Entra ID app
 * registration needs these *application* permissions with admin consent:
 *
 *  - Calendars.ReadWrite  read and write events in room mailboxes
 *  - Place.Read.All       list the tenant's room resources
 */
class MicrosoftGraphClient
{
    public const GRAPH = 'https://graph.microsoft.com/v1.0';

    private const LOGIN = 'https://login.microsoftonline.com';

    /**
     * Stop following @odata.nextLink after this many pages.
     */
    private const MAX_PAGES = 20;

    public function __construct(protected CalendarConnection $connection) {}

    public static function for(CalendarConnection $connection): self
    {
        return new self($connection);
    }

    /**
     * Fetch (and cache) an app-only access token.
     */
    public function accessToken(): string
    {
        if (! $this->connection->isConfigured()) {
            throw new MicrosoftGraphException('Enter the tenant ID, client ID and client secret first.');
        }

        $cacheKey = 'm365:token:'.$this->connection->id.':'.sha1(
            $this->connection->tenant_id.'|'.$this->connection->client_id.'|'.$this->connection->client_secret,
        );

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->acceptJson()
            ->post(self::LOGIN.'/'.rawurlencode((string) $this->connection->tenant_id).'/oauth2/v2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->connection->client_id,
                'client_secret' => $this->connection->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

        if (! $response->successful()) {
            throw new MicrosoftGraphException(
                'Microsoft sign-in failed: '.$this->errorMessage($response),
                $response->status(),
            );
        }

        $token = (string) $response->json('access_token', '');

        if ($token === '') {
            throw new MicrosoftGraphException('Microsoft sign-in did not return an access token.');
        }

        $lifetime = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($cacheKey, $token, $lifetime);

        return $token;
    }

    /**
     * Room resources registered in the tenant.
     *
     * @return list<array{id: string, name: string, email: string, capacity: int|null, building: string|null, floor: string|null}>
     */
    public function rooms(): array
    {
        $rooms = [];

        foreach ($this->paginate(self::GRAPH.'/places/microsoft.graph.room?$top=100') as $place) {
            $email = (string) ($place['emailAddress'] ?? '');

            if ($email === '') {
                continue;
            }

            $rooms[] = [
                'id' => (string) ($place['id'] ?? $email),
                'name' => (string) ($place['displayName'] ?? $email),
                'email' => $email,
                'capacity' => isset($place['capacity']) ? (int) $place['capacity'] : null,
                'building' => isset($place['building']) ? (string) $place['building'] : null,
                'floor' => isset($place['floorLabel'])
                    ? (string) $place['floorLabel']
                    : (isset($place['floorNumber']) ? (string) $place['floorNumber'] : null),
            ];
        }

        usort($rooms, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $rooms;
    }

    /**
     * Events on a room calendar between two instants, in UTC.
     *
     * @return list<array{id: string, change_key: string|null, subject: string, organizer_name: string|null, organizer_email: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, cancelled: bool, free: bool}>
     */
    public function calendarView(string $mailbox, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $query = http_build_query([
            'startDateTime' => CarbonImmutable::instance($from)->utc()->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => CarbonImmutable::instance($to)->utc()->format('Y-m-d\TH:i:s\Z'),
            '$select' => 'id,changeKey,subject,start,end,organizer,isCancelled,showAs',
            '$orderby' => 'start/dateTime',
            '$top' => 100,
        ]);

        $events = [];

        foreach ($this->paginate(self::GRAPH.'/users/'.rawurlencode($mailbox).'/calendarView?'.$query, true) as $event) {
            $start = $this->parseDateTime($event['start'] ?? null);
            $end = $this->parseDateTime($event['end'] ?? null);

            if ($start === null || $end === null || ! is_string($event['id'] ?? null)) {
                continue;
            }

            $organizer = is_array($event['organizer']['emailAddress'] ?? null) ? $event['organizer']['emailAddress'] : [];

            $events[] = [
                'id' => $event['id'],
                'change_key' => is_string($event['changeKey'] ?? null) ? $event['changeKey'] : null,
                'subject' => trim((string) ($event['subject'] ?? '')) ?: 'Busy',
                'organizer_name' => is_string($organizer['name'] ?? null) ? $organizer['name'] : null,
                'organizer_email' => is_string($organizer['address'] ?? null) ? $organizer['address'] : null,
                'starts_at' => $start,
                'ends_at' => $end,
                'cancelled' => (bool) ($event['isCancelled'] ?? false),
                'free' => ($event['showAs'] ?? null) === 'free',
            ];
        }

        return $events;
    }

    /**
     * Create an event directly on the room calendar.
     *
     * @return array{id: string, change_key: string|null}
     */
    public function createEvent(
        string $mailbox,
        string $subject,
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?string $body = null,
        ?string $attendeeEmail = null,
        ?string $attendeeName = null,
    ): array {
        $payload = [
            'subject' => $subject,
            'start' => ['dateTime' => CarbonImmutable::instance($start)->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => CarbonImmutable::instance($end)->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'showAs' => 'busy',
            'body' => ['contentType' => 'text', 'content' => (string) $body],
        ];

        if (filled($attendeeEmail)) {
            $payload['attendees'] = [[
                'emailAddress' => ['address' => $attendeeEmail, 'name' => $attendeeName ?: $attendeeEmail],
                'type' => 'required',
            ]];
        }

        $response = $this->request()->post(self::GRAPH.'/users/'.rawurlencode($mailbox).'/events', $payload);

        if (! $response->successful()) {
            throw new MicrosoftGraphException(
                'Microsoft 365 could not create the meeting: '.$this->errorMessage($response),
                $response->status(),
            );
        }

        return [
            'id' => (string) $response->json('id'),
            'change_key' => $response->json('changeKey'),
        ];
    }

    /**
     * Move an existing event to a new time and subject.
     */
    public function updateEvent(string $mailbox, string $eventId, string $subject, DateTimeInterface $start, DateTimeInterface $end): ?string
    {
        $response = $this->request()->patch(self::GRAPH.'/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($eventId), [
            'subject' => $subject,
            'start' => ['dateTime' => CarbonImmutable::instance($start)->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => CarbonImmutable::instance($end)->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
        ]);

        if (! $response->successful()) {
            throw new MicrosoftGraphException(
                'Microsoft 365 could not update the meeting: '.$this->errorMessage($response),
                $response->status(),
            );
        }

        $changeKey = $response->json('changeKey');

        return is_string($changeKey) ? $changeKey : null;
    }

    /**
     * Delete an event. An event that is already gone counts as deleted.
     */
    public function deleteEvent(string $mailbox, string $eventId): void
    {
        $response = $this->request()->delete(self::GRAPH.'/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($eventId));

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw new MicrosoftGraphException(
            'Microsoft 365 could not cancel the meeting: '.$this->errorMessage($response),
            $response->status(),
        );
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    protected function paginate(string $url, bool $utc = false): iterable
    {
        $pages = 0;

        while ($url !== '' && $pages < self::MAX_PAGES) {
            $request = $this->request();

            if ($utc) {
                $request = $request->withHeaders(['Prefer' => 'outlook.timezone="UTC"']);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                throw new MicrosoftGraphException(
                    'Microsoft Graph request failed: '.$this->errorMessage($response),
                    $response->status(),
                );
            }

            foreach ((array) $response->json('value', []) as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $next = $response->json('@odata.nextLink');
            $url = is_string($next) && str_starts_with($next, self::GRAPH) ? $next : '';
            $pages++;
        }
    }

    protected function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 250, fn ($exception) => $exception instanceof ConnectionException, throw: false);
    }

    /**
     * @param  mixed  $value  Graph dateTimeTimeZone: {dateTime, timeZone}
     */
    protected function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_array($value) || ! is_string($value['dateTime'] ?? null)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value['dateTime'], (string) ($value['timeZone'] ?? 'UTC'))->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function errorMessage(Response $response): string
    {
        $message = $response->json('error_description')
            ?? $response->json('error.message')
            ?? $response->json('error');

        return is_string($message) && $message !== ''
            ? mb_substr(strtok($message, "\r\n") ?: $message, 0, 300)
            : 'HTTP '.$response->status();
    }
}
