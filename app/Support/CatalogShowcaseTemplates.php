<?php

namespace App\Support;

use App\Enums\TemplateCategory;

/**
 * Image-rich catalog layouts built on the designer's graphics library.
 *
 * These combine generated background artwork, stickers, the Icon element and
 * the newer widgets (analog clock, forecast, KPI tiles, gauges, schedules), so
 * they show off what a finished screen can look like without any uploads.
 */
final class CatalogShowcaseTemplates
{
    /**
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
            self::definition('cafe-illustrated-menu', 'Illustrated cafe menu', 'Priced drinks and bakes with dietary stickers, icons and a live clock.', TemplateCategory::Restaurant, true, self::cafeMenu()),
            self::definition('safety-first-board', 'Safety first board', 'Days-without-incident counter, compliance gauge and safety reminders.', TemplateCategory::Announcements, true, self::safetyBoard()),
            self::definition('sales-kpi-wall', 'Sales KPI wall', 'Metric tiles, goal progress and a target gauge for sales floors.', TemplateCategory::Corporate, true, self::salesKpiWall()),
            self::definition('hospital-directory', 'Hospital directory', 'Department directory with icons, arrows, weather and clock.', TemplateCategory::Healthcare, true, self::hospitalDirectory()),
            self::definition('conference-live-schedule', 'Conference live schedule', 'Sessions that highlight what is live now, with a countdown and quotes.', TemplateCategory::Events, true, self::conferenceSchedule()),
            self::definition('team-celebrations', 'Team celebrations', 'Birthdays, anniversaries and new starters with festive stickers.', TemplateCategory::Corporate, false, self::teamCelebrations()),
            self::definition('retail-mega-sale', 'Mega sale', 'Bold sale bursts, ribbons and a countdown to the end of the offer.', TemplateCategory::Retail, true, self::megaSale()),
            self::definition('hotel-lobby-forecast', 'Hotel lobby forecast', 'Five-day forecast, world clocks and guest amenity icons.', TemplateCategory::Hospitality, true, self::hotelForecast()),
            self::definition('gym-class-board', 'Gym class board', 'Class schedule with live status, member goals and fitness icons.', TemplateCategory::Events, false, self::gymClassBoard()),
            self::definition('square-quote-of-the-day', 'Quote of the day', 'Rotating quotations on a square canvas for lobbies and lifts.', TemplateCategory::Announcements, false, self::squareQuote()),
            self::definition('portrait-icon-wayfinding', 'Portrait icon wayfinding', 'Tall directory with large icons and arrows for corridors.', TemplateCategory::Portrait, true, self::portraitWayfinding()),
            self::definition('portrait-campus-open-day', 'Campus open day', 'Portrait agenda with icons, countdown and a welcome sticker.', TemplateCategory::Education, false, self::portraitOpenDay()),

            // Room booking: one layout per common screen shape.
            self::definition('room-door-sign', 'Meeting room door sign', 'Full-screen room status: free, starting soon or in use, the current meeting, what is next, and a QR code to book. 16:9 landscape.', TemplateCategory::Meeting, true, self::roomDoorSign()),
            self::definition('portrait-room-availability', 'Room availability lobby board', 'Every meeting room with a live free or busy label, plus the date and time. 9:16 portrait for lobbies and lifts.', TemplateCategory::Meeting, true, self::portraitRoomAvailability()),
            self::definition('room-door-panel', 'Room door panel (10-inch tablet)', 'Door-side room panel sized for 1280×800 tablets: live status, up next and scan to book.', TemplateCategory::Meeting, false, self::roomDoorPanel()),
            self::definition('square-room-sign', 'Square meeting room sign', 'Compact room status sign for 1:1 screens beside a door.', TemplateCategory::Meeting, false, self::squareRoomSign()),
        ];
    }

    /**
     * Background artwork themes for these layouts, merged into CatalogArtwork.
     *
     * @return array<string, array{list<string>, string, string}>
     */
    public static function artwork(): array
    {
        return [
            'cafe-illustrated-menu' => [['#1f130a', '#7c3f16', '#f6c177'], 'bokeh', '#fff7ed'],
            'safety-first-board' => [['#0b1f14', '#15803d', '#bbf7d0'], 'grid', '#f0fdf4'],
            'sales-kpi-wall' => [['#0a0f1f', '#312e81', '#a5b4fc'], 'contour', '#eef2ff'],
            'hospital-directory' => [['#062033', '#0e7490', '#a5f3fc'], 'waves', '#ecfeff'],
            'conference-live-schedule' => [['#150a26', '#6d28d9', '#f0abfc'], 'rays', '#faf5ff'],
            'team-celebrations' => [['#2a0d24', '#be185d', '#fbcfe8'], 'bokeh', '#fdf2f8'],
            'retail-mega-sale' => [['#2b0610', '#be123c', '#fdba74'], 'prism', '#fff7ed'],
            'hotel-lobby-forecast' => [['#101826', '#44403c', '#fcd34d'], 'arcs', '#fffbeb'],
            'gym-class-board' => [['#0a0a0a', '#9f1239', '#fb7185'], 'prism', '#fff1f2'],
            'square-quote-of-the-day' => [['#111827', '#1e3a8a', '#93c5fd'], 'waves', '#eff6ff'],
            'portrait-icon-wayfinding' => [['#071a2c', '#1d4ed8', '#93c5fd'], 'arcs', '#eff6ff'],
            'portrait-campus-open-day' => [['#10132b', '#4338ca', '#c7d2fe'], 'bokeh', '#eef2ff'],
            'room-door-sign' => [['#0b1324', '#1e3a8a', '#93c5fd'], 'contour', '#eff6ff'],
            'portrait-room-availability' => [['#0a1a1f', '#0f766e', '#99f6e4'], 'arcs', '#f0fdfa'],
            'room-door-panel' => [['#0f1115', '#334155', '#cbd5e1'], 'grid', '#f8fafc'],
            'square-room-sign' => [['#140f24', '#4c1d95', '#c4b5fd'], 'waves', '#f5f3ff'],
        ];
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function cafeMenu(): array
    {
        return self::canvas('#1f130a', [
            self::art('cafe-illustrated-menu', 0),
            self::shape(60, 60, 1120, 960, '#1c1917', 1, 36, 0.82),
            self::icon(110, 110, 110, 110, 'coffee', 2, '#f6c177', '#3b2412'),
            self::text(250, 110, 900, 110, 'Morning Brew Cafe', 76, '#fff7ed', 3, '800'),
            self::widget('menu_board', 110, 250, 1020, 720, [
                'heading' => 'Coffee & bakes',
                'currency' => 'AED',
                'items' => "Flat white | 18 | Double shot\nOat latte | 21 | Barista oat\nCold brew | 19 | 18-hour steep\nAlmond croissant | 16 | Baked this morning\nBanana bread | 14 | Vegan\nMatcha latte | 22 | Ceremonial grade",
                'color' => '#fff7ed',
                'fontSize' => 44,
            ], 4, 'Menu'),
            self::sticker(1230, 90, 300, 300, 'best-seller', 5),
            self::sticker(1560, 130, 260, 260, 'vegan', 6),
            self::shape(1230, 470, 630, 550, '#1c1917', 7, 36, 0.82),
            self::widget('analog_clock', 1330, 500, 430, 430, [
                'face' => 'light',
                'show_seconds' => true,
                'label' => '',
                'color' => '#c2410c',
            ], 8, 'Clock'),
            self::text(1260, 940, 570, 60, 'Open daily 7:00 - 19:00', 32, '#f6c177', 9, '600', 'center'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function safetyBoard(): array
    {
        return self::canvas('#0b1f14', [
            self::art('safety-first-board', 0),
            self::icon(80, 70, 120, 120, 'safety', 1, '#ffffff', '#16a34a'),
            self::text(230, 70, 1200, 120, 'Safety first, every shift', 72, '#ffffff', 2, '800'),
            self::shape(80, 240, 820, 760, '#052e16', 3, 40, 0.85),
            self::widget('safety_counter', 110, 300, 760, 520, [
                'since' => '2026-06-01',
                'label' => 'Days without a lost-time incident',
                'record' => 412,
                'color' => '#bbf7d0',
                'fontSize' => 220,
            ], 4, 'Safety counter'),
            self::text(110, 850, 760, 120, 'Report every near miss. It keeps this number growing.', 32, '#dcfce7', 5, '400', 'center'),
            self::shape(960, 240, 880, 420, '#052e16', 6, 40, 0.85),
            self::widget('gauge', 990, 270, 420, 360, [
                'label' => 'PPE compliance',
                'value' => 96,
                'min' => 0,
                'max' => 100,
                'suffix' => '%',
                'color' => '#4ade80',
                'fontSize' => 72,
            ], 7, 'PPE gauge'),
            self::widget('progress_goal', 1420, 280, 400, 360, [
                'heading' => 'Training',
                'goals' => "Forklift | 42 | 50\nFirst aid | 18 | 20\nFire warden | 9 | 12",
                'suffix' => '',
                'color' => '#ffffff',
                'fontSize' => 24,
            ], 8, 'Training progress'),
            self::shape(960, 700, 880, 300, '#052e16', 9, 40, 0.85),
            self::icon(1000, 750, 90, 90, 'warning', 10, '#fde047', 'transparent'),
            self::text(1110, 740, 700, 110, 'Hard hats and hi-vis beyond the yellow line', 36, '#ffffff', 11, '700'),
            self::icon(1000, 880, 90, 90, 'first-aid', 12, '#fca5a5', 'transparent'),
            self::text(1110, 870, 700, 110, 'First aid: ext. 222  |  Assembly point B', 32, '#dcfce7', 13, '400'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function salesKpiWall(): array
    {
        return self::canvas('#0a0f1f', [
            self::art('sales-kpi-wall', 0),
            self::text(80, 50, 1100, 100, 'Sales performance', 72, '#ffffff', 1, '800'),
            self::widget('clock', 1500, 40, 340, 120, ['format' => 'HH:mm', 'color' => '#c7d2fe', 'fontSize' => 64], 2, 'Clock'),
            self::widget('metric_tiles', 60, 180, 1200, 420, [
                'heading' => 'Today',
                'metrics' => "Revenue | AED 248k | +14%\nDeals closed | 37 | +6\nAvg. deal | AED 6.7k | +3%\nChurn | 1.2% | -0.4",
                'columns' => 4,
                'color' => '#ffffff',
                'fontSize' => 56,
            ], 3, 'KPIs'),
            self::widget('gauge', 1320, 180, 540, 420, [
                'label' => 'Monthly target',
                'value' => 78,
                'min' => 0,
                'max' => 100,
                'suffix' => '%',
                'color' => '#a5b4fc',
                'fontSize' => 80,
            ], 4, 'Target gauge'),
            self::shape(60, 640, 900, 380, '#111827', 5, 28, 0.75),
            self::widget('progress_goal', 90, 660, 840, 340, [
                'heading' => 'Quarter goals',
                'goals' => "New logos | 42 | 60\nExpansion | 310 | 400\nPipeline | 1.8 | 2.5",
                'suffix' => '',
                'color' => '#ffffff',
                'fontSize' => 30,
            ], 6, 'Goals'),
            self::shape(1000, 640, 860, 380, '#111827', 7, 28, 0.75),
            self::widget('charts', 1020, 650, 820, 360, [
                'title' => 'Deals by region',
                'series' => "North:42\nSouth:31\nEast:27\nWest:38",
                'color' => '#e0e7ff',
                'fontSize' => 22,
            ], 8, 'Regions'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function hospitalDirectory(): array
    {
        $rows = [
            ['heartbeat', 'Cardiology', 'Level 3'],
            ['stethoscope', 'Outpatients', 'Ground floor'],
            ['pharmacy', 'Pharmacy', 'Ground floor'],
            ['baby', 'Maternity', 'Level 2'],
            ['first-aid', 'Emergency', 'Level 1'],
        ];
        $elements = [
            self::art('hospital-directory', 0),
            self::shape(0, 0, 1920, 150, '#083344', 1, 0, 0.85),
            self::icon(60, 25, 100, 100, 'first-aid', 2, '#ffffff', '#dc2626', 'square'),
            self::text(190, 25, 1100, 100, 'Welcome to City General', 64, '#ffffff', 3, '800'),
            self::widget('clock', 1540, 20, 330, 110, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 60], 4, 'Clock'),
            self::shape(60, 200, 1200, 820, '#ffffff', 5, 36, 0.94),
        ];
        $z = 6;

        foreach ($rows as $index => [$icon, $label, $floor]) {
            $y = 240 + ($index * 155);
            $elements[] = self::icon(100, $y, 120, 120, $icon, $z++, '#ffffff', '#0e7490');
            $elements[] = self::text(250, $y, 560, 120, $label, 52, '#0f172a', $z++, '700');
            $elements[] = self::text(820, $y, 300, 120, $floor, 36, '#475569', $z++, '400');
            $elements[] = self::icon(1110, $y + 20, 80, 80, $index % 2 === 0 ? 'arrow-right' : 'arrow-up', $z++, '#0e7490', 'transparent');
        }

        $elements[] = self::widget('weather', 1320, 200, 540, 380, ['location' => 'Dubai', 'units' => 'celsius', 'color' => '#ffffff', 'fontSize' => 72], $z++, 'Weather');
        $elements[] = self::shape(1320, 620, 540, 400, '#083344', $z++, 36, 0.85);
        $elements[] = self::icon(1360, 660, 90, 90, 'wifi', $z++, '#a5f3fc', 'transparent');
        $elements[] = self::text(1470, 660, 380, 90, 'Free Wi-Fi: CityGuest', 32, '#ffffff', $z++, '600');
        $elements[] = self::icon(1360, 780, 90, 90, 'dining', $z++, '#a5f3fc', 'transparent');
        $elements[] = self::text(1470, 780, 380, 90, 'Cafe: Ground floor', 32, '#ffffff', $z++, '600');
        $elements[] = self::icon(1360, 900, 90, 90, 'parking', $z++, '#a5f3fc', 'transparent');
        $elements[] = self::text(1470, 900, 380, 90, 'Parking: Levels B1-B2', 32, '#ffffff', $z, '600');

        return self::canvas('#062033', $elements);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function conferenceSchedule(): array
    {
        return self::canvas('#150a26', [
            self::art('conference-live-schedule', 0),
            self::text(80, 60, 1100, 100, 'Future of Retail Summit', 72, '#ffffff', 1, '800'),
            self::text(80, 160, 1100, 60, 'Hall A  |  Studio 2  |  Atrium', 32, '#f0abfc', 2, '600'),
            self::shape(60, 260, 1180, 760, '#0f0a1f', 3, 36, 0.82),
            self::widget('event_schedule', 80, 280, 1140, 720, [
                'heading' => 'Today',
                'sessions' => "09:00-10:00 | Opening keynote | Hall A\n10:15-11:00 | Retail media deep dive | Studio 2\n11:15-12:00 | Store of the future panel | Hall A\n13:00-14:00 | Networking lunch | Atrium\n14:15-15:00 | AI for merchandising | Studio 2\n15:30-16:30 | Closing fireside chat | Hall A",
                'color' => '#ffffff',
                'fontSize' => 34,
            ], 4, 'Schedule'),
            self::shape(1290, 260, 570, 360, '#0f0a1f', 5, 36, 0.82),
            self::widget('countdown', 1310, 280, 530, 320, [
                'target' => '2026-12-31T18:00',
                'label' => 'Awards night in',
                'color' => '#f5d0fe',
                'fontSize' => 72,
            ], 6, 'Countdown'),
            self::sticker(1330, 650, 180, 144, 'quote-mark', 7),
            self::widget('quote', 1290, 690, 570, 330, [
                'quotes' => "Retail is detail. | James Timpson\nThe customer is always the headline. | Summit team",
                'rotate_seconds' => 10,
                'color' => '#ffffff',
                'fontSize' => 38,
            ], 8, 'Quote'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function teamCelebrations(): array
    {
        return self::canvas('#2a0d24', [
            self::art('team-celebrations', 0),
            self::icon(90, 70, 130, 130, 'gift', 1, '#ffffff', '#db2777'),
            self::text(250, 70, 1200, 130, 'This week we celebrate', 76, '#ffffff', 2, '800'),
            self::sticker(1430, 60, 420, 92, 'star-rating', 3),
            self::shape(80, 250, 1760, 640, '#1f0a1a', 4, 40, 0.8),
            self::widget('celebrations', 110, 270, 1700, 600, [
                'heading' => '',
                'people' => "Amara Osei | Work anniversary | 5 years\nLuis Navarro | Birthday | Tuesday\nPriya Shah | Welcome aboard | Monday\nTom Becker | Retirement | Friday",
                'color' => '#ffffff',
                'fontSize' => 44,
            ], 5, 'Celebrations'),
            self::sticker(560, 920, 800, 140, 'wave-divider', 6),
            self::text(80, 950, 1760, 80, 'Cake in the kitchen at 15:00. Everyone is welcome.', 36, '#fbcfe8', 7, '600', 'center'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function megaSale(): array
    {
        return self::canvas('#2b0610', [
            self::art('retail-mega-sale', 0),
            self::sticker(110, 90, 520, 520, 'sale-burst', 1),
            self::text(700, 120, 1150, 170, 'MEGA SALE', 150, '#ffffff', 2, '800'),
            self::text(700, 300, 1150, 90, 'Up to 50% off storewide', 56, '#fed7aa', 3, '600'),
            self::sticker(700, 420, 720, 200, 'today-only', 4),
            self::sticker(1500, 400, 340, 340, 'percent-off', 5),
            self::shape(110, 680, 1740, 300, '#450a0a', 6, 36, 0.75),
            self::icon(160, 730, 120, 120, 'shopping', 7, '#ffffff', '#e11d48'),
            self::text(310, 720, 700, 80, 'Free gift over AED 300', 44, '#ffffff', 8, '700'),
            self::text(310, 800, 700, 80, 'Ask at any till', 32, '#fecdd3', 9, '400'),
            self::widget('countdown', 1080, 700, 740, 260, [
                'target' => '2026-12-31T23:59',
                'label' => 'Offer ends in',
                'color' => '#ffffff',
                'fontSize' => 76,
            ], 10, 'Countdown'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function hotelForecast(): array
    {
        return self::canvas('#101826', [
            self::art('hotel-lobby-forecast', 0),
            self::sticker(60, 40, 620, 202, 'welcome', 1),
            self::text(700, 90, 1160, 90, 'to The Grand Palm', 64, '#fde68a', 2, '700'),
            self::shape(60, 270, 1220, 480, '#1c1917', 3, 36, 0.78),
            self::widget('weather_forecast', 80, 290, 1180, 440, [
                'location' => 'Dubai',
                'units' => 'celsius',
                'days' => 5,
                'color' => '#ffffff',
                'fontSize' => 36,
            ], 4, 'Forecast'),
            self::shape(1320, 270, 540, 480, '#1c1917', 5, 36, 0.78),
            self::widget('analog_clock', 1400, 300, 380, 380, [
                'face' => 'dark',
                'show_seconds' => true,
                'label' => 'Dubai',
                'color' => '#fcd34d',
            ], 6, 'Clock'),
            self::shape(60, 790, 1800, 230, '#1c1917', 7, 36, 0.78),
            ...self::amenity(110, 830, 'wifi', 'Wi-Fi: PalmGuest', 8),
            ...self::amenity(560, 830, 'dining', 'Breakfast 6:30-10:30', 10),
            ...self::amenity(1010, 830, 'fitness', 'Gym: Level 2, 24h', 12),
            ...self::amenity(1460, 830, 'bed', 'Late checkout: ext. 0', 14),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function gymClassBoard(): array
    {
        return self::canvas('#0a0a0a', [
            self::art('gym-class-board', 0),
            self::icon(80, 60, 130, 130, 'fitness', 1, '#ffffff', '#e11d48'),
            self::text(240, 60, 1100, 130, 'Today\'s classes', 80, '#ffffff', 2, '800'),
            self::widget('clock', 1520, 60, 340, 130, ['format' => 'HH:mm', 'color' => '#fecdd3', 'fontSize' => 72], 3, 'Clock'),
            self::shape(60, 230, 1160, 790, '#0a0a0a', 4, 32, 0.75),
            self::widget('event_schedule', 80, 250, 1120, 750, [
                'heading' => 'Studio schedule',
                'sessions' => "06:30-07:15 | HIIT Burn | Studio 1\n08:00-08:45 | Spin Power | Cycle room\n12:15-12:45 | Lunch Express Core | Studio 2\n17:30-18:30 | Strength Club | Weights floor\n19:00-20:00 | Yoga Flow | Studio 2",
                'color' => '#ffffff',
                'fontSize' => 34,
            ], 5, 'Classes'),
            self::shape(1260, 230, 600, 470, '#0a0a0a', 6, 32, 0.75),
            self::widget('progress_goal', 1280, 250, 560, 430, [
                'heading' => 'Member challenge',
                'goals' => "Km run | 8420 | 10000\nClasses booked | 612 | 750\nPlank minutes | 380 | 500",
                'suffix' => '',
                'color' => '#ffffff',
                'fontSize' => 28,
            ], 7, 'Challenge'),
            self::shape(1260, 740, 600, 280, '#9f1239', 8, 32, 0.9),
            self::icon(1300, 790, 90, 90, 'heartbeat', 9, '#ffffff', 'transparent'),
            self::text(1410, 770, 430, 220, 'Book at the front desk or in the app', 36, '#ffffff', 10, '700'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareQuote(): array
    {
        return self::canvas('#111827', [
            self::art('square-quote-of-the-day', 0),
            self::sticker(80, 70, 220, 176, 'quote-mark', 1),
            self::text(80, 270, 920, 80, 'QUOTE OF THE DAY', 36, '#93c5fd', 2, '700'),
            self::widget('quote', 60, 360, 960, 560, [
                'quotes' => "The best way to predict the future is to create it. | Peter Drucker\nQuality is not an act, it is a habit. | Aristotle\nAlone we can do so little; together we can do so much. | Helen Keller",
                'rotate_seconds' => 15,
                'color' => '#ffffff',
                'fontSize' => 56,
            ], 3, 'Quote'),
        ], 1080, 1080);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitWayfinding(): array
    {
        $rows = [
            ['elevator', 'Lifts', 'arrow-right'],
            ['restroom', 'Restrooms', 'arrow-left'],
            ['dining', 'Food court', 'arrow-up'],
            ['meeting', 'Conference rooms', 'arrow-right'],
            ['parking', 'Car park', 'arrow-down'],
            ['accessibility', 'Accessible exit', 'arrow-left'],
        ];
        $elements = [
            self::art('portrait-icon-wayfinding', 0),
            self::icon(70, 80, 140, 140, 'map-pin', 1, '#ffffff', '#1d4ed8'),
            self::text(240, 80, 780, 140, 'You are here: Level 1', 60, '#ffffff', 2, '800'),
        ];
        $z = 3;

        foreach ($rows as $index => [$icon, $label, $arrow]) {
            $y = 300 + ($index * 230);
            $elements[] = self::shape(50, $y, 980, 200, '#0b1f3a', $z++, 28, 0.85);
            $elements[] = self::icon(90, $y + 30, 140, 140, $icon, $z++, '#ffffff', '#2563eb', 'square');
            $elements[] = self::text(270, $y + 30, 560, 140, $label, 52, '#ffffff', $z++, '700');
            $elements[] = self::icon(850, $y + 40, 120, 120, $arrow, $z++, '#93c5fd', 'transparent');
        }

        $elements[] = self::widget('clock', 60, 1720, 960, 140, ['format' => 'HH:mm', 'color' => '#bfdbfe', 'fontSize' => 72], $z, 'Clock');

        return self::canvas('#071a2c', $elements, 1080, 1920);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitOpenDay(): array
    {
        return self::canvas('#10132b', [
            self::art('portrait-campus-open-day', 0),
            self::sticker(90, 70, 900, 292, 'welcome', 1),
            self::text(60, 380, 960, 110, 'Campus open day', 84, '#ffffff', 2, '800', 'center'),
            self::icon(470, 520, 140, 140, 'education', 3, '#ffffff', '#4338ca'),
            self::shape(50, 710, 980, 820, '#0b0e24', 4, 36, 0.82),
            self::widget('event_schedule', 70, 730, 940, 780, [
                'heading' => 'Saturday',
                'sessions' => "10:00-10:30 | Welcome talk | Main hall\n10:45-11:30 | Campus tours | Library steps\n11:30-12:30 | Taster lectures | Science block\n13:00-14:00 | Student life Q&A | Union\n14:00-15:00 | Scholarships clinic | Room 104",
                'color' => '#ffffff',
                'fontSize' => 32,
            ], 5, 'Agenda'),
            self::widget('countdown', 60, 1580, 960, 280, [
                'target' => '2026-11-14T10:00',
                'label' => 'Doors open in',
                'color' => '#c7d2fe',
                'fontSize' => 80,
            ], 6, 'Countdown'),
        ], 1080, 1920);
    }

    /**
     * Room status settings shared by the door-sign layouts. The room is left
     * unchosen: the sign shows a sample until a room is picked in settings.
     *
     * @return array<string, mixed>
     */
    private static function roomStatusProps(int $fontSize, int $upcoming): array
    {
        return [
            'room_id' => '0',
            'show_qr' => true,
            'upcoming' => $upcoming,
            'soon_minutes' => 10,
            'fontSize' => $fontSize,
        ];
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function roomDoorSign(): array
    {
        return self::canvas('#0b1324', [
            self::art('room-door-sign', 0),
            self::icon(60, 36, 88, 88, 'meeting', 1, '#ffffff', '#2563eb', 'square'),
            self::text(170, 36, 1100, 88, 'Meeting space', 48, '#ffffff', 2, '700'),
            self::widget('date', 1120, 30, 460, 100, ['format' => 'long', 'color' => '#bfdbfe', 'fontSize' => 26], 3, 'Date'),
            self::widget('clock', 1580, 26, 300, 110, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 56], 4, 'Clock'),
            self::shape(40, 160, 1840, 880, '#020617', 5, 36, 0.35),
            self::widget('room_status', 60, 180, 1800, 840, self::roomStatusProps(76, 4), 6, 'Room status'),
        ]);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function portraitRoomAvailability(): array
    {
        return self::canvas('#0a1a1f', [
            self::art('portrait-room-availability', 0),
            self::icon(60, 80, 130, 130, 'calendar', 1, '#ffffff', '#0f766e'),
            self::text(220, 80, 800, 130, 'Meeting rooms', 72, '#ffffff', 2, '800'),
            self::widget('date', 60, 230, 960, 110, ['format' => 'long', 'color' => '#99f6e4', 'fontSize' => 40], 3, 'Date'),
            self::shape(40, 370, 1000, 1180, '#021014', 4, 40, 0.6),
            self::widget('room_board', 60, 390, 960, 1140, [
                'heading' => '',
                'location_id' => '0',
                'limit' => 8,
                'color' => '#ffffff',
                'fontSize' => 44,
            ], 5, 'Room availability'),
            self::shape(40, 1590, 1000, 290, '#0f766e', 6, 40, 0.9),
            self::widget('clock', 60, 1610, 440, 250, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 96], 7, 'Clock'),
            self::icon(540, 1650, 110, 110, 'qr-code', 8, '#0f766e', '#ffffff', 'square'),
            self::text(680, 1620, 340, 230, 'Scan the code on any room door to book it', 34, '#ffffff', 9, '600'),
        ], 1080, 1920);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function roomDoorPanel(): array
    {
        return self::canvas('#0f1115', [
            self::art('room-door-panel', 0),
            self::widget('room_status', 0, 0, 1280, 716, self::roomStatusProps(54, 3), 1, 'Room status'),
            self::shape(0, 716, 1280, 84, '#0f172a', 2, 0, 0.95),
            self::icon(24, 732, 52, 52, 'wifi', 3, '#94a3b8', 'transparent'),
            self::text(88, 726, 520, 64, 'Wi-Fi: Guest  ·  Help desk ext. 200', 26, '#cbd5e1', 4, '600'),
            self::widget('date', 620, 720, 420, 76, ['format' => 'long', 'color' => '#cbd5e1', 'fontSize' => 22], 5, 'Date'),
            self::widget('clock', 1040, 716, 230, 84, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 40], 6, 'Clock'),
        ], 1280, 800);
    }

    /**
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    protected static function squareRoomSign(): array
    {
        return self::canvas('#140f24', [
            self::art('square-room-sign', 0),
            self::widget('room_status', 40, 40, 1000, 860, self::roomStatusProps(52, 2), 1, 'Room status'),
            self::shape(40, 930, 1000, 110, '#1e1b4b', 2, 28, 0.85),
            self::icon(64, 950, 70, 70, 'calendar', 3, '#c4b5fd', 'transparent'),
            self::text(150, 940, 600, 90, 'Book at the door or scan the code', 30, '#ffffff', 4, '600'),
            self::widget('clock', 780, 935, 240, 100, ['format' => 'HH:mm', 'color' => '#ffffff', 'fontSize' => 48], 5, 'Clock'),
        ], 1080, 1080);
    }

    /**
     * @param  array{width: int, height: int, background: string, elements: list<array<string, mixed>>}  $document
     * @return array{key: string, name: string, description: string, category: TemplateCategory, featured: bool, document: array{width: int, height: int, background: string, elements: list<array<string, mixed>>}}
     */
    private static function definition(string $key, string $name, string $description, TemplateCategory $category, bool $featured, array $document): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'featured' => $featured,
            'document' => $document,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    private static function canvas(string $background, array $elements, int $width = 1920, int $height = 1080): array
    {
        return DesignDocument::normalize([
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'elements' => array_map(function (array $element) use ($width, $height): array {
                // Full-bleed artwork is authored at 1920x1080; stretch it to
                // portrait and square canvases instead of hand-sizing each one.
                if (($element['name'] ?? '') === 'Background artwork') {
                    $element['width'] = $width;
                    $element['height'] = $height;
                }

                return $element;
            }, $elements),
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function el(string $type, float $x, float $y, float $width, float $height, array $props, int $zIndex, string $name, float $opacity = 1): array
    {
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
    private static function art(string $key, int $zIndex): array
    {
        return self::el('image', 0, 0, 1920, 1080, [
            'src' => CatalogArtwork::source($key),
            'media_id' => null,
            'objectFit' => 'cover',
        ], $zIndex, 'Background artwork');
    }

    /**
     * @return array<string, mixed>
     */
    private static function sticker(float $x, float $y, float $width, float $height, string $key, int $zIndex): array
    {
        return self::el('image', $x, $y, $width, $height, [
            'src' => DesignStickers::source($key),
            'media_id' => null,
            'objectFit' => 'contain',
        ], $zIndex, DesignStickers::catalog()[$key]['label'] ?? 'Sticker');
    }

    /**
     * @return array<string, mixed>
     */
    private static function icon(float $x, float $y, float $width, float $height, string $icon, int $zIndex, string $color, string $background, string $badgeShape = 'circle'): array
    {
        return self::el('icon', $x, $y, $width, $height, [
            'icon' => $icon,
            'color' => $color,
            'strokeWidth' => 2,
            'background' => $background,
            'badgeShape' => $badgeShape,
        ], $zIndex, ucfirst(str_replace('-', ' ', $icon)).' icon');
    }

    /**
     * @return array<string, mixed>
     */
    private static function shape(float $x, float $y, float $width, float $height, string $fill, int $zIndex, int $radius = 0, float $opacity = 1): array
    {
        return self::el('shape', $x, $y, $width, $height, ['fill' => $fill, 'radius' => $radius], $zIndex, 'Panel', $opacity);
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(float $x, float $y, float $width, float $height, string $text, int $fontSize, string $color, int $zIndex, string $weight = '600', string $align = 'left'): array
    {
        return self::el('text', $x, $y, $width, $height, [
            'text' => $text,
            'fontSize' => $fontSize,
            'color' => $color,
            'align' => $align,
            'fontWeight' => $weight,
        ], $zIndex, 'Text');
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function widget(string $type, float $x, float $y, float $width, float $height, array $props, int $zIndex, string $name): array
    {
        return self::el($type, $x, $y, $width, $height, $props, $zIndex, $name);
    }

    /**
     * Icon plus caption pair used on the hotel amenities strip.
     *
     * @return list<array<string, mixed>>
     */
    private static function amenity(float $x, float $y, string $icon, string $label, int $zIndex): array
    {
        return [
            self::el('icon', $x, $y, 110, 110, [
                'icon' => $icon,
                'color' => '#1c1917',
                'strokeWidth' => 2,
                'background' => '#fcd34d',
                'badgeShape' => 'circle',
            ], $zIndex, $label.' icon'),
            self::text($x + 130, $y, 290, 110, $label, 28, '#fef3c7', $zIndex + 1, '600'),
        ];
    }
}
