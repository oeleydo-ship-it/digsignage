<?php

namespace App\Widgets;

use App\Support\ContentApps;
use App\Support\QueueSnapshot;
use App\Support\SafeOutboundHttp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

final class WidgetCatalog
{
    /**
     * Built-in widgets. Register additional widgets here without changing the designer.
     *
     * @return list<Widget>
     */
    public static function definitions(): array
    {
        return [
            new SchemaWidget('clock', 'Clock', 'Live time using the screen timezone.', [
                new WidgetField('format', 'select', 'Format', 'HH:mm', true, [
                    ['value' => 'HH:mm', 'label' => '24-hour'],
                    ['value' => 'h:mm a', 'label' => '12-hour'],
                    ['value' => 'HH:mm:ss', 'label' => 'With seconds'],
                ]),
                ...self::textStyleFields(60),
            ]),
            new SchemaWidget('date', 'Date', 'Live calendar date using the screen timezone.', [
                new WidgetField('format', 'select', 'Format', 'long', true, [
                    ['value' => 'long', 'label' => 'Long'],
                    ['value' => 'short', 'label' => 'Short'],
                    ['value' => 'iso', 'label' => 'ISO'],
                ]),
                ...self::textStyleFields(48),
            ]),
            new SchemaWidget('weather', 'Weather', 'Current conditions for a city.', [
                new WidgetField('location', 'string', 'Location', 'Dubai', true),
                new WidgetField('units', 'select', 'Units', 'celsius', true, [
                    ['value' => 'celsius', 'label' => 'Celsius'],
                    ['value' => 'fahrenheit', 'label' => 'Fahrenheit'],
                ]),
                ...self::textStyleFields(60),
            ], [self::class, 'weather']),
            new SchemaWidget('rss', 'RSS', 'Headlines from an RSS or Atom feed.', [
                new WidgetField('feed_url', 'url', 'Feed URL', 'https://example.com/feed.xml', true),
                new WidgetField('limit', 'number', 'Items', 5, true),
                ...self::textStyleFields(24),
            ], [self::class, 'rss']),
            new SchemaWidget('news', 'News', 'Headlines from a news RSS feed.', [
                new WidgetField('feed_url', 'url', 'Feed URL', 'https://feeds.bbci.co.uk/news/rss.xml', true),
                new WidgetField('limit', 'number', 'Items', 6, true),
                ...self::textStyleFields(24),
            ], [self::class, 'rss']),
            new SchemaWidget('qr_code', 'QR Code', 'Encode a URL or message as a QR code.', [
                new WidgetField('value', 'string', 'Value', 'https://example.com', true),
                new WidgetField('fontSize', 'number', 'Caption size', 14, false, [], 'Pixels, 8 to 200.'),
            ]),
            new SchemaWidget('queue_now_serving', 'Queue · Now Serving', 'Live ticket and counter assignments.', [
                ...self::queueFields(56, 5, true),
                new WidgetField('heading', 'string', 'Heading', 'Now serving', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_recently_called', 'Queue · Recently Called', 'Most recently called tickets and their counters.', [
                ...self::queueFields(34, 5, true),
                new WidgetField('heading', 'string', 'Heading', 'Recently called', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_waiting_tickets', 'Queue · Waiting Tickets', 'Waiting ticket numbers ordered by queue position.', [
                ...self::queueFields(32, 8),
                new WidgetField('heading', 'string', 'Heading', 'Waiting', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_position', 'Queue · Position', 'Next waiting ticket and its current position.', [
                ...self::queueFields(64, 1),
                new WidgetField('heading', 'string', 'Heading', 'Next in queue', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_counter_number', 'Queue · Counter Number', 'Counter or desk for the latest called ticket.', [
                ...self::queueFields(72, 1, true),
                new WidgetField('heading', 'string', 'Heading', 'Please proceed to', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_service_name', 'Queue · Service Name', 'Selected service name for a queue layout.', [
                ...self::queueFields(64, 1),
                new WidgetField('heading', 'string', 'Heading', 'Service', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_estimated_wait', 'Queue · Estimated Wait', 'Estimated waiting time based on queue depth and open counters.', [
                ...self::queueFields(64, 1),
                new WidgetField('heading', 'string', 'Heading', 'Estimated wait', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_statistics', 'Queue · Statistics', 'Waiting, serving, completed, no-show, and counter totals.', [
                ...self::queueFields(28, 5),
                new WidgetField('heading', 'string', 'Heading', 'Queue statistics', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_ticker', 'Queue · Ticker', 'Scrolling ticket-to-counter announcements.', [
                ...self::queueFields(42, 8, true),
                new WidgetField('heading', 'string', 'Heading', 'Now serving', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_join_qr', 'Queue · Join QR', 'QR code that lets customers join this queue from their phone.', [
                ...self::queueFields(22, 1),
                new WidgetField('heading', 'string', 'Heading', 'Scan to join the queue', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_status', 'Queue · Status', 'Open, busy, or closed status for the selected queue.', [
                ...self::queueFields(72, 1),
                new WidgetField('heading', 'string', 'Heading', 'Queue status', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('queue_board', 'Queue · Custom Board', 'Combined now-serving, waiting, and queue statistics board.', [
                ...self::queueFields(32, 8, true),
                new WidgetField('heading', 'string', 'Heading', 'Queue board', false),
            ], [self::class, 'queueData']),
            new SchemaWidget('web_page', 'Web Page', 'Live webpage in an iframe. Sites that send X-Frame-Options or frame-ancestors will not display.', [
                new WidgetField('url', 'url', 'URL', 'https://example.com', true, [], 'Use a page that allows embedding, such as example.com or your own site. ChatGPT, Google, Facebook, and many banking sites block iframes.'),
                new WidgetField('fullscreen', 'boolean', 'Full-screen web page', false, false, [], 'Fill the entire canvas instead of the widget box.'),
            ], [self::class, 'webPage']),
            new SchemaWidget('youtube', 'YouTube', 'Muted looping YouTube video.', [
                new WidgetField('url', 'url', 'Video URL', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', true, [], 'Watch, youtu.be, embed, Shorts, and live URLs are supported.'),
            ]),
            new SchemaWidget('calendar', 'Calendar', 'Upcoming events from configured entries.', [
                new WidgetField('heading', 'string', 'Heading', 'Today', false),
                new WidgetField('events', 'textarea', 'Events (one per line: time | title)', "09:00 | Doors open\n12:00 | Town hall", true),
                ...self::textStyleFields(24),
            ], [self::class, 'calendar']),
            new SchemaWidget('countdown', 'Countdown', 'Countdown to a target date.', [
                new WidgetField('target', 'datetime', 'Target', '', true),
                new WidgetField('label', 'string', 'Label', 'Starts in', false),
                ...self::textStyleFields(60),
            ]),
            new SchemaWidget('ticker', 'Ticker', 'Scrolling text banner.', [
                new WidgetField('text', 'textarea', 'Text', 'Welcome visitors · Follow hallway signs', true),
                new WidgetField('speed', 'number', 'Speed', 40, true),
                new WidgetField('color', 'color', 'Text color', '#ffffff'),
                new WidgetField('background', 'color', 'Background color', 'transparent', false, [], 'Transparent by default so photos show through. Pick a color for a solid bar.'),
                new WidgetField('background_opacity', 'number', 'Background opacity', 100, false, [], '0 is fully transparent. 100 is a solid bar. Only applies after you pick a background color.'),
                new WidgetField('fontSize', 'number', 'Font size', 48, false, [], 'Pixels, 8 to 200.'),
            ]),
            new SchemaWidget('json_api', 'JSON / API Data', 'Fetch JSON from an HTTPS endpoint.', [
                new WidgetField('url', 'url', 'Endpoint', 'https://example.com/api.json', true),
                new WidgetField('title_path', 'string', 'Title path', 'title', false, [], 'Dot path such as data.headline'),
                new WidgetField('body_path', 'string', 'Body path', 'body', false),
                ...self::textStyleFields(48),
            ], [self::class, 'jsonApi']),
            new SchemaWidget('charts', 'Charts', 'Simple bar chart from values.', [
                new WidgetField('title', 'string', 'Title', 'Occupancy', false),
                new WidgetField('series', 'textarea', 'Series (label:value per line)', "Lobby:42\nCafe:18\nGym:9", true),
                ...self::textStyleFields(20),
            ]),
            new SchemaWidget('table', 'Table', 'Rows of labeled values for lists and directories.', [
                new WidgetField('heading', 'string', 'Heading', 'Directory', false),
                new WidgetField('rows', 'textarea', 'Rows (columns separated by |)', "Radiology | Level 2\nPharmacy | Ground floor\nEmergency | 24 hours", true),
                ...self::textStyleFields(24),
            ]),
            new SchemaWidget('room_info', 'Room Information', 'Meeting room status card.', [
                new WidgetField('room_name', 'string', 'Room', 'Boardroom', true),
                new WidgetField('status', 'select', 'Status', 'available', true, [
                    ['value' => 'available', 'label' => 'Available'],
                    ['value' => 'occupied', 'label' => 'Occupied'],
                    ['value' => 'soon', 'label' => 'Starting soon'],
                ]),
                new WidgetField('next_meeting', 'string', 'Next meeting', '14:00 Leadership', false),
                new WidgetField('capacity', 'number', 'Capacity', 8, false),
                ...self::textStyleFields(48),
            ]),
            new SchemaWidget('booking', 'Bookings', 'Room or resource booking board for the current day.', [
                new WidgetField('heading', 'string', 'Heading', 'Bookings', false),
                new WidgetField('resource', 'string', 'Resource', 'Boardroom', false),
                new WidgetField('bookings', 'textarea', 'Bookings (one per line: start-end | title | status)', "09:00-10:00 | Leadership standup | booked\n10:00-10:30 | Available | available\n11:00-12:00 | Client workshop | booked", true),
                ...self::textStyleFields(24),
            ], [self::class, 'bookings']),
            new SchemaWidget('menu_board', 'Menu Board', 'Priced menu items for cafes and restaurants.', [
                new WidgetField('heading', 'string', 'Heading', 'Today\'s menu', false),
                new WidgetField('currency', 'string', 'Currency', 'AED', false),
                new WidgetField('items', 'textarea', 'Items (name | price | note)', "Espresso | 14\nFlat white | 18\nAvocado toast | 32 | Vegetarian", true),
                ...self::textStyleFields(32),
            ]),
            new SchemaWidget('social_wall', 'Social Wall', 'Curated posts or quotes for a social wall.', [
                new WidgetField('heading', 'string', 'Heading', 'Community', false),
                new WidgetField('posts', 'textarea', 'Posts (one per line: @handle | message)', "@lobby | Welcome to the campus.\n@cafe | New seasonal drinks are out.", true),
                ...self::textStyleFields(24),
            ], [self::class, 'socialWall']),
            new SchemaWidget('alert_banner', 'Alert Banner', 'High-visibility notice or wayfinding message.', [
                new WidgetField('severity', 'select', 'Severity', 'info', true, [
                    ['value' => 'info', 'label' => 'Info'],
                    ['value' => 'warning', 'label' => 'Warning'],
                    ['value' => 'critical', 'label' => 'Critical'],
                ]),
                new WidgetField('heading', 'string', 'Heading', 'Notice', true),
                new WidgetField('message', 'textarea', 'Message', 'Please use the east entrance while the lobby is being refurbished.', true),
                ...self::textStyleFields(48),
            ]),
            new SchemaWidget('world_clock', 'World Clock', 'Times for additional cities.', [
                new WidgetField('cities', 'textarea', 'Cities (Name | Timezone)', "Dubai | Asia/Dubai\nLondon | Europe/London\nNew York | America/New_York", true),
                ...self::textStyleFields(48),
            ]),
        ];
    }

    /**
     * @return list<WidgetField>
     */
    private static function textStyleFields(int $fontSize = 48, string $color = '#ffffff'): array
    {
        return [
            new WidgetField('color', 'color', 'Text color', $color),
            new WidgetField('fontSize', 'number', 'Font size', $fontSize, false, [], 'Pixels, 8 to 200.'),
        ];
    }

    /**
     * @return list<WidgetField>
     */
    private static function queueFields(int $fontSize, int $limit, bool $callSound = false): array
    {
        return [
            new WidgetField('service_id', 'select', 'Service', '0', false, [
                ['value' => '0', 'label' => 'All services'],
            ]),
            new WidgetField('location_id', 'select', 'Location', '0', false, [
                ['value' => '0', 'label' => 'All locations'],
            ]),
            new WidgetField('counter_id', 'select', 'Counter', '0', false, [
                ['value' => '0', 'label' => 'All counters'],
            ]),
            new WidgetField('limit', 'number', 'Number of tickets', $limit, false),
            new WidgetField('color', 'color', 'Text color', '#ffffff'),
            new WidgetField('background', 'color', 'Background color', '#0f172a'),
            new WidgetField('background_opacity', 'number', 'Background opacity', 100, false),
            new WidgetField('border_color', 'color', 'Border color', '#334155'),
            new WidgetField('border_width', 'number', 'Border width', 0, false),
            new WidgetField('align', 'select', 'Alignment', 'center', false, [
                ['value' => 'left', 'label' => 'Left'],
                ['value' => 'center', 'label' => 'Center'],
                ['value' => 'right', 'label' => 'Right'],
            ]),
            new WidgetField('animation', 'select', 'Animation', 'none', false, [
                ['value' => 'none', 'label' => 'None'],
                ['value' => 'pulse', 'label' => 'Pulse'],
                ['value' => 'slide', 'label' => 'Slide in'],
            ]),
            new WidgetField('sound', 'boolean', 'Call sound', $callSound, false),
            new WidgetField('voice', 'boolean', 'Voice announcements', false, false),
            new WidgetField('font_family', 'select', 'Font', 'Arial', false, [
                ['value' => 'Arial', 'label' => 'Arial'],
                ['value' => 'Inter', 'label' => 'Inter'],
                ['value' => 'Georgia', 'label' => 'Georgia'],
                ['value' => 'Courier New', 'label' => 'Courier New'],
            ]),
            new WidgetField('fontSize', 'number', 'Font size', $fontSize, false, [], 'Pixels, 8 to 200.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function queueData(array $settings, WidgetContext $context): array
    {
        return [
            ...$settings,
            ...app(QueueSnapshot::class)->forTeam($context->team, $settings),
            'timezone' => $context->timezone,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function webPage(array $settings, WidgetContext $context): array
    {
        $url = trim((string) ($settings['url'] ?? ''));

        return [
            ...$settings,
            'embed_blocked' => $url !== '' && SafeOutboundHttp::blocksFraming($url),
            'timezone' => $context->timezone,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function weather(array $settings, WidgetContext $context): array
    {
        $location = trim((string) ($settings['location'] ?? 'Dubai'));
        $units = ($settings['units'] ?? 'celsius') === 'fahrenheit' ? 'fahrenheit' : 'celsius';

        try {
            $search = Http::timeout(5)
                ->acceptJson()
                ->get('https://geocoding-api.open-meteo.com/v1/search', [
                    'name' => $location,
                    'count' => 1,
                ])
                ->json();

            $results = is_array($search) && is_array($search['results'] ?? null) ? $search['results'] : [];
            $place = is_array($results[0] ?? null) ? $results[0] : null;

            if ($place === null) {
                return [...$settings, 'error' => 'Location not found.', 'timezone' => $context->timezone];
            }

            $forecast = Http::timeout(5)
                ->acceptJson()
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $place['latitude'] ?? null,
                    'longitude' => $place['longitude'] ?? null,
                    'current' => 'temperature_2m,weather_code',
                    'temperature_unit' => $units === 'fahrenheit' ? 'fahrenheit' : 'celsius',
                ])
                ->json();

            $current = is_array($forecast) && is_array($forecast['current'] ?? null) ? $forecast['current'] : [];

            return [
                ...$settings,
                'place' => (string) ($place['name'] ?? $location),
                'temperature' => $current['temperature_2m'] ?? null,
                'units' => $units,
                'weather_code' => $current['weather_code'] ?? null,
                'timezone' => $context->timezone,
            ];
        } catch (Throwable) {
            return [...$settings, 'error' => 'Weather is unavailable.', 'timezone' => $context->timezone];
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function rss(array $settings, WidgetContext $context): array
    {
        $feedUrl = (string) ($settings['feed_url'] ?? '');
        $limit = max(1, min(12, (int) ($settings['limit'] ?? 5)));

        if (! SafeOutboundHttp::isAllowed($feedUrl)) {
            return [...$settings, 'items' => [], 'error' => 'The feed URL is not allowed.', 'timezone' => $context->timezone];
        }

        try {
            $body = SafeOutboundHttp::get($feedUrl);
            $items = self::parseFeed($body, $limit);

            return [...$settings, 'items' => $items, 'timezone' => $context->timezone];
        } catch (Throwable) {
            return [...$settings, 'items' => [], 'error' => 'The feed could not be loaded.', 'timezone' => $context->timezone];
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function jsonApi(array $settings, WidgetContext $context): array
    {
        $url = (string) ($settings['url'] ?? '');

        if (! SafeOutboundHttp::isAllowed($url)) {
            return [...$settings, 'error' => 'The endpoint is not allowed.', 'timezone' => $context->timezone];
        }

        try {
            $payload = json_decode(SafeOutboundHttp::get($url, ContentApps::jsonApiHeaders($context->team)), true);
            $data = is_array($payload) ? $payload : [];

            return [
                ...$settings,
                'title' => self::dot($data, (string) ($settings['title_path'] ?? 'title')),
                'body' => self::dot($data, (string) ($settings['body_path'] ?? 'body')),
                'timezone' => $context->timezone,
            ];
        } catch (Throwable) {
            return [...$settings, 'error' => 'The endpoint could not be loaded.', 'timezone' => $context->timezone];
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function calendar(array $settings, WidgetContext $context): array
    {
        $events = trim((string) ($settings['events'] ?? ''));
        $config = ContentApps::config($context->team, 'calendar');

        if ($events === '' && filled($config['events'] ?? null)) {
            $events = trim((string) $config['events']);
        }

        if ($events === '' && filled($config['ics_url'] ?? null)) {
            $events = self::eventsFromIcs((string) $config['ics_url'], $context->timezone);
        }

        return [
            ...$settings,
            'events' => $events,
            'timezone' => $context->timezone,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function socialWall(array $settings, WidgetContext $context): array
    {
        $posts = trim((string) ($settings['posts'] ?? ''));
        $config = ContentApps::config($context->team, 'social_wall');

        if ($posts === '' && filled($config['posts'] ?? null)) {
            $posts = trim((string) $config['posts']);
        }

        if ($posts === '' && filled($config['source_url'] ?? null)) {
            $posts = self::postsFromJson((string) $config['source_url']);
        }

        return [
            ...$settings,
            'posts' => $posts,
            'timezone' => $context->timezone,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function bookings(array $settings, WidgetContext $context): array
    {
        $bookings = trim((string) ($settings['bookings'] ?? ''));
        $config = ContentApps::config($context->team, 'booking');

        if ($bookings === '' && filled($config['bookings'] ?? null)) {
            $bookings = trim((string) $config['bookings']);
            $settings['bookings'] = $bookings;
        }

        if ($bookings === '' && filled($config['endpoint_url'] ?? null)) {
            $bookings = self::bookingsFromJson((string) $config['endpoint_url']);
            $settings['bookings'] = $bookings;
        }

        $now = CarbonImmutable::now($context->timezone);
        $minutes = $now->hour * 60 + $now->minute;
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) ($settings['bookings'] ?? '')) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $when = $parts[0];
            $title = $parts[1] ?? $when;
            $status = strtolower($parts[2] ?? 'booked');
            [$start, $end] = self::parseBookingWindow($when);
            $isNow = $start !== null && $minutes >= $start && ($end === null || $minutes < $end);
            $isNext = $start !== null && $start > $minutes;

            $rows[] = [
                'when' => $when,
                'title' => $title !== '' ? $title : $when,
                'status' => in_array($status, ['booked', 'available', 'cancelled'], true) ? $status : 'booked',
                'is_now' => $isNow,
                'is_next' => $isNext,
                'start_minutes' => $start,
            ];
        }

        $current = collect($rows)->first(fn (array $row) => $row['is_now'] === true);
        $next = collect($rows)
            ->filter(fn (array $row) => $row['is_next'] === true && $row['status'] !== 'cancelled')
            ->sortBy('start_minutes')
            ->first();

        return [
            ...$settings,
            'slots' => $rows,
            'current' => is_array($current) ? $current : null,
            'next' => is_array($next) ? $next : null,
            'timezone' => $context->timezone,
            'clock' => $now->format('H:i'),
        ];
    }

    protected static function eventsFromIcs(string $url, string $timezone): string
    {
        if (! SafeOutboundHttp::isAllowed($url)) {
            return '';
        }

        try {
            $body = SafeOutboundHttp::get($url);
        } catch (Throwable) {
            return '';
        }

        $unfolded = preg_replace("/\r\n[ \t]/", '', str_replace("\r\n", "\n", $body)) ?? $body;
        $lines = [];

        if (preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $unfolded, $events) < 1) {
            return '';
        }

        foreach ($events[1] as $block) {
            $summary = '';
            $start = '';

            if (preg_match('/^SUMMARY(?:;[^:]*)?:(.*)$/m', $block, $match) === 1) {
                $summary = trim(str_replace('\\,', ',', $match[1]));
            }

            if (preg_match('/^DTSTART(?:;[^:]*)?:([0-9TZ]+)/m', $block, $match) === 1) {
                $start = self::formatIcsTime($match[1], $timezone);
            }

            if ($summary === '' && $start === '') {
                continue;
            }

            $lines[] = trim($start.' | '.$summary, ' |');

            if (count($lines) >= 12) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    protected static function formatIcsTime(string $value, string $timezone): string
    {
        try {
            if (str_ends_with($value, 'Z')) {
                return CarbonImmutable::createFromFormat('Ymd\THis\Z', $value, 'UTC')
                    ?->setTimezone($timezone)
                    ->format('H:i') ?? '';
            }

            if (strlen($value) === 8) {
                return CarbonImmutable::createFromFormat('Ymd', $value, $timezone)?->format('H:i') ?? '00:00';
            }

            return CarbonImmutable::createFromFormat('Ymd\THis', $value, $timezone)?->format('H:i') ?? '';
        } catch (Throwable) {
            return '';
        }
    }

    protected static function postsFromJson(string $url): string
    {
        return self::linesFromJson($url, function (mixed $item): ?string {
            if (is_string($item)) {
                return trim($item) !== '' ? trim($item) : null;
            }

            if (! is_array($item)) {
                return null;
            }

            $handle = trim((string) ($item['handle'] ?? $item['author'] ?? ''));
            $message = trim((string) ($item['message'] ?? $item['text'] ?? $item['body'] ?? ''));

            if ($handle === '' && $message === '') {
                return null;
            }

            return trim($handle.' | '.$message, ' |');
        });
    }

    protected static function bookingsFromJson(string $url): string
    {
        return self::linesFromJson($url, function (mixed $item): ?string {
            if (is_string($item)) {
                return trim($item) !== '' ? trim($item) : null;
            }

            if (! is_array($item)) {
                return null;
            }

            $when = trim((string) ($item['when'] ?? $item['time'] ?? ''));
            $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
            $status = trim((string) ($item['status'] ?? 'booked'));

            if ($when === '' && $title === '') {
                return null;
            }

            return trim($when.' | '.$title.' | '.$status, ' |');
        });
    }

    /**
     * @param  callable(mixed): (?string)  $formatter
     */
    protected static function linesFromJson(string $url, callable $formatter): string
    {
        if (! SafeOutboundHttp::isAllowed($url)) {
            return '';
        }

        try {
            $payload = json_decode(SafeOutboundHttp::get($url), true);
        } catch (Throwable) {
            return '';
        }

        if (! is_array($payload)) {
            return '';
        }

        $items = $payload;

        foreach (['posts', 'bookings', 'items', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $items = $payload[$key];
                break;
            }
        }

        $lines = [];

        foreach ($items as $item) {
            $line = $formatter($item);

            if ($line === null) {
                continue;
            }

            $lines[] = $line;

            if (count($lines) >= 24) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected static function parseBookingWindow(string $when): array
    {
        if (preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $when, $match) === 1) {
            return [
                ((int) $match[1]) * 60 + (int) $match[2],
                ((int) $match[3]) * 60 + (int) $match[4],
            ];
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $when, $match) === 1) {
            $start = ((int) $match[1]) * 60 + (int) $match[2];

            return [$start, $start + 60];
        }

        return [null, null];
    }

    /**
     * @return list<array{title: string, url: string|null}>
     */
    protected static function parseFeed(string $xml, int $limit): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $feed instanceof SimpleXMLElement) {
            return [];
        }

        $nodes = $feed->channel->item ?? $feed->entry ?? [];
        $items = [];

        foreach ($nodes as $node) {
            $title = trim((string) ($node->title ?? ''));

            if ($title === '') {
                continue;
            }

            $link = (string) ($node->link['href'] ?? $node->link ?? '');
            $items[] = [
                'title' => $title,
                'url' => $link !== '' ? $link : null,
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function dot(array $data, string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public static function formatNow(string $timezone, string $kind, string $format): string
    {
        $now = CarbonImmutable::now($timezone);

        if ($kind === 'clock') {
            return match ($format) {
                'h:mm a' => $now->format('g:i A'),
                'HH:mm:ss' => $now->format('H:i:s'),
                default => $now->format('H:i'),
            };
        }

        return match ($format) {
            'short' => $now->format('M j, Y'),
            'iso' => $now->toDateString(),
            default => $now->format('l, F j, Y'),
        };
    }
}
