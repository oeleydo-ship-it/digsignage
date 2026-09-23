<?php

namespace App\Support;

use App\Enums\TemplateCategory;

final class CatalogTemplateLibrary
{
    /**
     * Ready-made published catalog layouts, keyed for idempotent seeding.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     category: TemplateCategory,
     *     featured: bool,
     *     document: array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'lobby-welcome',
                'name' => 'Lobby welcome',
                'description' => 'Full-bleed photo, headline, live clock, and an editable ticker.',
                'category' => TemplateCategory::Corporate,
                'featured' => true,
                'document' => self::lobbyWelcome(),
            ],
            [
                'key' => 'restaurant-menu',
                'name' => 'Restaurant menu board',
                'description' => 'Priced menu with today’s specials for cafes and restaurants.',
                'category' => TemplateCategory::Restaurant,
                'featured' => true,
                'document' => self::restaurantMenu(),
            ],
            [
                'key' => 'menu-breakfast-lunch',
                'name' => 'Breakfast and lunch board',
                'description' => 'Dual-column menu board for all-day dining.',
                'category' => TemplateCategory::MenuBoards,
                'featured' => false,
                'document' => self::menuBoard(),
            ],
            [
                'key' => 'news-rss-wall',
                'name' => 'News and RSS wall',
                'description' => 'Live headlines, a clock, and a breaking-news ticker.',
                'category' => TemplateCategory::News,
                'featured' => true,
                'document' => self::newsRssWall(),
            ],
            [
                'key' => 'meeting-room',
                'name' => 'Meeting room',
                'description' => 'Room status, today’s bookings, date, and clock.',
                'category' => TemplateCategory::Meeting,
                'featured' => true,
                'document' => self::meetingRoom(),
            ],
            [
                'key' => 'social-community-wall',
                'name' => 'Social wall',
                'description' => 'Curated posts with a heading you can edit in the inspector.',
                'category' => TemplateCategory::Events,
                'featured' => true,
                'document' => self::socialWall(),
            ],
            [
                'key' => 'retail-promo',
                'name' => 'Retail promotion',
                'description' => 'Sale campaign with offer, countdown, hours, and a QR code.',
                'category' => TemplateCategory::Retail,
                'featured' => true,
                'document' => self::retailPromo(),
            ],
            [
                'key' => 'weather-world-clock',
                'name' => 'Weather and world clocks',
                'description' => 'Dubai weather plus clocks for other cities.',
                'category' => TemplateCategory::Corporate,
                'featured' => true,
                'document' => self::weatherWorldClock(),
            ],
            [
                'key' => 'internal-comms',
                'name' => 'Internal comms',
                'description' => 'Alert banner, news headlines, and an embeddable web page.',
                'category' => TemplateCategory::Announcements,
                'featured' => true,
                'document' => self::internalComms(),
            ],
            [
                'key' => 'media-lounge',
                'name' => 'Media lounge',
                'description' => 'Looping YouTube feature with a ticker underneath.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::mediaLounge(),
            ],
            [
                'key' => 'healthcare-waiting-room',
                'name' => 'Healthcare waiting room',
                'description' => 'Clinic welcome board with wait times, hours, and a live clock.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::healthcareWaitingRoom(),
            ],
            [
                'key' => 'healthcare-wayfinding',
                'name' => 'Healthcare wayfinding',
                'description' => 'Department directory and visiting hours for hospitals and clinics.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::healthcareWayfinding(),
            ],
            [
                'key' => 'corporate-lobby',
                'name' => 'Corporate lobby',
                'description' => 'Reception welcome with the date, a ticker, and today’s meetings.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::corporateLobby(),
            ],
            [
                'key' => 'education-campus',
                'name' => 'Campus bulletin',
                'description' => 'School or university news, events, and a clock.',
                'category' => TemplateCategory::Education,
                'featured' => false,
                'document' => self::educationCampus(),
            ],
            [
                'key' => 'hospitality-welcome',
                'name' => 'Hotel welcome',
                'description' => 'Guest welcome with check-in times and local highlights.',
                'category' => TemplateCategory::Hospitality,
                'featured' => false,
                'document' => self::hospitalityWelcome(),
            ],
            [
                'key' => 'events-agenda',
                'name' => 'Event agenda',
                'description' => 'Session schedule for conferences and town halls.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::eventsAgenda(),
            ],
            [
                'key' => 'transport-departures',
                'name' => 'Departures board',
                'description' => 'Route and departure times for transit and campuses.',
                'category' => TemplateCategory::Transportation,
                'featured' => false,
                'document' => self::transportDepartures(),
            ],
            [
                'key' => 'announcements-notice',
                'name' => 'Building announcement',
                'description' => 'High-visibility notice with a scrolling ticker.',
                'category' => TemplateCategory::Announcements,
                'featured' => false,
                'document' => self::announcement(),
            ],
            [
                'key' => 'emergency-notice',
                'name' => 'Emergency notice',
                'description' => 'Critical alert layout for evacuations and lockdowns.',
                'category' => TemplateCategory::Emergency,
                'featured' => false,
                'document' => self::emergencyNotice(),
            ],
            [
                'key' => 'portrait-lobby-welcome',
                'name' => 'Portrait lobby welcome',
                'description' => 'Vertical welcome board with photo, headline, clock, and ticker.',
                'category' => TemplateCategory::Portrait,
                'featured' => true,
                'document' => self::portraitLobbyWelcome(1080, 1920),
            ],
            [
                'key' => 'portrait-menu-board',
                'name' => 'Portrait menu board',
                'description' => 'Full-height menu for vertical displays in cafes and restaurants.',
                'category' => TemplateCategory::MenuBoards,
                'featured' => true,
                'document' => self::portraitMenuBoard(1080, 1920),
            ],
            [
                'key' => 'portrait-wayfinding',
                'name' => 'Portrait wayfinding',
                'description' => 'Department directory with QR code for vertical lobby screens.',
                'category' => TemplateCategory::Vertical,
                'featured' => false,
                'document' => self::portraitWayfinding(1080, 1920),
            ],
            [
                'key' => 'portrait-retail-promo',
                'name' => 'Portrait retail promo',
                'description' => 'Sale campaign with hero image, countdown, QR, and ticker.',
                'category' => TemplateCategory::Retail,
                'featured' => true,
                'document' => self::portraitRetailPromo(1080, 1920),
            ],
            [
                'key' => 'portrait-internal-comms',
                'name' => 'Portrait internal comms',
                'description' => 'Alert banner, news feed, and scrolling ticker for vertical panels.',
                'category' => TemplateCategory::Announcements,
                'featured' => false,
                'document' => self::portraitInternalComms(1080, 1920),
            ],
            [
                'key' => 'portrait-hd-lobby',
                'name' => 'HD portrait lobby',
                'description' => '720×1280 welcome board for smaller vertical screens.',
                'category' => TemplateCategory::Portrait,
                'featured' => false,
                'document' => self::portraitLobbyWelcome(720, 1280),
            ],
            [
                'key' => 'portrait-hd-menu',
                'name' => 'HD portrait menu',
                'description' => '720×1280 menu board for compact vertical displays.',
                'category' => TemplateCategory::MenuBoards,
                'featured' => false,
                'document' => self::portraitMenuBoard(720, 1280),
            ],
            [
                'key' => 'portrait-hd-comms',
                'name' => 'HD portrait comms',
                'description' => '720×1280 alert and news layout for narrow vertical panels.',
                'category' => TemplateCategory::Announcements,
                'featured' => false,
                'document' => self::portraitInternalComms(720, 1280),
            ],
            [
                'key' => 'portrait-4x5-retail',
                'name' => '4:5 retail promo',
                'description' => 'Instagram-style 1080×1350 sale layout with countdown and QR.',
                'category' => TemplateCategory::Retail,
                'featured' => false,
                'document' => self::portraitRetailPromo(1080, 1350),
            ],
            [
                'key' => 'portrait-4x5-wayfinding',
                'name' => '4:5 wayfinding',
                'description' => '1080×1350 directory with departments and QR code.',
                'category' => TemplateCategory::Vertical,
                'featured' => false,
                'document' => self::portraitWayfinding(1080, 1350),
            ],
            [
                'key' => 'portrait-4x5-menu',
                'name' => '4:5 menu board',
                'description' => '1080×1350 menu layout for portrait social-style panels.',
                'category' => TemplateCategory::MenuBoards,
                'featured' => false,
                'document' => self::portraitMenuBoard(1080, 1350),
            ],
            [
                'key' => 'ceo-message',
                'name' => 'CEO message',
                'description' => 'Executive welcome with photo, headline, and live date.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::ceoMessage(),
            ],
            [
                'key' => 'kpi-dashboard',
                'name' => 'KPI dashboard',
                'description' => 'Occupancy bar chart with headline and world clocks.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::kpiDashboard(),
            ],
            [
                'key' => 'meeting-agenda-board',
                'name' => 'Meeting agenda',
                'description' => 'Room name, today’s schedule, clock, and ticker.',
                'category' => TemplateCategory::Meeting,
                'featured' => false,
                'document' => self::meetingAgendaBoard(),
            ],
            [
                'key' => 'patient-safety-reminder',
                'name' => 'Patient safety reminder',
                'description' => 'Hygiene and safety alerts for waiting areas.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::patientSafetyReminder(),
            ],
            [
                'key' => 'clinic-hours-board',
                'name' => 'Clinic hours',
                'description' => 'Weekly hours table with date and clock.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::clinicHoursBoard(),
            ],
            [
                'key' => 'allergen-notice',
                'name' => 'Allergen notice',
                'description' => 'Menu highlights with allergen warning banner.',
                'category' => TemplateCategory::Restaurant,
                'featured' => false,
                'document' => self::allergenNotice(),
            ],
            [
                'key' => 'daily-specials-board',
                'name' => 'Daily specials',
                'description' => 'Chef’s picks with hero photo and priced items.',
                'category' => TemplateCategory::Restaurant,
                'featured' => false,
                'document' => self::dailySpecialsBoard(),
            ],
            [
                'key' => 'new-arrivals',
                'name' => 'New arrivals',
                'description' => 'Product spotlight with photo, copy, and QR code.',
                'category' => TemplateCategory::Retail,
                'featured' => false,
                'document' => self::newArrivals(),
            ],
            [
                'key' => 'flash-sale',
                'name' => 'Flash sale countdown',
                'description' => 'Limited-time offer with countdown and promo code.',
                'category' => TemplateCategory::Retail,
                'featured' => false,
                'document' => self::flashSale(),
            ],
            [
                'key' => 'exam-schedule',
                'name' => 'Exam schedule',
                'description' => 'Campus exam timetable with calendar widget.',
                'category' => TemplateCategory::Education,
                'featured' => false,
                'document' => self::examSchedule(),
            ],
            [
                'key' => 'campus-events-board',
                'name' => 'Campus events',
                'description' => 'Upcoming events, notice banner, and ticker.',
                'category' => TemplateCategory::Education,
                'featured' => false,
                'document' => self::campusEventsBoard(),
            ],
            [
                'key' => 'now-hiring',
                'name' => 'Now hiring',
                'description' => 'Open roles headline with QR code to careers page.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::nowHiring(),
            ],
            [
                'key' => 'onboarding-welcome',
                'name' => 'Onboarding welcome',
                'description' => 'First-day checklist with alert banner and agenda.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::onboardingWelcome(),
            ],
            [
                'key' => 'property-showcase',
                'name' => 'Property showcase',
                'description' => 'Real estate listing with photo, details, and QR tour.',
                'category' => TemplateCategory::Hospitality,
                'featured' => false,
                'document' => self::propertyShowcase(),
            ],
            [
                'key' => 'transit-departures-table',
                'name' => 'Transit departures',
                'description' => 'Route table with live clock and scrolling ticker.',
                'category' => TemplateCategory::Transportation,
                'featured' => false,
                'document' => self::transitDeparturesTable(),
            ],
            [
                'key' => 'hotel-amenities',
                'name' => 'Hotel amenities',
                'description' => 'Pool, spa, and dining hours in a directory table.',
                'category' => TemplateCategory::Hospitality,
                'featured' => false,
                'document' => self::hotelAmenities(),
            ],
            [
                'key' => 'guest-check-in',
                'name' => 'Guest check-in',
                'description' => 'Front desk welcome with room info and world clocks.',
                'category' => TemplateCategory::Hospitality,
                'featured' => false,
                'document' => self::guestCheckIn(),
            ],
            [
                'key' => 'event-countdown',
                'name' => 'Event countdown',
                'description' => 'Conference opener with countdown and sponsor ticker.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::eventCountdown(),
            ],
            [
                'key' => 'sponsor-showcase',
                'name' => 'Sponsor wall',
                'description' => 'Partner logos area with curated sponsor messages.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::sponsorShowcase(),
            ],
            [
                'key' => 'team-metrics-api',
                'name' => 'Live metrics board',
                'description' => 'JSON/API headline with chart and news feed.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::teamMetricsApi(),
            ],
            [
                'key' => 'square-brand-spotlight',
                'name' => 'Brand spotlight',
                'description' => '1080×1080 social-style brand feature with QR.',
                'category' => TemplateCategory::Corporate,
                'featured' => true,
                'document' => self::squareBrandSpotlight(),
            ],
            [
                'key' => 'square-hiring',
                'name' => 'Square hiring post',
                'description' => '1080×1080 careers promo with QR code.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::squareHiring(),
            ],
            [
                'key' => 'square-menu-special',
                'name' => 'Square menu special',
                'description' => '1080×1080 daily dish highlight for social displays.',
                'category' => TemplateCategory::MenuBoards,
                'featured' => false,
                'document' => self::squareMenuSpecial(),
            ],
            [
                'key' => 'square-retail-sale',
                'name' => 'Square sale badge',
                'description' => '1080×1080 promo tile with countdown.',
                'category' => TemplateCategory::Retail,
                'featured' => true,
                'document' => self::squareRetailSale(),
            ],
            [
                'key' => 'square-event-teaser',
                'name' => 'Square event teaser',
                'description' => '1080×1080 session promo with countdown.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::squareEventTeaser(),
            ],
            [
                'key' => 'square-wellness-tip',
                'name' => 'Square wellness tip',
                'description' => '1080×1080 health reminder for clinics and gyms.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::squareWellnessTip(),
            ],
            [
                'key' => 'square-social-quote',
                'name' => 'Square social quote',
                'description' => '1080×1080 community quote for lobby displays.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::squareSocialQuote(),
            ],
            [
                'key' => 'square-product-feature',
                'name' => 'Square product feature',
                'description' => '1080×1080 product hero with price and QR.',
                'category' => TemplateCategory::Retail,
                'featured' => false,
                'document' => self::squareProductFeature(),
            ],
            [
                'key' => 'portrait-ceo-message',
                'name' => 'Portrait CEO message',
                'description' => 'Vertical executive welcome with photo and ticker.',
                'category' => TemplateCategory::Portrait,
                'featured' => false,
                'document' => self::portraitCeoMessage(1080, 1920),
            ],
            [
                'key' => 'portrait-hiring-board',
                'name' => 'Portrait hiring board',
                'description' => 'Vertical careers layout with QR and ticker.',
                'category' => TemplateCategory::Portrait,
                'featured' => false,
                'document' => self::portraitHiringBoard(1080, 1920),
            ],
            [
                'key' => 'portrait-patient-info',
                'name' => 'Portrait patient info',
                'description' => 'Vertical clinic board with safety alert and hours.',
                'category' => TemplateCategory::Healthcare,
                'featured' => false,
                'document' => self::portraitPatientInfo(1080, 1920),
            ],
            [
                'key' => 'portrait-property-listing',
                'name' => 'Portrait property listing',
                'description' => 'Vertical real estate showcase with photo and details.',
                'category' => TemplateCategory::Vertical,
                'featured' => false,
                'document' => self::portraitPropertyListing(1080, 1920),
            ],
            [
                'key' => 'portrait-hotel-amenities',
                'name' => 'Portrait hotel amenities',
                'description' => 'Vertical guest services directory with clocks.',
                'category' => TemplateCategory::Hospitality,
                'featured' => false,
                'document' => self::portraitHotelAmenities(1080, 1920),
            ],
            [
                'key' => 'portrait-kpi-stack',
                'name' => 'Portrait KPI stack',
                'description' => 'Vertical metrics chart with date and clock.',
                'category' => TemplateCategory::Corporate,
                'featured' => false,
                'document' => self::portraitKpiStack(1080, 1920),
            ],
            [
                'key' => 'portrait-sponsor-list',
                'name' => 'Portrait sponsor list',
                'description' => 'Vertical sponsor wall for event lobbies.',
                'category' => TemplateCategory::Events,
                'featured' => false,
                'document' => self::portraitSponsorList(1080, 1920),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function featuredKeys(): array
    {
        return array_values(array_map(
            fn (array $definition): string => $definition['key'],
            array_filter(self::definitions(), fn (array $definition): bool => $definition['featured']),
        ));
    }

    /**
     * @return list<string>
     */
    public static function portraitFeaturedKeys(): array
    {
        return [
            'portrait-lobby-welcome',
            'portrait-menu-board',
            'portrait-retail-promo',
        ];
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function lobbyWelcome(): array
    {
        return self::canvas('#0f172a', [
            self::image(0, 0, 1920, 1080, '/images/catalog/lobby-welcome.jpg', 0, 'Lobby photo'),
            self::shape(0, 0, 1920, 1080, '#0f172a', 1, 0, 0.72),
            self::text(80, 280, 1400, 80, 'Welcome', 36, '#93c5fd', 2),
            self::text(80, 360, 1500, 160, 'You have arrived', 88, '#ffffff', 3),
            self::text(80, 540, 1100, 70, 'Check in at reception · Guest Wi-Fi downstairs', 28, '#cbd5e1', 4),
            self::el('clock', 1480, 40, 400, 120, [
                'format' => 'HH:mm',
                'color' => '#ffffff',
                'fontSize' => 64,
            ], 5, 'Clock'),
            self::ticker(0, 960, 1920, 120, 'Please sign in at reception · Visitor badges are required beyond this floor · Ask the host to collect you', 6),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function newsRssWall(): array
    {
        return self::canvas('#020617', [
            self::image(0, 0, 1920, 1080, '/images/catalog/news-rss-wall.jpg', 0, 'Newsroom photo'),
            self::shape(0, 0, 1920, 1080, '#020617', 1, 0, 0.78),
            self::text(64, 40, 1100, 70, 'Headlines', 48, '#ffffff', 2),
            self::el('clock', 1500, 36, 360, 90, [
                'format' => 'HH:mm',
                'color' => '#7dd3fc',
                'fontSize' => 48,
            ], 3, 'Clock'),
            self::el('news', 64, 140, 1100, 780, [
                'feed_url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                'limit' => 6,
                'color' => '#e2e8f0',
                'fontSize' => 24,
            ], 4, 'News'),
            self::el('rss', 1220, 140, 636, 780, [
                'feed_url' => 'https://feeds.bbci.co.uk/news/world/rss.xml',
                'limit' => 5,
                'color' => '#cbd5e1',
                'fontSize' => 22,
            ], 5, 'World RSS'),
            self::ticker(0, 960, 1920, 120, 'Replace the feed URLs in the inspector · Headlines refresh automatically', 6, 36, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function meetingRoom(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 1080, '/images/catalog/meeting-room.jpg', 0, 'Meeting room photo'),
            self::shape(0, 0, 1920, 1080, '#111827', 1, 0, 0.72),
            self::shape(0, 0, 1920, 160, '#1d4ed8', 2, 0, 0.92),
            self::text(64, 40, 900, 80, 'This room', 40, '#dbeafe', 3),
            self::el('date', 1100, 36, 420, 90, [
                'format' => 'long',
                'color' => '#ffffff',
                'fontSize' => 28,
            ], 4, 'Date'),
            self::el('clock', 1540, 28, 340, 100, [
                'format' => 'HH:mm',
                'color' => '#ffffff',
                'fontSize' => 56,
            ], 5, 'Clock'),
            self::el('room_info', 64, 200, 820, 720, [
                'room_name' => 'Boardroom',
                'status' => 'available',
                'next_meeting' => '14:00 Leadership standup',
                'capacity' => 12,
                'color' => '#ffffff',
                'fontSize' => 48,
            ], 6, 'Room'),
            self::el('booking', 920, 200, 936, 720, [
                'heading' => "Today's bookings",
                'resource' => 'Boardroom',
                'bookings' => "09:00-10:00 | Leadership standup | booked\n10:00-11:00 | Available | available\n11:00-12:30 | Client workshop | booked\n14:00-15:00 | Product review | booked\n16:00-16:30 | Available | available",
                'color' => '#e5e7eb',
                'fontSize' => 24,
            ], 7, 'Bookings'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function socialWall(): array
    {
        return self::canvas('#0f172a', [
            self::image(0, 0, 1920, 1080, '/images/catalog/social-community-wall.jpg', 0, 'Backdrop'),
            self::shape(0, 0, 1920, 1080, '#0f172a', 1, 0, 0.82),
            self::text(80, 48, 1400, 80, 'Community', 52, '#ffffff', 2),
            self::el('social_wall', 80, 160, 1760, 760, [
                'heading' => 'Latest posts',
                'posts' => "@lobby | Welcome to the campus — coffee is on us this morning.\n@cafe | New seasonal drinks are out until Friday.\n@events | Town hall starts at 15:00 in the auditorium.\n@facilities | East entrance is the main door this week.",
                'color' => '#f8fafc',
                'fontSize' => 28,
            ], 3, 'Social wall'),
            self::ticker(0, 960, 1920, 120, 'Edit posts in the inspector · One line per handle | message', 4),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function weatherWorldClock(): array
    {
        return self::canvas('#082f49', [
            self::image(0, 0, 1920, 1080, '/images/catalog/weather-world-clock.jpg', 0, 'Skyline photo'),
            self::shape(0, 0, 1920, 1080, '#082f49', 1, 0, 0.76),
            self::text(64, 48, 900, 70, 'Conditions', 48, '#e0f2fe', 2),
            self::el('date', 1400, 48, 456, 80, [
                'format' => 'long',
                'color' => '#7dd3fc',
                'fontSize' => 28,
            ], 3, 'Date'),
            self::el('weather', 64, 160, 900, 760, [
                'location' => 'Dubai',
                'units' => 'celsius',
                'color' => '#ffffff',
                'fontSize' => 72,
            ], 4, 'Weather'),
            self::el('world_clock', 1000, 160, 856, 760, [
                'cities' => "Dubai | Asia/Dubai\nLondon | Europe/London\nNew York | America/New_York\nSingapore | Asia/Singapore",
                'color' => '#e0f2fe',
                'fontSize' => 40,
            ], 5, 'World clocks'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function internalComms(): array
    {
        return self::canvas('#1e293b', [
            self::image(0, 0, 1920, 1080, '/images/catalog/internal-comms.jpg', 0, 'Office photo'),
            self::shape(0, 0, 1920, 1080, '#1e293b', 1, 0, 0.72),
            self::el('alert_banner', 40, 40, 1840, 280, [
                'severity' => 'info',
                'heading' => 'Today in the building',
                'message' => 'The east lobby is the main entrance while the atrium is refurbished. Use lifts 3–6.',
                'color' => '#ffffff',
                'fontSize' => 36,
            ], 2, 'Alert'),
            self::el('news', 40, 360, 920, 560, [
                'feed_url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                'limit' => 5,
                'color' => '#e2e8f0',
                'fontSize' => 22,
            ], 3, 'News'),
            self::el('web_page', 980, 360, 900, 560, [
                'url' => 'https://example.com',
                'fullscreen' => false,
            ], 4, 'Web page'),
            self::ticker(0, 960, 1920, 120, 'Facilities hotline 200 · Update this banner and the web page URL in the inspector', 5, 38, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function mediaLounge(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 1080, '/images/catalog/media-lounge.jpg', 0, 'Lounge photo'),
            self::shape(0, 0, 1920, 1080, '#111827', 1, 0, 0.7),
            self::text(64, 28, 1100, 60, 'Now showing', 36, '#f8fafc', 2),
            self::el('youtube', 64, 110, 1792, 800, [
                'url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
            ], 3, 'YouTube'),
            self::ticker(0, 940, 1920, 140, 'Muted looping video · Paste any YouTube watch, youtu.be, or embed URL in the inspector', 4, 34, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function healthcareWaitingRoom(): array
    {
        return self::canvas('#0f766e', [
            self::image(0, 0, 1920, 1080, '/images/catalog/healthcare-waiting-room.jpg', 0, 'Clinic photo'),
            self::shape(0, 0, 1920, 1080, '#0f766e', 1, 0, 0.7),
            self::shape(0, 0, 1920, 180, '#0b4f4a', 2, 0, 0.45),
            self::text(64, 36, 1100, 70, 'Welcome to Riverside Clinic', 52, '#ffffff', 2),
            self::text(64, 110, 800, 40, 'Patient information · Floor 1', 22, '#ccfbf1', 3),
            self::el('clock', 1500, 40, 360, 100, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 48], 4, 'Clock'),
            self::shape(64, 240, 900, 620, '#115e59', 3, 24),
            self::text(104, 280, 800, 50, 'Current wait', 28, '#99f6e4', 5),
            self::text(104, 340, 800, 140, '12 min', 96, '#ffffff', 6),
            self::text(104, 500, 800, 80, 'Next: Registration desk B', 32, '#ecfdf5', 7),
            self::text(104, 600, 800, 180, "Please have your ID and insurance card ready.\nAsk a volunteer if you need assistance.", 24, '#ccfbf1', 8),
            self::shape(1020, 240, 836, 620, '#ffffff', 3, 24),
            self::el('calendar', 1060, 280, 756, 540, [
                'heading' => 'Clinic hours',
                'events' => "08:00 | Doors open\n09:00 | General practice\n12:00 | Lunch (limited staff)\n13:00 | Specialist clinics\n17:00 | Last check-in",
                'color' => '#0f172a',
                'fontSize' => 24,
            ], 9, 'Hours'),
            self::ticker(0, 980, 1920, 100, 'Masks available at reception · Free Wi-Fi: ClinicGuest · Emergency: dial 0', 10, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function healthcareWayfinding(): array
    {
        return self::canvas('#f8fafc', [
            self::image(0, 0, 1920, 1080, '/images/catalog/healthcare-wayfinding.jpg', 0, 'Clinic corridor'),
            self::shape(0, 0, 1920, 1080, '#f8fafc', 1, 0, 0.82),
            self::shape(0, 0, 80, 1080, '#0369a1', 2),
            self::text(140, 60, 1400, 80, 'Find your department', 56, '#0f172a', 3),
            self::text(140, 150, 900, 40, 'Follow the colored signs from the main lobby', 22, '#475569', 3),
            self::el('date', 1480, 60, 380, 80, ['format' => 'long', 'color' => '#0369a1', 'fontSize' => 28], 4, 'Date'),
            self::shape(140, 240, 520, 280, '#e0f2fe', 1, 20),
            self::text(180, 280, 440, 40, 'Radiology', 28, '#0369a1', 5),
            self::text(180, 340, 440, 120, "Level 2 · East wing\nTurn left after the cafe", 24, '#0f172a', 6),
            self::shape(700, 240, 520, 280, '#dcfce7', 1, 20),
            self::text(740, 280, 440, 40, 'Pharmacy', 28, '#166534', 7),
            self::text(740, 340, 440, 120, "Ground floor · West\nOpen until 19:00", 24, '#0f172a', 8),
            self::shape(1260, 240, 520, 280, '#fef3c7', 1, 20),
            self::text(1300, 280, 440, 40, 'Emergency', 28, '#92400e', 9),
            self::text(1300, 340, 440, 120, "Ground floor · North\n24 hours", 24, '#0f172a', 10),
            self::shape(140, 560, 1640, 420, '#ffffff', 1, 20),
            self::el('room_info', 180, 600, 1560, 340, [
                'room_name' => 'Outpatients',
                'status' => 'available',
                'next_meeting' => 'Next clinic 14:00 Cardiology',
                'capacity' => 24,
                'color' => '#0f172a',
                'fontSize' => 40,
            ], 11, 'Outpatients'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function corporateLobby(): array
    {
        return self::canvas('#0f172a', [
            self::image(0, 0, 720, 1080, '/images/catalog/corporate-lobby.jpg', 0, 'Lobby photo'),
            self::shape(0, 0, 720, 1080, '#0f172a', 1, 0, 0.62),
            self::text(64, 80, 580, 80, 'Good to see you', 40, '#bfdbfe', 2),
            self::text(64, 180, 580, 200, 'Northridge HQ', 64, '#ffffff', 3),
            self::el('date', 64, 420, 580, 80, ['format' => 'long', 'color' => '#dbeafe', 'fontSize' => 28], 4, 'Date'),
            self::el('clock', 64, 520, 580, 140, ['format' => 'h:mm a', 'color' => '#ffffff', 'fontSize' => 64], 5, 'Clock'),
            self::text(820, 80, 1000, 60, 'Today in the building', 36, '#ffffff', 6),
            self::el('calendar', 820, 170, 1000, 620, [
                'heading' => 'Meetings',
                'events' => "09:00 | Leadership standup · Boardroom\n11:00 | Client workshop · Studio 2\n14:00 | All-hands · Auditorium\n16:30 | New joiner tour",
                'color' => '#f8fafc',
                'fontSize' => 24,
            ], 7, 'Agenda'),
            self::ticker(0, 980, 1920, 100, 'Visitor Wi-Fi: NorthridgeGuest · Sign in at reception · Please wear your badge', 8),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function restaurantMenu(): array
    {
        return self::canvas('#1c1917', [
            self::shape(0, 0, 1920, 160, '#7c2d12', 0),
            self::text(64, 40, 1200, 80, 'Today’s kitchen', 56, '#fff7ed', 2),
            self::el('clock', 1500, 36, 360, 90, ['format' => 'HH:mm', 'color' => '#fed7aa', 'fontSize' => 44], 3, 'Clock'),
            self::el('menu_board', 0, 160, 1240, 920, [
                'heading' => 'À la carte',
                'currency' => 'AED',
                'items' => "Hummus & pita | 28 | Vegan\nGrilled halloumi salad | 42 | Vegetarian\nCatch of the day | 78\nSlow-cooked lamb | 86\nBaklava | 24",
                'color' => '#fff7ed',
                'fontSize' => 36,
            ], 4, 'Menu'),
            self::image(1280, 160, 640, 920, '/images/catalog/restaurant-menu.jpg', 5, 'Dish photo'),
            self::shape(1280, 160, 640, 920, '#292524', 6, 20, 0.45),
            self::text(1336, 220, 528, 50, 'Chef’s special', 28, '#fdba74', 7),
            self::text(1336, 300, 528, 220, "Saffron rice\nand roasted vegetables", 40, '#fff7ed', 8),
            self::text(1336, 560, 528, 80, 'AED 64', 44, '#ffffff', 9),
            self::el('qr_code', 1440, 720, 320, 280, ['value' => 'https://example.com/menu', 'fontSize' => 14], 10, 'QR'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function menuBoard(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 220, '/images/catalog/menu-breakfast-lunch.jpg', 0, 'Food photo'),
            self::shape(0, 0, 1920, 220, '#111827', 1, 0, 0.45),
            self::text(72, 48, 1776, 100, 'All-day board', 72, '#ffffff', 2),
            self::el('menu_board', 0, 220, 960, 860, [
                'heading' => 'Breakfast',
                'currency' => 'AED',
                'items' => "Shakshuka | 38\nAvocado toast | 32 | Vegetarian\nGranola bowl | 26\nFlat white | 18",
                'color' => '#f9fafb',
                'fontSize' => 42,
            ], 3, 'Breakfast'),
            self::el('menu_board', 960, 220, 960, 860, [
                'heading' => 'Lunch',
                'currency' => 'AED',
                'items' => "Chicken wrap | 42\nQuinoa bowl | 36 | Vegetarian\nSoup of the day | 22\nIced tea | 14",
                'color' => '#f9fafb',
                'fontSize' => 42,
            ], 4, 'Lunch'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function retailPromo(): array
    {
        return self::canvas('#1e1b4b', [
            self::image(0, 0, 1920, 1080, '/images/catalog/retail-promo.jpg', 0, 'Campaign photo'),
            self::shape(0, 0, 1920, 1080, '#1e1b4b', 1, 0, 0.78),
            self::text(80, 80, 1200, 80, 'Weekend sale', 28, '#c4b5fd', 2),
            self::text(80, 170, 1400, 180, '30% off autumn', 92, '#ffffff', 3),
            self::text(80, 380, 1100, 80, 'In store and online through Sunday', 32, '#ddd6fe', 4),
            self::shape(80, 500, 420, 140, '#7c3aed', 5, 16),
            self::text(100, 540, 380, 60, 'Use code FALL30', 28, '#ffffff', 6),
            self::el('countdown', 80, 680, 720, 220, [
                'target' => '2027-12-31T20:00:00',
                'label' => 'Offer ends in',
                'color' => '#ffffff',
                'fontSize' => 48,
            ], 7, 'Countdown'),
            self::el('qr_code', 1480, 560, 360, 360, ['value' => 'https://example.com/sale', 'fontSize' => 14], 8, 'Shop QR'),
            self::ticker(0, 980, 1920, 100, 'Open 10:00–22:00 · Click & collect at customer service · Members earn double points', 9, 38),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function educationCampus(): array
    {
        return self::canvas('#172554', [
            self::image(0, 0, 1920, 1080, '/images/catalog/education-campus.jpg', 0, 'Campus photo'),
            self::shape(0, 0, 1920, 1080, '#172554', 1, 0, 0.76),
            self::text(64, 48, 1200, 80, 'Campus today', 52, '#ffffff', 2),
            self::el('date', 1400, 48, 460, 80, ['format' => 'long', 'color' => '#93c5fd', 'fontSize' => 28], 3, 'Date'),
            self::el('calendar', 64, 180, 1100, 740, [
                'heading' => 'This week',
                'events' => "09:00 | Library open study\n12:00 | Career fair · Hall B\n15:00 | Guest lecture: Climate lab\n18:00 | Student union social",
                'color' => '#eff6ff',
                'fontSize' => 24,
            ], 4, 'Events'),
            self::el('alert_banner', 1220, 180, 636, 740, [
                'severity' => 'info',
                'heading' => 'Notice',
                'message' => 'West car park closed Friday. Use the south entrance and shuttle from Gate 2.',
                'color' => '#ffffff',
                'fontSize' => 32,
            ], 5, 'Notice'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function hospitalityWelcome(): array
    {
        return self::canvas('#14532d', [
            self::image(0, 0, 1920, 1080, '/images/catalog/hospitality-welcome.jpg', 0, 'Hotel photo'),
            self::shape(0, 0, 1920, 1080, '#14532d', 1, 0, 0.72),
            self::text(80, 70, 1400, 90, 'Welcome to Palm Court', 56, '#ecfccb', 2),
            self::text(80, 180, 1000, 50, 'A restful stay starts here', 26, '#bbf7d0', 3),
            self::el('world_clock', 80, 280, 1760, 280, [
                'cities' => "Local | Asia/Dubai\nLondon | Europe/London\nNew York | America/New_York",
                'color' => '#dcfce7',
                'fontSize' => 40,
            ], 4, 'Clocks'),
            self::shape(80, 600, 1760, 360, '#052e16', 0, 20),
            self::text(120, 640, 800, 50, 'Concierge', 28, '#86efac', 5),
            self::text(120, 710, 1680, 180, "Check-in from 15:00 · Check-out by 12:00\nSpa bookings at extension 4 · Dinner reservations until 21:30", 28, '#dcfce7', 6),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function eventsAgenda(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 1080, '/images/catalog/events-agenda.jpg', 0, 'Conference photo'),
            self::shape(0, 0, 1920, 1080, '#111827', 1, 0, 0.72),
            self::text(64, 40, 1400, 70, 'Summit agenda', 48, '#ffffff', 2),
            self::el('calendar', 64, 140, 1792, 800, [
                'heading' => 'Main stage',
                'events' => "09:00 | Doors and registration\n10:00 | Opening keynote\n11:30 | Product roadmap\n13:00 | Lunch\n14:30 | Breakouts A / B / C\n16:30 | Closing remarks",
                'color' => '#f3f4f6',
                'fontSize' => 28,
            ], 3, 'Agenda'),
            self::ticker(0, 980, 1920, 100, 'Wi-Fi: Summit2026 · Sessions are recorded · Ask staff for accessibility seating', 4, 42),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function transportDepartures(): array
    {
        return self::canvas('#020617', [
            self::image(0, 0, 1920, 1080, '/images/catalog/transport-departures.jpg', 0, 'Transit photo'),
            self::shape(0, 0, 1920, 1080, '#020617', 1, 0, 0.78),
            self::text(64, 36, 900, 70, 'Departures', 48, '#38bdf8', 2),
            self::el('clock', 1500, 30, 360, 90, ['format' => 'HH:mm:ss', 'color' => '#e0f2fe', 'fontSize' => 44], 3, 'Clock'),
            self::el('booking', 64, 140, 1792, 800, [
                'heading' => 'Next services',
                'resource' => 'Campus shuttle',
                'bookings' => "08:10-08:25 | Gate A · City Center | booked\n08:40-08:55 | Gate B · Airport | booked\n09:15-09:30 | Gate A · City Center | available\n09:45-10:00 | Gate C · Marina | booked",
                'color' => '#e2e8f0',
                'fontSize' => 24,
            ], 4, 'Board'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function announcement(): array
    {
        return self::canvas('#1e293b', [
            self::image(0, 0, 1920, 1080, '/images/catalog/announcements-notice.jpg', 0, 'Building photo'),
            self::shape(0, 0, 1920, 1080, '#1e293b', 1, 0, 0.78),
            self::el('alert_banner', 80, 80, 1760, 760, [
                'severity' => 'warning',
                'heading' => 'Planned maintenance',
                'message' => 'Water will be off on Level 4 from 22:00 to 02:00. Bottled water is available at reception.',
                'color' => '#ffffff',
                'fontSize' => 48,
            ], 2, 'Notice'),
            self::ticker(0, 900, 1920, 180, 'Facilities hotline 200 · Updates will appear here as work completes', 3, 34, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function emergencyNotice(): array
    {
        return self::canvas('#7f1d1d', [
            self::image(0, 0, 1920, 1080, '/images/catalog/emergency-notice.jpg', 0, 'Exit photo'),
            self::shape(0, 0, 1920, 1080, '#7f1d1d', 1, 0, 0.82),
            self::el('alert_banner', 40, 40, 1840, 1000, [
                'severity' => 'critical',
                'heading' => 'Evacuate now',
                'message' => 'Leave by the nearest marked exit. Do not use elevators. Assemble in the south car park and wait for instructions.',
                'color' => '#ffffff',
                'fontSize' => 56,
            ], 2, 'Emergency'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitLobbyWelcome(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(120 * $scale);
        $tickerY = $height - $tickerHeight;
        $heroHeight = (int) round($height * 0.5);

        return self::canvas('#0f172a', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/lobby-welcome-portrait.jpg', 0, 'Lobby photo'),
            self::shape(0, 0, $width, $height, '#0f172a', 1, 0, 0.68),
            self::text((int) round(48 * $scale), (int) round($height * 0.38), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'Welcome', (int) round(32 * $scale), '#93c5fd', 2),
            self::text((int) round(48 * $scale), (int) round($height * 0.44), (int) round($width - 96 * $scale), (int) round(120 * $scale), 'You have arrived', (int) round(64 * $scale), '#ffffff', 3),
            self::text((int) round(48 * $scale), (int) round($height * 0.56), (int) round($width - 96 * $scale), (int) round(50 * $scale), 'Check in at reception · Guest Wi-Fi downstairs', (int) round(24 * $scale), '#cbd5e1', 4),
            self::el('clock', (int) round($width - 340 * $scale), (int) round(40 * $scale), (int) round(300 * $scale), (int) round(100 * $scale), [
                'format' => 'HH:mm',
                'color' => '#ffffff',
                'fontSize' => (int) round(52 * $scale),
            ], 5, 'Clock'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Please sign in at reception · Visitor badges are required beyond this floor · Ask the host to collect you', 6, 40, (int) round(36 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitMenuBoard(int $width, int $height): array
    {
        $scale = $width / 1080;
        $headerHeight = (int) round(140 * $scale);
        $photoHeight = (int) round(280 * $scale);
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        return self::canvas('#1c1917', [
            self::image(0, $headerHeight, $width, $photoHeight, '/images/catalog/portrait-menu-board.jpg', 0, 'Food photo'),
            self::shape(0, $headerHeight, $width, $photoHeight, '#1c1917', 1, 0, 0.28),
            self::shape(0, 0, $width, $headerHeight, '#7c2d12', 2),
            self::text((int) round(48 * $scale), (int) round(36 * $scale), (int) round($width * 0.65), (int) round(70 * $scale), "Today's kitchen", (int) round(44 * $scale), '#fff7ed', 3),
            self::el('clock', (int) round($width - 300 * $scale), (int) round(30 * $scale), (int) round(260 * $scale), (int) round(80 * $scale), [
                'format' => 'HH:mm',
                'color' => '#fed7aa',
                'fontSize' => (int) round(40 * $scale),
            ], 4, 'Clock'),
            self::el('menu_board', 0, $headerHeight + $photoHeight, $width, $tickerY - $headerHeight - $photoHeight, [
                'heading' => 'À la carte',
                'currency' => 'AED',
                'items' => "Hummus & pita | 28 | Vegan\nGrilled halloumi salad | 42 | Vegetarian\nCatch of the day | 78\nSlow-cooked lamb | 86\nBaklava | 24\nSoup of the day | 22\nIced tea | 14",
                'color' => '#fff7ed',
                'fontSize' => (int) round(34 * $scale),
            ], 5, 'Menu'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Ask about today\'s specials · Allergen information available on request', 6, 38, (int) round(32 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitWayfinding(int $width, int $height): array
    {
        $scale = $width / 1080;
        $accentWidth = (int) round(60 * $scale);
        $cardHeight = (int) round(220 * $scale);
        $cardGap = (int) round(24 * $scale);
        $heroHeight = (int) round($height * 0.22);
        $startY = $heroHeight + (int) round(24 * $scale);
        $qrSize = (int) round(min(300 * $scale, $width * 0.35));

        return self::canvas('#f8fafc', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-wayfinding.jpg', 0, 'Lobby photo'),
            self::shape(0, 0, $width, $heroHeight, '#0f172a', 1, 0, 0.35),
            self::shape(0, 0, $accentWidth, $height, '#0369a1', 2),
            self::text((int) round(90 * $scale), (int) round(56 * $scale), (int) round($width - 120 * $scale), (int) round(70 * $scale), 'Find your department', (int) round(44 * $scale), '#ffffff', 3),
            self::text((int) round(90 * $scale), (int) round(130 * $scale), (int) round($width - 120 * $scale), (int) round(40 * $scale), 'Follow the colored signs from the main lobby', (int) round(20 * $scale), '#e2e8f0', 4),
            self::el('date', (int) round(90 * $scale), (int) round(170 * $scale), (int) round(400 * $scale), (int) round(60 * $scale), [
                'format' => 'long',
                'color' => '#0369a1',
                'fontSize' => (int) round(24 * $scale),
            ], 4, 'Date'),
            self::shape((int) round(90 * $scale), $startY, (int) round($width - 120 * $scale), $cardHeight, '#e0f2fe', 1, 20),
            self::text((int) round(120 * $scale), $startY + (int) round(28 * $scale), (int) round($width - 180 * $scale), (int) round(40 * $scale), 'Radiology', (int) round(26 * $scale), '#0369a1', 5),
            self::text((int) round(120 * $scale), $startY + (int) round(72 * $scale), (int) round($width - 180 * $scale), (int) round(100 * $scale), "Level 2 · East wing\nTurn left after the cafe", (int) round(22 * $scale), '#0f172a', 6),
            self::shape((int) round(90 * $scale), $startY + $cardHeight + $cardGap, (int) round($width - 120 * $scale), $cardHeight, '#dcfce7', 1, 20),
            self::text((int) round(120 * $scale), $startY + $cardHeight + $cardGap + (int) round(28 * $scale), (int) round($width - 180 * $scale), (int) round(40 * $scale), 'Pharmacy', (int) round(26 * $scale), '#166534', 7),
            self::text((int) round(120 * $scale), $startY + $cardHeight + $cardGap + (int) round(72 * $scale), (int) round($width - 180 * $scale), (int) round(100 * $scale), "Ground floor · West\nOpen until 19:00", (int) round(22 * $scale), '#0f172a', 8),
            self::shape((int) round(90 * $scale), $startY + ($cardHeight + $cardGap) * 2, (int) round($width - 120 * $scale), $cardHeight, '#fef3c7', 1, 20),
            self::text((int) round(120 * $scale), $startY + ($cardHeight + $cardGap) * 2 + (int) round(28 * $scale), (int) round($width - 180 * $scale), (int) round(40 * $scale), 'Emergency', (int) round(26 * $scale), '#92400e', 9),
            self::text((int) round(120 * $scale), $startY + ($cardHeight + $cardGap) * 2 + (int) round(72 * $scale), (int) round($width - 180 * $scale), (int) round(100 * $scale), "Ground floor · North\n24 hours", (int) round(22 * $scale), '#0f172a', 10),
            self::el('qr_code', (int) round(($width - $qrSize) / 2), $height - $qrSize - (int) round(48 * $scale), $qrSize, $qrSize, [
                'value' => 'https://example.com/directory',
                'fontSize' => 14,
            ], 11, 'Directory QR'),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitRetailPromo(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;
        $heroHeight = (int) round($height * 0.55);
        $qrSize = (int) round(min(280 * $scale, $width * 0.32));

        return self::canvas('#1e1b4b', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/retail-promo-portrait.jpg', 0, 'Campaign photo'),
            self::shape(0, 0, $width, $height, '#1e1b4b', 1, 0, 0.75),
            self::text((int) round(48 * $scale), (int) round(56 * $scale), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'Weekend sale', (int) round(26 * $scale), '#c4b5fd', 2),
            self::text((int) round(48 * $scale), (int) round(120 * $scale), (int) round($width - 96 * $scale), (int) round(140 * $scale), '30% off autumn', (int) round(72 * $scale), '#ffffff', 3),
            self::text((int) round(48 * $scale), (int) round(270 * $scale), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'In store and online through Sunday', (int) round(26 * $scale), '#ddd6fe', 4),
            self::el('countdown', (int) round(48 * $scale), (int) round($height * 0.48), (int) round($width - 96 * $scale), (int) round(180 * $scale), [
                'target' => '2027-12-31T20:00:00',
                'label' => 'Offer ends in',
                'color' => '#ffffff',
                'fontSize' => (int) round(40 * $scale),
            ], 5, 'Countdown'),
            self::el('qr_code', (int) round(($width - $qrSize) / 2), $tickerY - $qrSize - (int) round(32 * $scale), $qrSize, $qrSize, [
                'value' => 'https://example.com/sale',
                'fontSize' => 14,
            ], 6, 'Shop QR'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Open 10:00–22:00 · Click & collect at customer service · Members earn double points', 7, 38, (int) round(32 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitInternalComms(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;
        $alertHeight = (int) round(280 * $scale);
        $photoHeight = (int) round(260 * $scale);

        return self::canvas('#1e293b', [
            self::image(0, $alertHeight + (int) round(72 * $scale), $width, $photoHeight, '/images/catalog/portrait-internal-comms.jpg', 0, 'Office photo'),
            self::shape(0, $alertHeight + (int) round(72 * $scale), $width, $photoHeight, '#1e293b', 1, 0, 0.35),
            self::el('alert_banner', (int) round(40 * $scale), (int) round(40 * $scale), (int) round($width - 80 * $scale), $alertHeight, [
                'severity' => 'info',
                'heading' => 'Today in the building',
                'message' => 'The east lobby is the main entrance while the atrium is refurbished. Use lifts 3–6.',
                'color' => '#ffffff',
                'fontSize' => (int) round(30 * $scale),
            ], 2, 'Alert'),
            self::el('news', (int) round(40 * $scale), $alertHeight + $photoHeight + (int) round(88 * $scale), (int) round($width - 80 * $scale), $tickerY - $alertHeight - $photoHeight - (int) round(112 * $scale), [
                'feed_url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                'limit' => 6,
                'color' => '#e2e8f0',
                'fontSize' => (int) round(22 * $scale),
            ], 3, 'News'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Facilities hotline 200 · Update this banner and feed URL in the inspector', 4, 38, (int) round(32 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function ceoMessage(): array
    {
        return self::canvas('#0c4a6e', [
            self::image(0, 0, 960, 1080, '/images/catalog/ceo-message.jpg', 0, 'Executive photo'),
            self::shape(960, 0, 960, 1080, '#0c4a6e', 1),
            self::text(1020, 120, 820, 50, 'A message from leadership', 24, '#7dd3fc', 2),
            self::text(1020, 190, 820, 200, 'Building what matters together', 56, '#ffffff', 3),
            self::text(1020, 420, 820, 280, "Thank you for the focus and care you bring to our teams every day.\nWe are investing in clearer priorities and better tools this quarter.", 26, '#e0f2fe', 4),
            self::el('date', 1020, 760, 520, 70, ['format' => 'long', 'color' => '#bae6fd', 'fontSize' => 28], 5, 'Date'),
            self::el('clock', 1560, 750, 280, 90, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 48], 6, 'Clock'),
            self::ticker(0, 980, 1920, 100, 'Town hall Thursday 15:00 · Questions welcome at townhall@example.com', 7, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function kpiDashboard(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 280, '/images/catalog/kpi-dashboard.jpg', 0, 'Workplace photo'),
            self::shape(0, 0, 1920, 280, '#1e3a8a', 1, 0, 0.62),
            self::text(64, 40, 900, 70, 'Operations dashboard', 44, '#ffffff', 2),
            self::el('date', 1400, 40, 456, 70, ['format' => 'long', 'color' => '#93c5fd', 'fontSize' => 26], 3, 'Date'),
            self::el('charts', 64, 320, 1100, 600, [
                'title' => 'Today by zone',
                'series' => "Lobby:42\nCafe:18\nGym:9\nParking:31",
                'color' => '#e5e7eb',
                'fontSize' => 20,
            ], 4, 'Charts'),
            self::el('world_clock', 1200, 320, 656, 280, [
                'cities' => "Dubai | Asia/Dubai\nLondon | Europe/London\nSingapore | Asia/Singapore",
                'color' => '#dbeafe',
                'fontSize' => 36,
            ], 5, 'Clocks'),
            self::el('json_api', 1200, 640, 656, 280, [
                'url' => 'https://example.com/api.json',
                'title_path' => 'title',
                'body_path' => 'body',
                'color' => '#f8fafc',
                'fontSize' => 28,
            ], 6, 'Live metric'),
            self::ticker(0, 960, 1920, 120, 'Edit chart series in the inspector · Replace the API URL with your own HTTPS endpoint', 7, 38),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function meetingAgendaBoard(): array
    {
        return self::canvas('#1e293b', [
            self::image(0, 0, 1920, 240, '/images/catalog/meeting-agenda-board.jpg', 0, 'Boardroom photo'),
            self::shape(0, 0, 1920, 240, '#334155', 1, 0, 0.7),
            self::text(64, 50, 800, 70, 'Today’s agenda', 44, '#ffffff', 2),
            self::el('clock', 1500, 42, 360, 90, ['format' => 'HH:mm', 'color' => '#38bdf8', 'fontSize' => 52], 3, 'Clock'),
            self::el('calendar', 64, 280, 1200, 660, [
                'heading' => 'Boardroom schedule',
                'events' => "09:00 | Leadership standup\n10:30 | Budget review\n12:00 | Working lunch\n14:00 | Client presentation\n16:00 | Retrospective",
                'color' => '#f8fafc',
                'fontSize' => 28,
            ], 4, 'Agenda'),
            self::el('room_info', 1300, 280, 556, 660, [
                'room_name' => 'Boardroom',
                'status' => 'occupied',
                'next_meeting' => '16:00 Retrospective',
                'capacity' => 14,
                'color' => '#ffffff',
                'fontSize' => 40,
            ], 5, 'Room'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function patientSafetyReminder(): array
    {
        return self::canvas('#ecfdf5', [
            self::image(0, 0, 1920, 1080, '/images/catalog/patient-safety-reminder.jpg', 0, 'Clinic photo'),
            self::shape(0, 0, 1920, 1080, '#ecfdf5', 1, 0, 0.86),
            self::el('alert_banner', 64, 64, 1792, 320, [
                'severity' => 'info',
                'heading' => 'Patient safety',
                'message' => 'Please sanitize your hands when entering and leaving clinical areas. Masks are available at every desk.',
                'color' => '#064e3b',
                'fontSize' => 36,
            ], 2, 'Safety'),
            self::el('table', 64, 420, 900, 520, [
                'heading' => 'Quick reminders',
                'rows' => "Hand hygiene | Every visit\nVisitor limit | Two per patient\nFlu season | Vaccines at pharmacy\nQuestions | Ask any nurse",
                'color' => '#0f172a',
                'fontSize' => 24,
            ], 3, 'Reminders'),
            self::el('calendar', 1000, 420, 856, 520, [
                'heading' => 'Today',
                'events' => "08:00 | Walk-in clinic opens\n12:00 | Pharmacy lunch break\n17:00 | Last appointments",
                'color' => '#0f172a',
                'fontSize' => 24,
            ], 4, 'Hours'),
            self::ticker(0, 980, 1920, 100, 'Emergency entrance on the north side · Wi-Fi: ClinicGuest', 5, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function clinicHoursBoard(): array
    {
        return self::canvas('#f0f9ff', [
            self::image(0, 0, 1920, 280, '/images/catalog/clinic-hours-board.jpg', 0, 'Clinic photo'),
            self::shape(0, 0, 1920, 280, '#f0f9ff', 1, 0, 0.55),
            self::text(64, 48, 1200, 70, 'Clinic hours', 48, '#0c4a6e', 2),
            self::el('date', 1400, 48, 456, 70, ['format' => 'long', 'color' => '#0369a1', 'fontSize' => 28], 3, 'Date'),
            self::el('table', 64, 320, 1792, 600, [
                'heading' => 'Weekly schedule',
                'rows' => "Monday | 08:00–18:00\nTuesday | 08:00–18:00\nWednesday | 08:00–20:00\nThursday | 08:00–18:00\nFriday | 08:00–16:00\nSaturday | 09:00–13:00\nSunday | Closed",
                'color' => '#0f172a',
                'fontSize' => 28,
            ], 4, 'Hours'),
            self::el('clock', 64, 960, 360, 80, ['format' => 'HH:mm', 'color' => '#0369a1', 'fontSize' => 44], 5, 'Clock'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function allergenNotice(): array
    {
        return self::canvas('#292524', [
            self::image(1320, 300, 520, 680, '/images/catalog/allergen-notice.jpg', 0, 'Kitchen photo'),
            self::shape(1320, 300, 520, 680, '#292524', 1, 0, 0.35),
            self::el('alert_banner', 48, 48, 1824, 220, [
                'severity' => 'warning',
                'heading' => 'Allergen information',
                'message' => 'Ask our team about ingredients. Nuts, gluten, and dairy are used in this kitchen.',
                'color' => '#ffffff',
                'fontSize' => 32,
            ], 2, 'Allergens'),
            self::el('menu_board', 48, 300, 1200, 680, [
                'heading' => 'Today’s menu',
                'currency' => 'AED',
                'items' => "Garden salad | 34 | Vegan\nPasta primavera | 48 | Vegetarian\nGrilled salmon | 72\nChocolate mousse | 28 | Contains dairy",
                'color' => '#fff7ed',
                'fontSize' => 34,
            ], 3, 'Menu'),
            self::el('qr_code', 1420, 480, 320, 320, ['value' => 'https://example.com/allergens', 'fontSize' => 14], 4, 'Allergen QR'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function dailySpecialsBoard(): array
    {
        return self::canvas('#431407', [
            self::image(0, 0, 1920, 520, '/images/catalog/daily-specials-board.jpg', 0, 'Specials photo'),
            self::shape(0, 0, 1920, 1080, '#431407', 1, 0, 0.55),
            self::text(64, 80, 1200, 60, 'Chef’s picks today', 32, '#fed7aa', 2),
            self::text(64, 150, 1400, 120, 'Seasonal tasting menu', 72, '#ffffff', 3),
            self::el('menu_board', 64, 560, 1100, 420, [
                'heading' => 'Specials',
                'currency' => 'AED',
                'items' => "Roasted cauliflower soup | 26\nHarissa lamb | 84\nCitrus tart | 32",
                'color' => '#ffedd5',
                'fontSize' => 36,
            ], 4, 'Specials'),
            self::el('clock', 1500, 580, 360, 90, ['format' => 'HH:mm', 'color' => '#fdba74', 'fontSize' => 44], 5, 'Clock'),
            self::ticker(0, 980, 1920, 100, 'Happy hour 16:00–18:00 · Ask about vegetarian swaps', 6, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function newArrivals(): array
    {
        return self::canvas('#18181b', [
            self::image(0, 0, 1080, 1080, '/images/catalog/new-arrivals.jpg', 0, 'Product photo'),
            self::shape(1080, 0, 840, 1080, '#18181b', 1),
            self::text(1140, 120, 720, 50, 'Just landed', 28, '#a1a1aa', 2),
            self::text(1140, 180, 720, 160, 'Autumn collection', 64, '#ffffff', 3),
            self::text(1140, 380, 680, 120, 'Lightweight layers in teal, sand, and slate.', 26, '#d4d4d8', 4),
            self::el('qr_code', 1320, 720, 360, 280, ['value' => 'https://example.com/new', 'fontSize' => 14], 5, 'Shop QR'),
            self::ticker(0, 980, 1920, 100, 'Available in store today · Members preview one hour early', 6, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function flashSale(): array
    {
        return self::canvas('#450a0a', [
            self::image(0, 0, 1920, 1080, '/images/catalog/flash-sale.jpg', 0, 'Retail photo'),
            self::shape(0, 0, 1920, 1080, '#450a0a', 1, 0, 0.72),
            self::text(80, 100, 1200, 60, 'Flash sale', 32, '#fecaca', 2),
            self::text(80, 180, 1400, 180, '24 hours only', 96, '#ffffff', 3),
            self::el('countdown', 80, 420, 900, 240, [
                'target' => '2027-12-31T23:59:00',
                'label' => 'Ends in',
                'color' => '#ffffff',
                'fontSize' => 52,
            ], 4, 'Countdown'),
            self::shape(80, 700, 420, 120, '#dc2626', 5, 16),
            self::text(100, 735, 380, 50, 'Code FLASH24', 28, '#ffffff', 6),
            self::el('qr_code', 1400, 420, 420, 420, ['value' => 'https://example.com/flash', 'fontSize' => 14], 7, 'Shop QR'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function examSchedule(): array
    {
        return self::canvas('#1e3a8a', [
            self::image(0, 0, 1920, 1080, '/images/catalog/exam-schedule.jpg', 0, 'Campus photo'),
            self::shape(0, 0, 1920, 1080, '#1e3a8a', 1, 0, 0.78),
            self::text(64, 48, 1200, 70, 'Exam schedule', 48, '#ffffff', 2),
            self::el('date', 1400, 48, 456, 70, ['format' => 'long', 'color' => '#bfdbfe', 'fontSize' => 28], 3, 'Date'),
            self::el('calendar', 64, 160, 1792, 780, [
                'heading' => 'This week',
                'events' => "Mon 09:00 | Calculus · Hall A\nTue 13:00 | Biology lab · Room 204\nWed 10:00 | History · Hall C\nThu 14:00 | Literature · Room 118\nFri 09:00 | Physics · Hall B",
                'color' => '#eff6ff',
                'fontSize' => 28,
            ], 4, 'Exams'),
            self::ticker(0, 980, 1920, 100, 'Arrive 20 minutes early · Student ID required at every session', 5, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function campusEventsBoard(): array
    {
        return self::canvas('#312e81', [
            self::image(0, 0, 1920, 1080, '/images/catalog/campus-events-board.jpg', 0, 'Campus photo'),
            self::shape(0, 0, 1920, 1080, '#312e81', 1, 0, 0.76),
            self::text(64, 48, 1000, 70, 'Campus events', 48, '#ffffff', 2),
            self::el('calendar', 64, 160, 1100, 740, [
                'heading' => 'Coming up',
                'events' => "Tue 12:00 | Career fair · Hall B\nWed 15:00 | Guest lecture\nThu 18:00 | Film club\nFri 10:00 | Open day tours",
                'color' => '#eef2ff',
                'fontSize' => 26,
            ], 3, 'Events'),
            self::el('alert_banner', 1220, 160, 636, 740, [
                'severity' => 'info',
                'heading' => 'Parking notice',
                'message' => 'South lot is closed Saturday. Use Gate 2 shuttle from 08:00.',
                'color' => '#ffffff',
                'fontSize' => 30,
            ], 4, 'Notice'),
            self::ticker(0, 960, 1920, 120, 'Follow @campuslife for live updates · Events subject to room changes', 5, 38),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function nowHiring(): array
    {
        return self::canvas('#0f172a', [
            self::image(0, 0, 960, 1080, '/images/catalog/now-hiring.jpg', 0, 'Team photo'),
            self::shape(0, 0, 960, 1080, '#1d4ed8', 1, 0, 0.68),
            self::text(80, 120, 800, 60, 'We are hiring', 32, '#bfdbfe', 2),
            self::text(80, 200, 800, 220, 'Join our growing team', 72, '#ffffff', 3),
            self::text(80, 460, 800, 200, "Engineering · Design · Customer success\nFlexible hybrid roles · Competitive benefits", 26, '#dbeafe', 4),
            self::el('qr_code', 1200, 200, 520, 520, ['value' => 'https://example.com/careers', 'fontSize' => 14], 5, 'Careers QR'),
            self::text(1200, 760, 520, 120, 'Scan to view open roles', 28, '#94a3b8', 6),
            self::ticker(0, 980, 1920, 100, 'Refer a friend · Internal candidates welcome · Equal opportunity employer', 7, 36),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function onboardingWelcome(): array
    {
        return self::canvas('#134e4a', [
            self::image(0, 0, 1920, 420, '/images/catalog/onboarding-welcome.jpg', 0, 'Welcome photo'),
            self::shape(0, 0, 1920, 420, '#134e4a', 1, 0, 0.58),
            self::el('alert_banner', 64, 64, 1792, 260, [
                'severity' => 'info',
                'heading' => 'Welcome aboard',
                'message' => 'Your first week checklist is below. Badge pickup is at reception from 08:30.',
                'color' => '#ffffff',
                'fontSize' => 34,
            ], 1, 'Welcome'),
            self::el('calendar', 64, 360, 1100, 560, [
                'heading' => 'Day one',
                'events' => "09:00 | Welcome coffee\n10:00 | IT setup\n12:00 | Team lunch\n14:00 | Product overview\n16:00 | Office tour",
                'color' => '#ecfdf5',
                'fontSize' => 26,
            ], 2, 'Checklist'),
            self::el('table', 1220, 360, 636, 560, [
                'heading' => 'Need to know',
                'rows' => "Wi-Fi | NorthridgeGuest\nHelpdesk | ext. 500\nEmergency | dial 0\nHR contact | people@example.com",
                'color' => '#ecfdf5',
                'fontSize' => 24,
            ], 3, 'Info'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function propertyShowcase(): array
    {
        return self::canvas('#1c1917', [
            self::image(0, 0, 1200, 1080, '/images/catalog/property-showcase.jpg', 0, 'Property photo'),
            self::shape(1200, 0, 720, 1080, '#1c1917', 1),
            self::text(1250, 100, 620, 50, 'Now showing', 26, '#a8a29e', 2),
            self::text(1250, 160, 620, 140, 'Harbor View Residence', 52, '#ffffff', 3),
            self::el('table', 1250, 340, 620, 420, [
                'heading' => 'Details',
                'rows' => "3 bed · 2 bath\n1,420 sq ft\nBalcony · Parking\nOpen house Sat 11:00",
                'color' => '#fafaf9',
                'fontSize' => 26,
            ], 4, 'Details'),
            self::el('qr_code', 1380, 780, 360, 240, ['value' => 'https://example.com/tour', 'fontSize' => 14], 5, 'Tour QR'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function transitDeparturesTable(): array
    {
        return self::canvas('#020617', [
            self::image(0, 0, 1920, 320, '/images/catalog/transit-departures-table.jpg', 0, 'Transit photo'),
            self::shape(0, 0, 1920, 320, '#020617', 1, 0, 0.62),
            self::text(64, 36, 900, 70, 'Departures', 48, '#38bdf8', 2),
            self::el('clock', 1500, 30, 360, 90, ['format' => 'HH:mm:ss', 'color' => '#e0f2fe', 'fontSize' => 44], 3, 'Clock'),
            self::el('table', 64, 140, 1792, 760, [
                'heading' => 'Next services',
                'rows' => "08:12 | Gate A · City Center | On time\n08:28 | Gate B · Airport | Boarding\n08:45 | Gate C · Marina | On time\n09:02 | Gate A · City Center | Delayed 5 min",
                'color' => '#e2e8f0',
                'fontSize' => 28,
            ], 4, 'Board'),
            self::ticker(0, 940, 1920, 140, 'Real-time updates · Gate changes announced here · Have your ticket ready', 5, 34, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function hotelAmenities(): array
    {
        return self::canvas('#14532d', [
            self::image(0, 0, 1920, 1080, '/images/catalog/hotel-amenities.jpg', 0, 'Hotel photo'),
            self::shape(0, 0, 1920, 1080, '#14532d', 1, 0, 0.74),
            self::text(80, 60, 1400, 80, 'Guest services', 52, '#dcfce7', 2),
            self::el('table', 80, 180, 1760, 720, [
                'heading' => 'Amenities',
                'rows' => "Pool | 07:00–21:00\nSpa | 10:00–20:00\nFitness | 24 hours\nRoom service | Until 23:00\nConcierge | ext. 0",
                'color' => '#ecfccb',
                'fontSize' => 32,
            ], 3, 'Amenities'),
            self::el('world_clock', 80, 920, 1760, 120, [
                'cities' => "Local | Asia/Dubai\nLondon | Europe/London",
                'color' => '#bbf7d0',
                'fontSize' => 36,
            ], 4, 'Clocks'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function guestCheckIn(): array
    {
        return self::canvas('#052e16', [
            self::image(0, 0, 1920, 1080, '/images/catalog/guest-check-in.jpg', 0, 'Hotel lobby photo'),
            self::shape(0, 0, 1920, 1080, '#052e16', 1, 0, 0.74),
            self::text(80, 80, 1200, 80, 'Welcome', 56, '#bbf7d0', 2),
            self::el('room_info', 80, 200, 820, 720, [
                'room_name' => 'Front desk',
                'status' => 'available',
                'next_meeting' => 'Check-in from 15:00',
                'capacity' => 0,
                'color' => '#ffffff',
                'fontSize' => 44,
            ], 3, 'Check-in'),
            self::el('calendar', 940, 200, 900, 720, [
                'heading' => 'Today',
                'events' => "15:00 | Check-in opens\n18:00 | Live music · Lobby\n21:30 | Kitchen closes\n07:00 | Breakfast starts",
                'color' => '#dcfce7',
                'fontSize' => 26,
            ], 4, 'Schedule'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function eventCountdown(): array
    {
        return self::canvas('#4c1d95', [
            self::image(0, 0, 1920, 1080, '/images/catalog/event-countdown.jpg', 0, 'Conference photo'),
            self::shape(0, 0, 1920, 1080, '#4c1d95', 1, 0, 0.72),
            self::text(80, 80, 1400, 70, 'Summit 2026', 48, '#ddd6fe', 2),
            self::text(80, 160, 1400, 160, 'Doors open soon', 88, '#ffffff', 3),
            self::el('countdown', 80, 380, 1200, 260, [
                'target' => '2027-06-01T09:00:00',
                'label' => 'Starts in',
                'color' => '#ffffff',
                'fontSize' => 56,
            ], 4, 'Countdown'),
            self::el('date', 80, 680, 600, 80, ['format' => 'long', 'color' => '#c4b5fd', 'fontSize' => 28], 5, 'Date'),
            self::ticker(0, 900, 1920, 180, 'Thank you to our sponsors · Wi-Fi: Summit2026 · Follow #Summit2026', 6, 34, 40),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function sponsorShowcase(): array
    {
        return self::canvas('#0f172a', [
            self::image(0, 0, 1920, 1080, '/images/catalog/sponsor-showcase.jpg', 0, 'Event backdrop'),
            self::shape(0, 0, 1920, 1080, '#0f172a', 1, 0, 0.84),
            self::text(80, 48, 1400, 70, 'Our partners', 48, '#ffffff', 2),
            self::el('social_wall', 80, 160, 1760, 760, [
                'heading' => 'Sponsors',
                'posts' => "@northwind | Innovation partner\n@contoso | Networking lounge\n@fabrikam | Sustainability stage\n@tailspin | After-party host",
                'color' => '#f8fafc',
                'fontSize' => 28,
            ], 3, 'Sponsors'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function teamMetricsApi(): array
    {
        return self::canvas('#111827', [
            self::image(0, 0, 1920, 220, '/images/catalog/team-metrics-api.jpg', 0, 'Office photo'),
            self::shape(0, 0, 1920, 220, '#111827', 1, 0, 0.55),
            self::text(64, 50, 900, 60, 'Live metrics', 40, '#ffffff', 2),
            self::el('json_api', 64, 260, 900, 660, [
                'url' => 'https://example.com/api/metrics.json',
                'title_path' => 'headline',
                'body_path' => 'summary',
                'color' => '#f8fafc',
                'fontSize' => 36,
            ], 3, 'API headline'),
            self::el('charts', 1000, 260, 856, 660, [
                'title' => 'Weekly trend',
                'series' => "Mon:12\nTue:18\nWed:15\nThu:22\nFri:19",
                'color' => '#dbeafe',
                'fontSize' => 22,
            ], 4, 'Chart'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareBrandSpotlight(): array
    {
        return self::canvas('#0c4a6e', [
            self::image(0, 0, 1080, 720, '/images/catalog/square-brand-spotlight.jpg', 0, 'Brand photo'),
            self::shape(0, 720, 1080, 360, '#0c4a6e', 1),
            self::text(64, 760, 952, 60, 'Northridge', 40, '#7dd3fc', 2),
            self::text(64, 830, 952, 100, 'Ideas that scale', 56, '#ffffff', 3),
            self::el('qr_code', 780, 820, 220, 220, ['value' => 'https://example.com', 'fontSize' => 12], 4, 'QR'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareHiring(): array
    {
        return self::canvas('#1e3a8a', [
            self::image(0, 0, 1080, 480, '/images/catalog/square-hiring.jpg', 0, 'Team photo'),
            self::shape(0, 0, 1080, 480, '#1e3a8a', 1, 0, 0.45),
            self::text(64, 80, 952, 60, 'We are hiring', 32, '#bfdbfe', 2),
            self::text(64, 160, 952, 200, 'Build with us', 72, '#ffffff', 3),
            self::text(64, 400, 952, 120, 'Engineering · Product · Support', 28, '#dbeafe', 4),
            self::el('qr_code', 340, 620, 400, 400, ['value' => 'https://example.com/jobs', 'fontSize' => 14], 5, 'Jobs QR'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareMenuSpecial(): array
    {
        return self::canvas('#7c2d12', [
            self::image(0, 0, 1080, 540, '/images/catalog/square-menu-special.jpg', 0, 'Dish photo'),
            self::text(64, 580, 952, 50, 'Dish of the day', 28, '#fed7aa', 2),
            self::el('menu_board', 64, 640, 952, 380, [
                'heading' => 'Special',
                'currency' => 'AED',
                'items' => "Harissa lamb | 84\nRoasted vegetables | included\nAdd dessert | +24",
                'color' => '#fff7ed',
                'fontSize' => 34,
            ], 3, 'Special'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareRetailSale(): array
    {
        return self::canvas('#581c87', [
            self::image(0, 0, 1080, 1080, '/images/catalog/square-retail-sale.jpg', 0, 'Product photo'),
            self::shape(0, 0, 1080, 1080, '#581c87', 1, 0, 0.72),
            self::text(64, 80, 952, 60, 'Limited offer', 28, '#e9d5ff', 2),
            self::text(64, 150, 952, 180, '40% off', 96, '#ffffff', 3),
            self::el('countdown', 64, 380, 952, 200, [
                'target' => '2027-12-31T20:00:00',
                'label' => 'Ends in',
                'color' => '#ffffff',
                'fontSize' => 44,
            ], 4, 'Countdown'),
            self::text(64, 620, 952, 60, 'In store this weekend', 26, '#ddd6fe', 5),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareEventTeaser(): array
    {
        return self::canvas('#312e81', [
            self::image(0, 0, 1080, 1080, '/images/catalog/square-event-teaser.jpg', 0, 'Stage photo'),
            self::shape(0, 0, 1080, 1080, '#312e81', 1, 0, 0.72),
            self::text(64, 80, 952, 60, 'Main stage', 28, '#c7d2fe', 2),
            self::text(64, 150, 952, 160, 'Keynote preview', 64, '#ffffff', 3),
            self::el('countdown', 64, 360, 952, 220, [
                'target' => '2027-06-01T10:00:00',
                'label' => 'Starts in',
                'color' => '#ffffff',
                'fontSize' => 44,
            ], 4, 'Countdown'),
            self::text(64, 620, 952, 80, 'Doors 09:30 · Auditorium', 26, '#e0e7ff', 5),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareWellnessTip(): array
    {
        return self::canvas('#115e59', [
            self::image(0, 0, 1080, 1080, '/images/catalog/square-wellness-tip.jpg', 0, 'Wellness photo'),
            self::shape(0, 0, 1080, 1080, '#115e59', 1, 0, 0.72),
            self::el('alert_banner', 48, 48, 984, 420, [
                'severity' => 'info',
                'heading' => 'Wellness tip',
                'message' => 'Take a short walk every hour. Stretch your shoulders and drink water.',
                'color' => '#ffffff',
                'fontSize' => 36,
            ], 2, 'Tip'),
            self::text(64, 520, 952, 120, 'Small habits add up to better energy through the day.', 28, '#ccfbf1', 3),
            self::el('clock', 64, 720, 400, 100, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 48], 4, 'Clock'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareSocialQuote(): array
    {
        return self::canvas('#1e293b', [
            self::image(0, 0, 1080, 1080, '/images/catalog/square-social-quote.jpg', 0, 'Community photo'),
            self::shape(0, 0, 1080, 1080, '#1e293b', 1, 0, 0.78),
            self::text(64, 120, 952, 400, '"Great teams make hard work feel lighter."', 48, '#ffffff', 2),
            self::text(64, 560, 952, 60, '@community', 28, '#94a3b8', 3),
            self::el('social_wall', 64, 660, 952, 360, [
                'heading' => 'More posts',
                'posts' => "@lobby | Welcome back!\n@events | Town hall Friday",
                'color' => '#e2e8f0',
                'fontSize' => 22,
            ], 4, 'Posts'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareProductFeature(): array
    {
        return self::canvas('#18181b', [
            self::image(0, 0, 1080, 620, '/images/catalog/square-product-feature.jpg', 0, 'Product'),
            self::text(64, 660, 700, 60, 'Studio headphones', 36, '#ffffff', 2),
            self::text(64, 730, 400, 50, 'AED 499', 40, '#a1a1aa', 3),
            self::el('qr_code', 720, 680, 300, 300, ['value' => 'https://example.com/product', 'fontSize' => 12], 4, 'Product QR'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitCeoMessage(int $width, int $height): array
    {
        $scale = $width / 1080;
        $heroHeight = (int) round($height * 0.45);
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        return self::canvas('#0c4a6e', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-ceo-message.jpg', 0, 'Executive photo'),
            self::shape(0, 0, $width, $height, '#0c4a6e', 1, 0, 0.72),
            self::text((int) round(48 * $scale), (int) round($height * 0.42), (int) round($width - 96 * $scale), (int) round(50 * $scale), 'Leadership message', (int) round(24 * $scale), '#7dd3fc', 2),
            self::text((int) round(48 * $scale), (int) round($height * 0.47), (int) round($width - 96 * $scale), (int) round(120 * $scale), 'Thank you for showing up with care', (int) round(48 * $scale), '#ffffff', 3),
            self::text((int) round(48 * $scale), (int) round($height * 0.58), (int) round($width - 96 * $scale), (int) round(160 * $scale), 'We are investing in clearer priorities and better tools this quarter.', (int) round(24 * $scale), '#e0f2fe', 4),
            self::el('date', (int) round(48 * $scale), $tickerY - (int) round(100 * $scale), (int) round(400 * $scale), (int) round(60 * $scale), [
                'format' => 'long',
                'color' => '#bae6fd',
                'fontSize' => (int) round(24 * $scale),
            ], 5, 'Date'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Town hall Thursday 15:00 · Questions welcome', 6, 38, (int) round(32 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitHiringBoard(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;
        $heroHeight = (int) round($height * 0.42);
        $qrSize = (int) round(min(360 * $scale, $width * 0.42));

        return self::canvas('#1e3a8a', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-hiring-board.jpg', 0, 'Team photo'),
            self::shape(0, 0, $width, $heroHeight, '#1e3a8a', 1, 0, 0.55),
            self::text((int) round(48 * $scale), (int) round(80 * $scale), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'Now hiring', (int) round(32 * $scale), '#bfdbfe', 2),
            self::text((int) round(48 * $scale), (int) round(160 * $scale), (int) round($width - 96 * $scale), (int) round(160 * $scale), 'Join our team', (int) round(64 * $scale), '#ffffff', 3),
            self::text((int) round(48 * $scale), (int) round(340 * $scale), (int) round($width - 96 * $scale), (int) round(200 * $scale), "Engineering\nDesign\nCustomer success", (int) round(28 * $scale), '#dbeafe', 4),
            self::el('qr_code', (int) round(($width - $qrSize) / 2), (int) round($height * 0.52), $qrSize, $qrSize, [
                'value' => 'https://example.com/careers',
                'fontSize' => 14,
            ], 5, 'Careers QR'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Scan to view open roles · Equal opportunity employer', 6, 38, (int) round(32 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitPatientInfo(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        return self::canvas('#ecfdf5', [
            self::image(0, 0, $width, (int) round(320 * $scale), '/images/catalog/portrait-patient-info.jpg', 0, 'Clinic photo'),
            self::shape(0, 0, $width, (int) round(320 * $scale), '#ecfdf5', 1, 0, 0.4),
            self::el('alert_banner', (int) round(40 * $scale), (int) round(40 * $scale), (int) round($width - 80 * $scale), (int) round(280 * $scale), [
                'severity' => 'info',
                'heading' => 'Patient information',
                'message' => 'Please sanitize your hands when entering clinical areas.',
                'color' => '#064e3b',
                'fontSize' => (int) round(28 * $scale),
            ], 2, 'Safety'),
            self::el('table', (int) round(40 * $scale), (int) round(360 * $scale), (int) round($width - 80 * $scale), (int) round($height * 0.35), [
                'heading' => 'Quick reminders',
                'rows' => "Hand hygiene | Every visit\nVisitor limit | Two per patient\nQuestions | Ask any nurse",
                'color' => '#0f172a',
                'fontSize' => (int) round(22 * $scale),
            ], 3, 'Reminders'),
            self::el('calendar', (int) round(40 * $scale), (int) round($height * 0.52), (int) round($width - 80 * $scale), $tickerY - (int) round($height * 0.52) - (int) round(24 * $scale), [
                'heading' => 'Clinic hours',
                'events' => "08:00 | Doors open\n17:00 | Last check-in",
                'color' => '#0f172a',
                'fontSize' => (int) round(22 * $scale),
            ], 4, 'Hours'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Emergency entrance on the north side', 5, 36, (int) round(30 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitPropertyListing(int $width, int $height): array
    {
        $scale = $width / 1080;
        $heroHeight = (int) round($height * 0.42);
        $qrSize = (int) round(min(280 * $scale, $width * 0.35));

        return self::canvas('#1c1917', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-property-listing.jpg', 0, 'Property photo'),
            self::shape(0, $heroHeight, $width, $height - $heroHeight, '#1c1917', 1),
            self::text((int) round(48 * $scale), $heroHeight + (int) round(40 * $scale), (int) round($width - 96 * $scale), (int) round(120 * $scale), 'Harbor View Residence', (int) round(44 * $scale), '#ffffff', 2),
            self::el('table', (int) round(48 * $scale), $heroHeight + (int) round(180 * $scale), (int) round($width - 96 * $scale), (int) round($height * 0.28), [
                'heading' => 'Details',
                'rows' => "3 bed · 2 bath\n1,420 sq ft\nOpen house Sat 11:00",
                'color' => '#fafaf9',
                'fontSize' => (int) round(24 * $scale),
            ], 3, 'Details'),
            self::el('qr_code', (int) round(($width - $qrSize) / 2), $height - $qrSize - (int) round(48 * $scale), $qrSize, $qrSize, [
                'value' => 'https://example.com/tour',
                'fontSize' => 14,
            ], 4, 'Tour QR'),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitHotelAmenities(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        $heroHeight = (int) round($height * 0.28);

        return self::canvas('#14532d', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-hotel-amenities.jpg', 0, 'Hotel photo'),
            self::shape(0, 0, $width, $heroHeight, '#14532d', 1, 0, 0.55),
            self::text((int) round(48 * $scale), (int) round(56 * $scale), (int) round($width - 96 * $scale), (int) round(70 * $scale), 'Guest services', (int) round(40 * $scale), '#dcfce7', 2),
            self::el('table', (int) round(48 * $scale), $heroHeight + (int) round(40 * $scale), (int) round($width - 96 * $scale), $tickerY - $heroHeight - (int) round(60 * $scale), [
                'heading' => 'Amenities',
                'rows' => "Pool | 07:00–21:00\nSpa | 10:00–20:00\nFitness | 24 hours\nConcierge | ext. 0",
                'color' => '#ecfccb',
                'fontSize' => (int) round(26 * $scale),
            ], 3, 'Amenities'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Check-in from 15:00 · Room service until 23:00', 4, 36, (int) round(30 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitKpiStack(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        $headerHeight = (int) round(280 * $scale);

        return self::canvas('#111827', [
            self::image(0, 0, $width, $headerHeight, '/images/catalog/portrait-kpi-stack.jpg', 0, 'Workplace photo'),
            self::shape(0, 0, $width, $headerHeight, '#111827', 1, 0, 0.55),
            self::text((int) round(48 * $scale), (int) round(48 * $scale), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'Metrics', (int) round(36 * $scale), '#ffffff', 2),
            self::el('date', (int) round(48 * $scale), (int) round(120 * $scale), (int) round(400 * $scale), (int) round(60 * $scale), [
                'format' => 'long',
                'color' => '#93c5fd',
                'fontSize' => (int) round(24 * $scale),
            ], 3, 'Date'),
            self::el('charts', (int) round(48 * $scale), $headerHeight + (int) round(24 * $scale), (int) round($width - 96 * $scale), $tickerY - $headerHeight - (int) round(140 * $scale), [
                'title' => 'Today by zone',
                'series' => "Lobby:42\nCafe:18\nGym:9",
                'color' => '#e5e7eb',
                'fontSize' => (int) round(20 * $scale),
            ], 4, 'Charts'),
            self::el('clock', (int) round(48 * $scale), $tickerY - (int) round(100 * $scale), (int) round(280 * $scale), (int) round(80 * $scale), [
                'format' => 'HH:mm',
                'color' => '#38bdf8',
                'fontSize' => (int) round(44 * $scale),
            ], 5, 'Clock'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Edit chart series in the inspector', 6, 36, (int) round(30 * $scale)),
        ], $width, $height);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitSponsorList(int $width, int $height): array
    {
        $scale = $width / 1080;
        $tickerHeight = (int) round(100 * $scale);
        $tickerY = $height - $tickerHeight;

        $heroHeight = (int) round($height * 0.28);

        return self::canvas('#0f172a', [
            self::image(0, 0, $width, $heroHeight, '/images/catalog/portrait-sponsor-list.jpg', 0, 'Event photo'),
            self::shape(0, 0, $width, $heroHeight, '#0f172a', 1, 0, 0.55),
            self::text((int) round(48 * $scale), (int) round(48 * $scale), (int) round($width - 96 * $scale), (int) round(60 * $scale), 'Our partners', (int) round(36 * $scale), '#ffffff', 2),
            self::el('social_wall', (int) round(48 * $scale), $heroHeight + (int) round(24 * $scale), (int) round($width - 96 * $scale), $tickerY - $heroHeight - (int) round(40 * $scale), [
                'heading' => 'Sponsors',
                'posts' => "@northwind | Innovation partner\n@contoso | Networking lounge\n@fabrikam | Sustainability stage\n@tailspin | After-party host",
                'color' => '#f8fafc',
                'fontSize' => (int) round(24 * $scale),
            ], 3, 'Sponsors'),
            self::ticker(0, $tickerY, $width, $tickerHeight, 'Thank you to our sponsors · Wi-Fi: Summit2026', 4, 36, (int) round(30 * $scale)),
        ], $width, $height);
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function canvas(string $background, array $elements, int $width = 1920, int $height = 1080): array
    {
        return DesignDocument::normalize([
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'elements' => $elements,
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    protected static function el(
        string $type,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props,
        int $zIndex,
        string $name = '',
        float $opacity = 1,
    ): array {
        $name = $name !== '' ? $name : ucfirst(str_replace('_', ' ', $type));

        return [
            'id' => $type.'-'.$zIndex,
            'type' => $type,
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'rotation' => 0,
            'opacity' => $opacity,
            'zIndex' => $zIndex,
            'locked' => false,
            'hidden' => false,
            'props' => $props,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function shape(float $x, float $y, float $width, float $height, string $fill, int $zIndex, int $radius = 0, float $opacity = 1): array
    {
        return self::el('shape', $x, $y, $width, $height, ['fill' => $fill, 'radius' => $radius], $zIndex, 'Shape', $opacity);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function image(float $x, float $y, float $width, float $height, string $src, int $zIndex, string $name = 'Photo'): array
    {
        return self::el('image', $x, $y, $width, $height, [
            'src' => $src,
            'media_id' => null,
            'objectFit' => 'cover',
        ], $zIndex, $name);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function ticker(
        float $x,
        float $y,
        float $width,
        float $height,
        string $text,
        int $zIndex,
        int $speed = 40,
        int $fontSize = 48,
    ): array {
        return self::el('ticker', $x, $y, $width, $height, [
            'text' => $text,
            'speed' => $speed,
            'color' => '#ffffff',
            'background' => 'transparent',
            'background_opacity' => 100,
            'fontSize' => $fontSize,
        ], $zIndex, 'Ticker');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function text(
        float $x,
        float $y,
        float $width,
        float $height,
        string $text,
        int $fontSize,
        string $color,
        int $zIndex,
    ): array {
        return self::el('text', $x, $y, $width, $height, [
            'text' => $text,
            'fontSize' => $fontSize,
            'color' => $color,
            'align' => 'left',
            'fontWeight' => '600',
        ], $zIndex, 'Text');
    }
}
