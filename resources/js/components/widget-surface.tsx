import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
    type ReactNode,
} from 'react';
import WebPageEmbed from '@/components/web-page-embed';
import YoutubeEmbed from '@/components/youtube-embed';
import {
    formatMinutes,
    roomState,
    upcomingBookings,
    type ScreenBooking,
} from '@/lib/room-status';
import { canonicalWidgetKey } from '@/lib/widget-keys';
import {
    scaleWidgetFontToBox,
    widgetContainerFontSize,
    widgetFill,
    widgetFontSize,
} from '@/lib/widget-style';
import { youtubeId } from '@/lib/youtube';
import type { WidgetJsonValue, WidgetPayload } from '@/types';

function widgetColor(
    settings: Record<string, WidgetJsonValue>,
    fallback = '#ffffff',
): string {
    return String(settings.color ?? fallback);
}

function widgetTextShadow(fill: string): CSSProperties['textShadow'] {
    return fill === 'transparent'
        ? '0 1px 8px rgba(0,0,0,0.85), 0 0 2px rgba(0,0,0,0.9)'
        : undefined;
}

function parseLines(value: unknown): string[] {
    return String(value ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

function useWidgetBoxMinEdge() {
    const ref = useRef<HTMLDivElement>(null);
    const [minEdge, setMinEdge] = useState(0);

    useEffect(() => {
        const node = ref.current;

        if (!node) {
            return;
        }

        const update = (width: number, height: number) => {
            setMinEdge(Math.min(width, height));
        };

        update(node.clientWidth, node.clientHeight);

        const observer = new ResizeObserver(([entry]) => {
            update(entry.contentRect.width, entry.contentRect.height);
        });
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return { ref, minEdge };
}

/**
 * Advances through a list on a fixed interval, for widgets that cycle content.
 */
function useRotation(count: number, seconds: number): number {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        setIndex(0);

        if (count < 2) {
            return;
        }

        const timer = window.setInterval(
            () => setIndex((current) => (current + 1) % count),
            Math.max(2, seconds) * 1000,
        );

        return () => window.clearInterval(timer);
    }, [count, seconds]);

    return count > 0 ? index % count : 0;
}

/**
 * Splits `a | b | c` rows into trimmed cells, padded to the expected width.
 */
function parseCells(value: unknown, columns: number): string[][] {
    return parseLines(value).map((line) => {
        const cells = line.split('|').map((part) => part.trim());

        return Array.from(
            { length: columns },
            (_, index) => cells[index] ?? '',
        );
    });
}

function menuFont(base: number, minEdge: number): string | number {
    return minEdge > 0
        ? scaleWidgetFontToBox(base, minEdge)
        : widgetContainerFontSize(base);
}

function bookingSlots(
    settings: Record<string, unknown>,
    data: Record<string, unknown>,
    now: Date,
    timezone: string,
): unknown[] {
    if (Array.isArray(data.slots) && data.slots.length > 0) {
        return data.slots;
    }

    let minutes = now.getHours() * 60 + now.getMinutes();

    try {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).formatToParts(now);
        const hour = Number(
            parts.find((part) => part.type === 'hour')?.value ?? 0,
        );
        const minute = Number(
            parts.find((part) => part.type === 'minute')?.value ?? 0,
        );
        minutes = hour * 60 + minute;
    } catch {
        // Use local time when the timezone is invalid.
    }

    return parseLines(settings.bookings).map((line) => {
        const [when = '', title = when, status = 'booked'] = line
            .split('|')
            .map((part) => part.trim());
        const match = when.match(/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/);
        const start = match ? Number(match[1]) * 60 + Number(match[2]) : null;
        const end = match ? Number(match[3]) * 60 + Number(match[4]) : null;

        return {
            when,
            title: title || when,
            status,
            is_now:
                start !== null &&
                end !== null &&
                minutes >= start &&
                minutes < end,
            is_next: start !== null && start > minutes,
        };
    });
}

export default function WidgetSurface({
    widget,
    timezone = 'UTC',
    staticPreview = false,
}: {
    widget: WidgetPayload;
    timezone?: string;
    /** Replace iframes with posters so dense galleries stay light. */
    staticPreview?: boolean;
}) {
    const key = canonicalWidgetKey(widget.key);
    const settings = widget.settings;
    const data = widget.data;
    const [now, setNow] = useState(() => new Date());
    const menuBox = useWidgetBoxMinEdge();
    const quoteLines = useMemo(
        () => parseLines(settings.quotes),
        [settings.quotes],
    );
    const galleryLines = useMemo(
        () => parseLines(settings.images),
        [settings.images],
    );
    const rotationIndex = useRotation(
        key === 'quote'
            ? quoteLines.length
            : key === 'image_gallery'
              ? galleryLines.length
              : 0,
        Math.max(2, Number(settings.rotate_seconds ?? 8)),
    );

    useEffect(() => {
        if (
            ![
                'clock',
                'date',
                'countdown',
                'world_clock',
                'booking',
                'analog_clock',
                'event_schedule',
                'room_status',
                'room_board',
            ].includes(key)
        ) {
            return;
        }

        const timer = window.setInterval(() => setNow(new Date()), 1000);

        return () => window.clearInterval(timer);
    }, [key]);

    const zoned = useMemo(() => {
        try {
            return new Date(
                now.toLocaleString('en-US', { timeZone: timezone }),
            );
        } catch {
            return now;
        }
    }, [now, timezone]);

    if (key === 'room_status' && !data.room) {
        return (
            <RoomStatusSurface
                data={sampleRoomData(now)}
                settings={settings}
                now={now}
                staticPreview
                notice={
                    staticPreview
                        ? undefined
                        : typeof data.error === 'string'
                          ? data.error
                          : 'Choose a room in the widget settings.'
                }
            />
        );
    }

    if (typeof data.error === 'string') {
        return (
            <Fallback
                title={
                    typeof settings.location === 'string'
                        ? settings.location
                        : widgetTitle(key)
                }
                body={data.error}
            />
        );
    }

    if (key.startsWith('queue_')) {
        return (
            <QueueWidgetSurface
                widgetKey={key}
                settings={settings}
                data={data}
            />
        );
    }

    if (key === 'clock') {
        const format = String(settings.format ?? 'HH:mm');
        const text =
            format === 'h:mm a'
                ? zoned.toLocaleTimeString([], {
                      hour: 'numeric',
                      minute: '2-digit',
                  })
                : format === 'HH:mm:ss'
                  ? zoned.toLocaleTimeString([], { hour12: false })
                  : zoned.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                    });

        return (
            <Hero
                color={widgetColor(settings)}
                fontSize={widgetFontSize(settings, 60)}
                label="Clock"
                value={text}
            />
        );
    }

    if (key === 'date') {
        const format = String(settings.format ?? 'long');
        const text =
            format === 'iso'
                ? zoned.toISOString().slice(0, 10)
                : format === 'short'
                  ? zoned.toLocaleDateString()
                  : zoned.toLocaleDateString([], {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                    });

        return (
            <Hero
                color={widgetColor(settings)}
                fontSize={widgetFontSize(settings, 48)}
                label="Date"
                value={text}
            />
        );
    }

    if (key === 'weather') {
        const temp = data.temperature;
        const unit = data.units === 'fahrenheit' ? '°F' : '°C';

        return (
            <Hero
                color={widgetColor(settings)}
                fontSize={widgetFontSize(settings, 60)}
                label={String(data.place ?? settings.location ?? 'Weather')}
                value={
                    temp === null || temp === undefined ? '—' : `${temp}${unit}`
                }
            />
        );
    }

    if (key === 'rss' || key === 'news') {
        const items = Array.isArray(data.items) ? data.items : [];

        const fontSize = widgetFontSize(settings, 24);

        return (
            <div
                className="flex h-full flex-col justify-center gap-3 p-8"
                style={{ color: widgetColor(settings) }}
            >
                <p
                    className="tracking-[0.2em] uppercase"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.45)),
                    }}
                >
                    {key === 'news' ? 'News' : 'Headlines'}
                </p>
                {items.length === 0 ? (
                    <p className="text-white/70">No headlines yet.</p>
                ) : (
                    items.map((item, index) => (
                        <p
                            key={index}
                            className="font-medium"
                            style={{ fontSize, lineHeight: 1.2 }}
                        >
                            {typeof item === 'object' && item && 'title' in item
                                ? String(item.title)
                                : String(item)}
                        </p>
                    ))
                )}
            </div>
        );
    }

    if (key === 'qr_code') {
        const value = String(settings.value ?? '');

        return (
            <div className="flex h-full flex-col items-center justify-center gap-4 bg-white p-8 text-black">
                {value ? (
                    <img
                        alt=""
                        className="max-h-[70%] max-w-[70%] bg-white object-contain"
                        src={`https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=8&data=${encodeURIComponent(value)}`}
                    />
                ) : (
                    <p className="text-black/50">Add a URL or message</p>
                )}
                <p
                    className="max-w-md text-center break-all"
                    style={{ fontSize: widgetFontSize(settings, 14) }}
                >
                    {value}
                </p>
            </div>
        );
    }

    if (key === 'web_page') {
        const url = String(settings.url ?? data.url ?? '');
        const blocked =
            data.embed_blocked === true || data.embed_blocked === 'true';

        if (!url) {
            return <Fallback title="Web page" body="Add a URL" />;
        }

        if (blocked) {
            return (
                <div className="flex h-full flex-col items-center justify-center gap-2 bg-slate-200 p-6 text-center text-slate-800">
                    <p className="text-lg font-semibold">
                        This site does not allow embedding
                    </p>
                    <p className="max-w-md text-sm text-slate-600">
                        This URL sent X-Frame-Options or Content-Security-Policy
                        frame-ancestors that block iframes. Use a page that
                        allows embedding, such as example.com or your own site.
                    </p>
                </div>
            );
        }

        if (staticPreview) {
            return <WebPagePoster url={url} />;
        }

        return <WebPageEmbed url={url} title="Web page" />;
    }

    if (key === 'youtube') {
        const id = youtubeId(String(settings.url ?? ''));

        if (id && staticPreview) {
            return (
                <div className="relative h-full w-full bg-black">
                    <img
                        alt=""
                        loading="lazy"
                        className="h-full w-full object-cover"
                        src={`https://i.ytimg.com/vi/${id}/hqdefault.jpg`}
                    />
                    <span className="absolute inset-0 m-auto flex h-[18%] w-[14%] min-w-8 items-center justify-center rounded-[18%] bg-red-600 text-white">
                        <svg
                            viewBox="0 0 24 24"
                            className="h-1/2 w-1/2"
                            fill="currentColor"
                            aria-hidden
                        >
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </span>
                </div>
            );
        }

        return id ? (
            <YoutubeEmbed videoId={id} />
        ) : (
            <Fallback
                title="YouTube"
                body="Paste a watch, youtu.be, embed, Shorts, or live URL"
            />
        );
    }

    if (key === 'calendar') {
        const events = parseLines(settings.events);
        const fontSize = widgetFontSize(settings, 24);

        return (
            <div
                className="flex h-full flex-col justify-center gap-3 p-8"
                style={{ color: widgetColor(settings) }}
            >
                <p
                    className="tracking-[0.2em] uppercase"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.55)),
                    }}
                >
                    {String(settings.heading ?? 'Calendar')}
                </p>
                {events.map((event) => (
                    <p key={event} style={{ fontSize, lineHeight: 1.2 }}>
                        {event}
                    </p>
                ))}
            </div>
        );
    }

    if (key === 'countdown') {
        const target = Date.parse(String(settings.target ?? ''));
        const remaining = Number.isNaN(target)
            ? 0
            : Math.max(0, target - now.getTime());
        const total = Math.floor(remaining / 1000);
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const seconds = total % 60;
        const stamp = [hours, minutes, seconds]
            .map((part) => String(part).padStart(2, '0'))
            .join(':');

        return (
            <Hero
                color={widgetColor(settings)}
                fontSize={widgetFontSize(settings, 60)}
                label={String(settings.label ?? 'Countdown')}
                value={stamp}
            />
        );
    }

    if (key === 'ticker') {
        const text = String(settings.text ?? '');
        const speed = Math.max(10, Number(settings.speed ?? 40));
        const fontSize = widgetFontSize(settings, 48);
        const background = widgetFill(settings);

        return (
            <div
                className="flex h-full items-center overflow-hidden px-4"
                style={{
                    color: widgetColor(settings),
                    background,
                }}
            >
                <p
                    className="animate-[ticker_linear_infinite] font-semibold whitespace-nowrap"
                    style={{
                        fontSize,
                        lineHeight: 1.1,
                        animationDuration: `${Math.max(8, 120 - speed)}s`,
                        textShadow: widgetTextShadow(background),
                    }}
                >
                    {text} · {text}
                </p>
                <style>{`@keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }`}</style>
            </div>
        );
    }

    if (key === 'json_api') {
        return (
            <Hero
                color={widgetColor(settings)}
                fontSize={widgetFontSize(settings, 48)}
                label={String(data.title ?? 'Data')}
                value={String(data.body ?? '—')}
            />
        );
    }

    if (key === 'charts') {
        const series = parseLines(settings.series).map((line) => {
            const [label, raw] = line.split(':');
            return { label: label ?? '', value: Number(raw ?? 0) };
        });
        const max = Math.max(1, ...series.map((row) => row.value));
        const fontSize = widgetFontSize(settings, 20);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col justify-end gap-3 p-8"
                style={{ color }}
            >
                <p className="font-semibold" style={{ fontSize }}>
                    {String(settings.title ?? 'Chart')}
                </p>
                {series.map((row) => (
                    <div key={row.label} className="space-y-1">
                        <div
                            className="flex justify-between"
                            style={{
                                fontSize: Math.max(
                                    10,
                                    Math.round(fontSize * 0.7),
                                ),
                            }}
                        >
                            <span>{row.label}</span>
                            <span>{row.value}</span>
                        </div>
                        <div className="h-3 rounded bg-white/10">
                            <div
                                className="h-3 rounded bg-sky-400"
                                style={{ width: `${(row.value / max) * 100}%` }}
                            />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (key === 'table') {
        const rows = parseLines(settings.rows).map((line) =>
            line.split('|').map((part) => part.trim()),
        );

        const fontSize = widgetFontSize(settings, 24);
        const color = widgetColor(settings);

        return (
            <div className="flex h-full flex-col gap-3 p-8" style={{ color }}>
                <p
                    className="tracking-[0.2em] uppercase opacity-70"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.5)),
                    }}
                >
                    {String(settings.heading ?? 'Table')}
                </p>
                <div className="space-y-2 overflow-auto">
                    {rows.map((cells, index) => (
                        <div
                            key={index}
                            className="grid gap-4 border-b border-white/10 pb-2"
                            style={{
                                fontSize,
                                gridTemplateColumns: `repeat(${Math.max(1, cells.length)}, minmax(0, 1fr))`,
                            }}
                        >
                            {cells.map((cell, cellIndex) => (
                                <span key={cellIndex}>{cell}</span>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (key === 'room_info') {
        const fontSize = widgetFontSize(settings, 48);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col justify-center gap-2 p-8"
                style={{ color }}
            >
                <p
                    className="tracking-[0.2em] uppercase"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.28)),
                    }}
                >
                    {String(settings.status ?? 'available')}
                </p>
                <p
                    className="font-semibold"
                    style={{ fontSize, lineHeight: 1.1 }}
                >
                    {String(settings.room_name ?? 'Room')}
                </p>
                {settings.next_meeting ? (
                    <p
                        className="opacity-80"
                        style={{
                            fontSize: Math.max(12, Math.round(fontSize * 0.4)),
                        }}
                    >
                        Next: {String(settings.next_meeting)}
                    </p>
                ) : null}
                {settings.capacity ? (
                    <p
                        className="opacity-60"
                        style={{
                            fontSize: Math.max(12, Math.round(fontSize * 0.32)),
                        }}
                    >
                        Seats {String(settings.capacity)}
                    </p>
                ) : null}
            </div>
        );
    }

    if (key === 'booking') {
        const slots = bookingSlots(settings, data, now, timezone);
        const heading = String(settings.heading ?? 'Bookings');
        const resource = String(settings.resource ?? '');
        const clock =
            String(data.clock ?? '') ||
            zoned.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });
        const fontSize = widgetFontSize(settings, 24);
        const color = widgetColor(settings);

        return (
            <div className="flex h-full flex-col gap-4 p-8" style={{ color }}>
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p
                            className="tracking-[0.2em] uppercase opacity-70"
                            style={{
                                fontSize: Math.max(
                                    10,
                                    Math.round(fontSize * 0.55),
                                ),
                            }}
                        >
                            {heading}
                        </p>
                        {resource ? (
                            <p className="font-semibold" style={{ fontSize }}>
                                {resource}
                            </p>
                        ) : null}
                    </div>
                    <p className="font-mono" style={{ fontSize }}>
                        {clock}
                    </p>
                </div>
                <div className="grid flex-1 gap-2 overflow-hidden">
                    {slots.length === 0 ? (
                        <p className="opacity-70">No bookings listed.</p>
                    ) : (
                        slots.map((slot, index) => {
                            const row =
                                slot && typeof slot === 'object'
                                    ? (slot as Record<string, unknown>)
                                    : {};
                            const active = Boolean(row.is_now);
                            const next = Boolean(row.is_next);
                            const status = String(row.status ?? 'booked');

                            return (
                                <div
                                    key={index}
                                    className={`rounded-xl px-4 py-3 ${
                                        active
                                            ? 'bg-emerald-500 text-black'
                                            : next
                                              ? 'bg-white/20'
                                              : 'bg-white/10'
                                    }`}
                                >
                                    <div
                                        className="flex items-center justify-between gap-3 tracking-wide uppercase opacity-80"
                                        style={{
                                            fontSize: Math.max(
                                                10,
                                                Math.round(fontSize * 0.55),
                                            ),
                                        }}
                                    >
                                        <span>{String(row.when ?? '')}</span>
                                        <span>{status}</span>
                                    </div>
                                    <p
                                        className="font-medium"
                                        style={{ fontSize }}
                                    >
                                        {String(row.title ?? '')}
                                    </p>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        );
    }

    if (key === 'menu_board') {
        const currency = String(settings.currency ?? '');
        const items = parseLines(settings.items).map((line) => {
            const [name, price, note] = line
                .split('|')
                .map((part) => part.trim());

            return { name, price, note };
        });
        const fontSize = widgetFontSize(settings, 32);
        const color = widgetColor(settings);
        const { ref, minEdge } = menuBox;

        return (
            <div
                ref={ref}
                className="flex h-full min-h-0 w-full flex-col px-[4.5%] py-[4%]"
                style={{
                    color,
                    containerType: 'size',
                }}
            >
                <p
                    className="shrink-0 tracking-[0.22em] uppercase opacity-70"
                    style={{
                        fontSize: menuFont(fontSize * 0.42, minEdge),
                    }}
                >
                    {String(settings.heading ?? 'Menu')}
                </p>
                <div className="flex min-h-0 flex-1 flex-col justify-evenly">
                    {items.map((item) => (
                        <div
                            key={item.name}
                            className="flex min-h-0 flex-1 items-center justify-between gap-[3%]"
                        >
                            <div className="min-w-0">
                                <p
                                    className="font-medium"
                                    style={{
                                        fontSize: menuFont(fontSize, minEdge),
                                    }}
                                >
                                    {item.name}
                                </p>
                                {item.note ? (
                                    <p
                                        className="opacity-60"
                                        style={{
                                            fontSize: menuFont(
                                                fontSize * 0.42,
                                                minEdge,
                                            ),
                                        }}
                                    >
                                        {item.note}
                                    </p>
                                ) : null}
                            </div>
                            <p
                                className="shrink-0 tabular-nums"
                                style={{
                                    fontSize: menuFont(
                                        fontSize * 0.78,
                                        minEdge,
                                    ),
                                }}
                            >
                                {currency} {item.price}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (key === 'social_wall') {
        const posts = parseLines(settings.posts).map((line) => {
            const [handle, ...rest] = line.split('|');

            return {
                handle: (handle ?? '').trim(),
                message: rest.join('|').trim() || (handle ?? '').trim(),
            };
        });
        const fontSize = widgetFontSize(settings, 24);
        const color = widgetColor(settings);

        return (
            <div className="flex h-full flex-col gap-4 p-8" style={{ color }}>
                <p
                    className="tracking-[0.2em] uppercase opacity-70"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.55)),
                    }}
                >
                    {String(settings.heading ?? 'Social')}
                </p>
                {posts.map((post) => (
                    <div
                        key={post.handle + post.message}
                        className="rounded-2xl bg-white/10 p-4"
                    >
                        <p
                            className="text-sky-300"
                            style={{
                                fontSize: Math.max(
                                    10,
                                    Math.round(fontSize * 0.55),
                                ),
                            }}
                        >
                            {post.handle}
                        </p>
                        <p style={{ fontSize }}>{post.message}</p>
                    </div>
                ))}
            </div>
        );
    }

    if (key === 'alert_banner') {
        const severity = String(settings.severity ?? 'info');
        const tone =
            severity === 'critical'
                ? 'bg-red-700'
                : severity === 'warning'
                  ? 'bg-amber-600'
                  : 'bg-sky-700';
        const fontSize = widgetFontSize(settings, 48);

        return (
            <div
                className={`flex h-full flex-col justify-center gap-3 p-10 text-white ${tone}`}
            >
                <p
                    className="tracking-[0.3em] uppercase"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.28)),
                    }}
                >
                    {severity}
                </p>
                <p
                    className="font-semibold"
                    style={{ fontSize, lineHeight: 1.1 }}
                >
                    {String(settings.heading ?? 'Notice')}
                </p>
                <p
                    className="text-white/90"
                    style={{
                        fontSize: Math.max(14, Math.round(fontSize * 0.45)),
                    }}
                >
                    {String(settings.message ?? '')}
                </p>
            </div>
        );
    }

    if (key === 'world_clock') {
        const cities = parseLines(settings.cities).map((line) => {
            const [name, zone] = line.split('|').map((part) => part.trim());
            let time = '—';

            try {
                time = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZone: zone || timezone,
                    hour12: false,
                });
            } catch {
                time = '—';
            }

            return { name: name || zone, time };
        });
        const fontSize = widgetFontSize(settings, 48);

        return (
            <div
                className="grid h-full grid-cols-1 gap-4 p-8 sm:grid-cols-3"
                style={{ color: widgetColor(settings) }}
            >
                {cities.map((city) => (
                    <div
                        key={city.name}
                        className="flex flex-col justify-center"
                    >
                        <p
                            className="tracking-widest uppercase opacity-70"
                            style={{
                                fontSize: Math.max(
                                    10,
                                    Math.round(fontSize * 0.28),
                                ),
                            }}
                        >
                            {city.name}
                        </p>
                        <p
                            className="font-mono"
                            style={{ fontSize, lineHeight: 1.1 }}
                        >
                            {city.time}
                        </p>
                    </div>
                ))}
            </div>
        );
    }

    if (key === 'room_status') {
        return (
            <RoomStatusSurface
                data={data}
                settings={settings}
                now={now}
                staticPreview={staticPreview}
            />
        );
    }

    if (key === 'room_board') {
        return (
            <RoomBoardSurface
                data={Array.isArray(data.rooms) ? data : sampleBoardData(now)}
                settings={settings}
                now={now}
            />
        );
    }

    if (key === 'analog_clock') {
        const face = String(settings.face ?? 'dark');
        const showSeconds = settings.show_seconds !== false;
        const accent = widgetColor(settings, '#38bdf8');
        const dial =
            face === 'light'
                ? '#f8fafc'
                : face === 'minimal'
                  ? 'transparent'
                  : '#0f172a';
        const ink = face === 'light' ? '#0f172a' : '#e2e8f0';
        const seconds = zoned.getSeconds();
        const minutes = zoned.getMinutes() + seconds / 60;
        const hours = (zoned.getHours() % 12) + minutes / 60;

        return (
            <div className="flex h-full w-full flex-col items-center justify-center gap-2 p-[6%]">
                <svg
                    viewBox="0 0 200 200"
                    className="h-full min-h-0 w-full"
                    role="img"
                    aria-label={`Analog clock showing ${zoned.toLocaleTimeString()}`}
                >
                    <circle
                        cx="100"
                        cy="100"
                        r="94"
                        fill={dial}
                        stroke={ink}
                        strokeOpacity="0.25"
                        strokeWidth="2"
                    />
                    {Array.from({ length: 12 }, (_, tick) => {
                        const angle = (tick * 30 * Math.PI) / 180;
                        const outer = 84;
                        const inner = tick % 3 === 0 ? 68 : 76;

                        return (
                            <line
                                key={tick}
                                x1={100 + Math.sin(angle) * inner}
                                y1={100 - Math.cos(angle) * inner}
                                x2={100 + Math.sin(angle) * outer}
                                y2={100 - Math.cos(angle) * outer}
                                stroke={ink}
                                strokeOpacity={tick % 3 === 0 ? 0.9 : 0.4}
                                strokeWidth={tick % 3 === 0 ? 4 : 2}
                                strokeLinecap="round"
                            />
                        );
                    })}
                    <line
                        x1="100"
                        y1="100"
                        x2={100 + Math.sin((hours * 30 * Math.PI) / 180) * 46}
                        y2={100 - Math.cos((hours * 30 * Math.PI) / 180) * 46}
                        stroke={ink}
                        strokeWidth="7"
                        strokeLinecap="round"
                    />
                    <line
                        x1="100"
                        y1="100"
                        x2={100 + Math.sin((minutes * 6 * Math.PI) / 180) * 68}
                        y2={100 - Math.cos((minutes * 6 * Math.PI) / 180) * 68}
                        stroke={ink}
                        strokeWidth="5"
                        strokeLinecap="round"
                    />
                    {showSeconds && (
                        <line
                            x1="100"
                            y1="100"
                            x2={
                                100 +
                                Math.sin((seconds * 6 * Math.PI) / 180) * 76
                            }
                            y2={
                                100 -
                                Math.cos((seconds * 6 * Math.PI) / 180) * 76
                            }
                            stroke={accent}
                            strokeWidth="2"
                            strokeLinecap="round"
                        />
                    )}
                    <circle cx="100" cy="100" r="6" fill={accent} />
                </svg>
                {settings.label ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{ color: ink, fontSize: '0.8em' }}
                    >
                        {String(settings.label)}
                    </p>
                ) : null}
            </div>
        );
    }

    if (key === 'weather_forecast') {
        const unit = settings.units === 'fahrenheit' ? '°F' : '°C';
        const days = Array.isArray(data.days) ? data.days : [];
        const fontSize = widgetFontSize(settings, 32);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-3 p-[5%]"
                style={{ color }}
            >
                <p
                    className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                    style={{
                        fontSize: Math.max(10, Math.round(fontSize * 0.5)),
                    }}
                >
                    {String(data.place ?? settings.location ?? 'Forecast')}
                </p>
                {days.length === 0 ? (
                    <p className="opacity-70">No forecast yet.</p>
                ) : (
                    <div
                        className="grid min-h-0 flex-1 gap-2"
                        style={{
                            gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))`,
                        }}
                    >
                        {days.map((entry, index) => {
                            const row =
                                entry && typeof entry === 'object'
                                    ? (entry as Record<string, unknown>)
                                    : {};
                            const date = new Date(String(row.date ?? ''));

                            return (
                                <div
                                    key={index}
                                    className="flex flex-col items-center justify-center gap-1 rounded-xl bg-white/10 px-2 py-3"
                                >
                                    <p
                                        className="tracking-wide uppercase opacity-70"
                                        style={{
                                            fontSize: Math.max(
                                                10,
                                                Math.round(fontSize * 0.45),
                                            ),
                                        }}
                                    >
                                        {Number.isNaN(date.getTime())
                                            ? '—'
                                            : date.toLocaleDateString([], {
                                                  weekday: 'short',
                                              })}
                                    </p>
                                    <p style={{ fontSize: fontSize * 1.1 }}>
                                        {weatherGlyph(Number(row.code))}
                                    </p>
                                    <p
                                        className="font-semibold tabular-nums"
                                        style={{ fontSize }}
                                    >
                                        {row.high === null ||
                                        row.high === undefined
                                            ? '—'
                                            : `${Math.round(Number(row.high))}${unit}`}
                                    </p>
                                    <p
                                        className="tabular-nums opacity-60"
                                        style={{
                                            fontSize: Math.max(
                                                10,
                                                Math.round(fontSize * 0.6),
                                            ),
                                        }}
                                    >
                                        {row.low === null ||
                                        row.low === undefined
                                            ? '—'
                                            : `${Math.round(Number(row.low))}${unit}`}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        );
    }

    if (key === 'metric_tiles') {
        const metrics = parseCells(settings.metrics, 3);
        const columns = Math.max(1, Math.min(4, Number(settings.columns ?? 2)));
        const fontSize = widgetFontSize(settings, 48);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-3 p-[5%]"
                style={{ color }}
            >
                {settings.heading ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.35)),
                        }}
                    >
                        {String(settings.heading)}
                    </p>
                ) : null}
                <div
                    className="grid min-h-0 flex-1 gap-3"
                    style={{
                        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                    }}
                >
                    {metrics.map(([label, value, change], index) => (
                        <div
                            key={`${label}-${index}`}
                            className="flex flex-col justify-center gap-1 rounded-2xl bg-white/10 px-[6%] py-[4%]"
                        >
                            <p
                                className="truncate tracking-wide uppercase opacity-65"
                                style={{
                                    fontSize: Math.max(
                                        10,
                                        Math.round(fontSize * 0.3),
                                    ),
                                }}
                            >
                                {label}
                            </p>
                            <p
                                className="font-semibold tabular-nums"
                                style={{ fontSize, lineHeight: 1.05 }}
                            >
                                {value}
                            </p>
                            {change ? (
                                <p
                                    className={
                                        change.trim().startsWith('-')
                                            ? 'text-rose-300'
                                            : 'text-emerald-300'
                                    }
                                    style={{
                                        fontSize: Math.max(
                                            10,
                                            Math.round(fontSize * 0.34),
                                        ),
                                    }}
                                >
                                    {change}
                                </p>
                            ) : null}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (key === 'progress_goal') {
        const suffix = String(settings.suffix ?? '');
        const goals = parseCells(settings.goals, 3);
        const fontSize = widgetFontSize(settings, 28);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-4 p-[5%]"
                style={{ color }}
            >
                {settings.heading ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.6)),
                        }}
                    >
                        {String(settings.heading)}
                    </p>
                ) : null}
                <div className="flex min-h-0 flex-1 flex-col justify-evenly gap-3">
                    {goals.map(([label, value, target], index) => {
                        const current = Number(value.replace(/[^0-9.-]/g, ''));
                        const goal = Number(target.replace(/[^0-9.-]/g, ''));
                        const percent =
                            Number.isFinite(current) &&
                            Number.isFinite(goal) &&
                            goal > 0
                                ? Math.max(
                                      0,
                                      Math.min(100, (current / goal) * 100),
                                  )
                                : 0;

                        return (
                            <div
                                key={`${label}-${index}`}
                                className="space-y-1"
                            >
                                <div
                                    className="flex items-end justify-between gap-3"
                                    style={{ fontSize }}
                                >
                                    <span className="truncate">{label}</span>
                                    <span className="shrink-0 tabular-nums">
                                        {value}
                                        {suffix} / {target}
                                        {suffix}
                                    </span>
                                </div>
                                <div className="h-[0.6em] overflow-hidden rounded-full bg-white/15">
                                    <div
                                        className="h-full rounded-full bg-sky-400"
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    }

    if (key === 'gauge') {
        const min = Number(settings.min ?? 0);
        const max = Number(settings.max ?? 100);
        const value = Number(settings.value ?? 0);
        const span = max - min;
        const ratio =
            Number.isFinite(span) && span !== 0
                ? Math.max(0, Math.min(1, (value - min) / span))
                : 0;
        const accent = widgetColor(settings, '#22d3ee');
        const fontSize = widgetFontSize(settings, 64);
        // 240-degree sweep starting bottom-left, drawn as a dashed arc.
        const radius = 78;
        const sweep = (240 / 360) * 2 * Math.PI * radius;
        const circumference = 2 * Math.PI * radius;

        return (
            <div className="relative flex h-full w-full items-center justify-center p-[6%]">
                <svg
                    viewBox="0 0 200 200"
                    className="h-full max-h-full w-full"
                    role="img"
                    aria-label={`${String(settings.label ?? 'Gauge')} ${value}`}
                >
                    <g transform="rotate(150 100 100)">
                        <circle
                            cx="100"
                            cy="100"
                            r={radius}
                            fill="none"
                            stroke="#ffffff"
                            strokeOpacity="0.15"
                            strokeWidth="18"
                            strokeLinecap="round"
                            strokeDasharray={`${sweep} ${circumference}`}
                        />
                        <circle
                            cx="100"
                            cy="100"
                            r={radius}
                            fill="none"
                            stroke={accent}
                            strokeWidth="18"
                            strokeLinecap="round"
                            strokeDasharray={`${sweep * ratio} ${circumference}`}
                        />
                    </g>
                </svg>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1 text-center">
                    <p
                        className="font-semibold tabular-nums"
                        style={{ color: accent, fontSize, lineHeight: 1 }}
                    >
                        {Number.isFinite(value) ? value : '—'}
                        {String(settings.suffix ?? '')}
                    </p>
                    <p
                        className="tracking-[0.2em] text-white/70 uppercase"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.24)),
                        }}
                    >
                        {String(settings.label ?? '')}
                    </p>
                </div>
            </div>
        );
    }

    if (key === 'quote') {
        const [text, attribution] = (quoteLines[rotationIndex] ?? '')
            .split('|')
            .map((part) => part.trim());
        const fontSize = widgetFontSize(settings, 44);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col items-center justify-center gap-4 p-[7%] text-center"
                style={{ color }}
            >
                <p
                    className="font-light italic"
                    style={{ fontSize, lineHeight: 1.25 }}
                >
                    {text ? `“${text}”` : 'Add a quote'}
                </p>
                {attribution ? (
                    <p
                        className="tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.4)),
                        }}
                    >
                        {attribution}
                    </p>
                ) : null}
            </div>
        );
    }

    if (key === 'safety_counter') {
        const days = Number(data.days ?? 0);
        const record = Number(settings.record ?? 0);
        const fontSize = widgetFontSize(settings, 140);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col items-center justify-center gap-2 p-[5%] text-center"
                style={{ color }}
            >
                <p
                    className="font-bold tabular-nums"
                    style={{ fontSize, lineHeight: 1 }}
                >
                    {Number.isFinite(days) ? days : 0}
                </p>
                <p
                    className="tracking-[0.2em] uppercase opacity-80"
                    style={{
                        fontSize: Math.max(12, Math.round(fontSize * 0.16)),
                    }}
                >
                    {String(settings.label ?? 'Days without an incident')}
                </p>
                {record > 0 ? (
                    <p
                        className="opacity-60"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.12)),
                        }}
                    >
                        Best record {record}
                    </p>
                ) : null}
            </div>
        );
    }

    if (key === 'image_gallery') {
        const source = galleryLines[rotationIndex] ?? '';
        const fit = settings.objectFit === 'contain' ? 'contain' : 'cover';

        if (!source) {
            return <Fallback title="Image gallery" body="Add image URLs" />;
        }

        return (
            <div className="relative h-full w-full overflow-hidden bg-black">
                <img
                    key={source}
                    src={source}
                    alt=""
                    className="h-full w-full"
                    style={{ objectFit: fit }}
                />
                {settings.caption === true ? (
                    <p className="absolute inset-x-0 bottom-0 truncate bg-black/50 px-3 py-2 text-sm text-white">
                        {source.split('/').pop()}
                    </p>
                ) : null}
            </div>
        );
    }

    if (key === 'directory') {
        const entries = parseCells(settings.entries, 3);
        const fontSize = widgetFontSize(settings, 30);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-2 p-[5%]"
                style={{ color }}
            >
                {settings.heading ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.55)),
                        }}
                    >
                        {String(settings.heading)}
                    </p>
                ) : null}
                <div className="flex min-h-0 flex-1 flex-col justify-evenly">
                    {entries.map(([name, location, direction], index) => (
                        <div
                            key={`${name}-${index}`}
                            className="flex items-center justify-between gap-4 border-b border-white/10 py-[1.5%]"
                            style={{ fontSize }}
                        >
                            <span className="min-w-0 flex-1 truncate font-medium">
                                {name}
                            </span>
                            <span className="shrink-0 opacity-70">
                                {location}
                            </span>
                            <span
                                className="shrink-0"
                                aria-label={direction}
                                style={{ fontSize: fontSize * 1.2 }}
                            >
                                {directionArrow(direction)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (key === 'celebrations') {
        const people = parseCells(settings.people, 3);
        const fontSize = widgetFontSize(settings, 30);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-3 p-[5%]"
                style={{ color }}
            >
                {settings.heading ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.55)),
                        }}
                    >
                        {String(settings.heading)}
                    </p>
                ) : null}
                <div className="flex min-h-0 flex-1 flex-col justify-evenly gap-2">
                    {people.map(([name, occasion, when], index) => (
                        <div
                            key={`${name}-${index}`}
                            className="flex items-center gap-3 rounded-2xl bg-white/10 px-[4%] py-[2.5%]"
                        >
                            <span
                                className="shrink-0"
                                aria-hidden
                                style={{ fontSize: fontSize * 1.1 }}
                            >
                                {occasionGlyph(occasion)}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p
                                    className="truncate font-medium"
                                    style={{ fontSize }}
                                >
                                    {name}
                                </p>
                                <p
                                    className="truncate opacity-65"
                                    style={{
                                        fontSize: Math.max(
                                            10,
                                            Math.round(fontSize * 0.55),
                                        ),
                                    }}
                                >
                                    {occasion}
                                </p>
                            </div>
                            <span
                                className="shrink-0 opacity-70"
                                style={{
                                    fontSize: Math.max(
                                        10,
                                        Math.round(fontSize * 0.6),
                                    ),
                                }}
                            >
                                {when}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (key === 'event_schedule') {
        const sessions = Array.isArray(data.sessions) ? data.sessions : [];
        const fontSize = widgetFontSize(settings, 28);
        const color = widgetColor(settings);

        return (
            <div
                className="flex h-full flex-col gap-3 p-[5%]"
                style={{ color }}
            >
                {settings.heading ? (
                    <p
                        className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.55)),
                        }}
                    >
                        {String(settings.heading)}
                    </p>
                ) : null}
                <div className="flex min-h-0 flex-1 flex-col justify-evenly gap-2">
                    {sessions.length === 0 ? (
                        <p className="opacity-70">No sessions listed.</p>
                    ) : (
                        sessions.map((session, index) => {
                            const row =
                                session && typeof session === 'object'
                                    ? (session as Record<string, unknown>)
                                    : {};
                            const state = String(row.state ?? 'scheduled');

                            return (
                                <div
                                    key={index}
                                    className={`flex items-center gap-4 rounded-xl px-[3%] py-[1.8%] ${
                                        state === 'live'
                                            ? 'bg-emerald-500 text-black'
                                            : state === 'done'
                                              ? 'bg-white/5 opacity-55'
                                              : 'bg-white/10'
                                    }`}
                                    style={{ fontSize }}
                                >
                                    <span className="shrink-0 font-mono tabular-nums">
                                        {String(row.when ?? '')}
                                    </span>
                                    <span className="min-w-0 flex-1 truncate font-medium">
                                        {String(row.title ?? '')}
                                    </span>
                                    <span
                                        className="shrink-0 opacity-75"
                                        style={{
                                            fontSize: Math.max(
                                                10,
                                                Math.round(fontSize * 0.65),
                                            ),
                                        }}
                                    >
                                        {state === 'live'
                                            ? 'Live now'
                                            : String(row.room ?? '')}
                                    </span>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        );
    }

    return <Fallback title={widgetTitle(key)} body="Widget is configured." />;
}

/**
 * WMO weather code to a single glyph, matching Open-Meteo's code ranges.
 */
function weatherGlyph(code: number): string {
    if (!Number.isFinite(code)) {
        return '—';
    }

    if (code === 0) {
        return '☀';
    }

    if (code <= 2) {
        return '⛅';
    }

    if (code === 3) {
        return '☁';
    }

    if (code <= 48) {
        return '🌫';
    }

    if (code <= 67) {
        return '🌧';
    }

    if (code <= 77) {
        return '❄';
    }

    if (code <= 82) {
        return '🌦';
    }

    if (code <= 86) {
        return '🌨';
    }

    return '⛈';
}

function directionArrow(direction: string): string {
    switch (direction.trim().toLowerCase()) {
        case 'left':
            return '←';
        case 'right':
            return '→';
        case 'up':
        case 'straight':
        case 'ahead':
            return '↑';
        case 'down':
        case 'back':
            return '↓';
        case 'up-left':
            return '↖';
        case 'up-right':
            return '↗';
        case 'down-left':
            return '↙';
        case 'down-right':
            return '↘';
        default:
            return '•';
    }
}

function occasionGlyph(occasion: string): string {
    const value = occasion.toLowerCase();

    if (value.includes('birthday')) {
        return '🎂';
    }

    if (value.includes('anniversary')) {
        return '🎉';
    }

    if (value.includes('welcome') || value.includes('joining')) {
        return '👋';
    }

    if (value.includes('retire')) {
        return '🏅';
    }

    return '⭐';
}

function Hero({
    label,
    value,
    color = '#ffffff',
    fontSize = 60,
}: {
    label: string;
    value: string;
    color?: string;
    fontSize?: number;
}) {
    return (
        <div
            className="flex h-full flex-col items-center justify-center gap-2 p-8 text-center"
            style={{ color }}
        >
            <p
                className="tracking-[0.3em] uppercase opacity-70"
                style={{ fontSize: Math.max(10, Math.round(fontSize * 0.22)) }}
            >
                {label}
            </p>
            <p className="font-semibold" style={{ fontSize, lineHeight: 1.1 }}>
                {value}
            </p>
        </div>
    );
}

function queueRows(
    value: WidgetJsonValue | undefined,
): Record<string, WidgetJsonValue>[] {
    return Array.isArray(value)
        ? value.filter(
              (row): row is Record<string, WidgetJsonValue> =>
                  typeof row === 'object' &&
                  row !== null &&
                  !Array.isArray(row),
          )
        : [];
}

function QueueWidgetSurface({
    widgetKey,
    settings,
    data,
}: {
    widgetKey: string;
    settings: Record<string, WidgetJsonValue>;
    data: Record<string, WidgetJsonValue>;
}) {
    const fontSize = widgetFontSize(settings, 36);
    const background = widgetFill(settings);
    const color = widgetColor(settings);
    const align = ['left', 'right'].includes(String(settings.align))
        ? (String(settings.align) as 'left' | 'right')
        : 'center';
    const animation = String(settings.animation ?? 'none');
    const nowServing = queueRows(data.now_serving);
    const recentlyCalled = queueRows(data.recently_called);
    const waiting = queueRows(data.waiting);
    const next =
        data.next_waiting &&
        typeof data.next_waiting === 'object' &&
        !Array.isArray(data.next_waiting)
            ? data.next_waiting
            : {};
    const stats =
        data.stats &&
        typeof data.stats === 'object' &&
        !Array.isArray(data.stats)
            ? data.stats
            : {};
    const heading = String(settings.heading ?? 'Queue');
    const rowSize = Math.max(12, Math.round(fontSize * 0.72));
    const smallSize = Math.max(10, Math.round(fontSize * 0.38));
    const animationClass =
        animation === 'pulse'
            ? 'animate-pulse'
            : animation === 'slide'
              ? 'animate-[queue-slide_600ms_ease-out]'
              : '';
    const highlightedTicketId = Number(data.highlight_ticket_id ?? 0);
    const rows =
        widgetKey === 'queue_recently_called'
            ? recentlyCalled
            : widgetKey === 'queue_waiting_tickets'
              ? waiting
              : nowServing;

    const ticketRows = (
        <div className="min-h-0 flex-1 space-y-2 overflow-hidden">
            {rows.length === 0 ? (
                <div className="opacity-60" style={{ fontSize: rowSize }}>
                    <p>
                        {widgetKey === 'queue_waiting_tickets'
                            ? 'No one waiting'
                            : waiting.length > 0
                              ? 'Waiting to be called'
                              : 'No one serving'}
                    </p>
                    {widgetKey !== 'queue_waiting_tickets' &&
                        waiting[0]?.number && (
                            <p className="font-semibold tabular-nums">
                                {String(waiting[0].number)}
                            </p>
                        )}
                </div>
            ) : (
                rows.map((row, index) => (
                    <div
                        key={String(row.id ?? `${row.number}-${index}`)}
                        className={`grid grid-cols-[1fr_auto] items-center gap-4 rounded-xl bg-white/10 px-4 py-2 ${widgetKey !== 'queue_waiting_tickets' && Number(row.id) === highlightedTicketId ? 'queue-call-blink' : ''}`}
                        style={{ fontSize: rowSize }}
                    >
                        <div>
                            <p className="font-bold tabular-nums">
                                {String(row.number ?? '—')}
                            </p>
                            <p
                                className="opacity-65"
                                style={{ fontSize: smallSize }}
                            >
                                {String(row.service ?? '')}
                            </p>
                        </div>
                        <p className="font-semibold">
                            {String(
                                row.counter ??
                                    (row.position ? `#${row.position}` : ''),
                            )}
                        </p>
                    </div>
                ))
            )}
        </div>
    );

    let content: ReactNode = ticketRows;

    if (widgetKey === 'queue_position') {
        content = (
            <QueueHero
                value={String(next.number ?? '—')}
                detail={
                    next.position
                        ? `Position ${next.position}`
                        : 'No one waiting'
                }
                fontSize={fontSize}
            />
        );
    } else if (widgetKey === 'queue_counter_number') {
        content = (
            <QueueHero
                value={String(
                    nowServing[0]?.counter ?? data.counter_name ?? '—',
                )}
                detail={String(nowServing[0]?.number ?? '')}
                fontSize={fontSize}
                highlight={
                    nowServing[0]
                        ? Number(nowServing[0].id) === highlightedTicketId
                        : false
                }
            />
        );
    } else if (widgetKey === 'queue_service_name') {
        content = (
            <QueueHero
                value={String(data.service_name ?? 'All services')}
                fontSize={fontSize}
            />
        );
    } else if (widgetKey === 'queue_estimated_wait') {
        content = (
            <QueueHero
                value={`${String(data.estimated_wait_minutes ?? 0)} min`}
                detail="Approximate"
                fontSize={fontSize}
            />
        );
    } else if (widgetKey === 'queue_status') {
        content = (
            <QueueHero
                value={String(data.status_label ?? 'Closed')}
                fontSize={fontSize}
            />
        );
    } else if (widgetKey === 'queue_statistics') {
        content = <QueueStats stats={stats} fontSize={fontSize} />;
    } else if (widgetKey === 'queue_ticker') {
        const ticker = String(data.ticker ?? 'No calls yet');
        content = (
            <div className="flex flex-1 items-center overflow-hidden">
                <p
                    className="animate-[ticker_linear_infinite] font-bold whitespace-nowrap"
                    style={{ fontSize, animationDuration: '24s' }}
                >
                    {ticker} &nbsp;&nbsp; • &nbsp;&nbsp; {ticker}
                </p>
            </div>
        );
    } else if (widgetKey === 'queue_join_qr') {
        const url = String(data.join_url ?? '');
        content = url ? (
            <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-3">
                <img
                    alt="Join this queue"
                    className="max-h-[75%] min-h-0 max-w-[75%] bg-white object-contain p-2"
                    src={`https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=8&data=${encodeURIComponent(url)}`}
                />
                <p
                    className="max-w-full truncate opacity-70"
                    style={{ fontSize: smallSize }}
                >
                    {url}
                </p>
            </div>
        ) : (
            <p>No join link available</p>
        );
    } else if (widgetKey === 'queue_board') {
        content = (
            <div className="grid min-h-0 flex-1 grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4">
                <div className="flex min-h-0 flex-col gap-4">
                    <div className="flex min-h-0 flex-1 flex-col gap-2">
                        <p
                            className="text-left font-semibold uppercase opacity-70"
                            style={{ fontSize: smallSize }}
                        >
                            Now serving
                        </p>
                        {ticketRows}
                    </div>
                    <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-auto">
                        <p
                            className="text-left font-semibold uppercase opacity-70"
                            style={{ fontSize: smallSize }}
                        >
                            Waiting
                        </p>
                        {waiting.length === 0 ? (
                            <p
                                className="opacity-60"
                                style={{ fontSize: smallSize }}
                            >
                                No one waiting
                            </p>
                        ) : (
                            waiting.map((row, index) => (
                                <div
                                    key={String(
                                        row.id ?? `${row.number}-${index}`,
                                    )}
                                    className="flex items-center justify-between rounded-xl bg-white/10 px-4 py-2"
                                    style={{ fontSize: smallSize }}
                                >
                                    <span className="font-bold tabular-nums">
                                        {String(row.number ?? '—')}
                                    </span>
                                    <span>
                                        #{String(row.position ?? index + 1)}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
                <QueueStats stats={stats} fontSize={fontSize} />
            </div>
        );
    }

    return (
        <div
            className={`flex h-full min-h-0 flex-col gap-4 overflow-hidden p-[5%] ${animationClass}`}
            style={{
                color,
                background,
                textAlign: align,
                fontFamily: String(settings.font_family ?? 'Arial'),
                borderColor: String(settings.border_color ?? 'transparent'),
                borderWidth: Math.max(0, Number(settings.border_width ?? 0)),
                borderStyle: 'solid',
                textShadow: widgetTextShadow(background),
            }}
        >
            <p
                className="shrink-0 tracking-[0.18em] uppercase opacity-70"
                style={{ fontSize: smallSize }}
            >
                {heading}
            </p>
            {content}
            <style>{`@keyframes queue-slide { from { opacity: 0; transform: translateY(12%); } to { opacity: 1; transform: translateY(0); } } @keyframes ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }`}</style>
        </div>
    );
}

function QueueHero({
    value,
    detail,
    fontSize,
    highlight = false,
}: {
    value: string;
    detail?: string;
    fontSize: number;
    highlight?: boolean;
}) {
    return (
        <div
            className={`flex flex-1 flex-col items-center justify-center gap-2 rounded-xl ${highlight ? 'queue-call-blink' : ''}`}
        >
            <p
                className="font-bold tabular-nums"
                style={{ fontSize, lineHeight: 1.05 }}
            >
                {value}
            </p>
            {detail ? (
                <p
                    className="opacity-70"
                    style={{
                        fontSize: Math.max(11, Math.round(fontSize * 0.38)),
                    }}
                >
                    {detail}
                </p>
            ) : null}
        </div>
    );
}

function QueueStats({
    stats,
    fontSize,
}: {
    stats: Record<string, WidgetJsonValue>;
    fontSize: number;
}) {
    const items = [
        ['Waiting', stats.waiting],
        ['Serving', stats.serving],
        ['Completed', stats.completed_today],
        ['No show', stats.no_show_today],
        ['Open counters', stats.open_counters],
        ['Closed counters', stats.closed_counters],
    ];

    return (
        <div className="grid min-h-0 flex-1 grid-cols-2 content-center gap-2">
            {items.map(([label, value]) => (
                <div key={String(label)} className="rounded-xl bg-white/10 p-3">
                    <p className="font-bold tabular-nums" style={{ fontSize }}>
                        {String(value ?? 0)}
                    </p>
                    <p
                        className="opacity-65"
                        style={{
                            fontSize: Math.max(10, Math.round(fontSize * 0.4)),
                        }}
                    >
                        {String(label)}
                    </p>
                </div>
            ))}
        </div>
    );
}

/**
 * Browser-chrome poster standing in for a live iframe in gallery previews.
 */
function WebPagePoster({ url }: { url: string }) {
    let host = url;

    try {
        host = new URL(url).host;
    } catch {
        // Keep the raw value when it is not a full URL.
    }

    return (
        <div className="flex h-full w-full flex-col overflow-hidden bg-white text-slate-700">
            <div className="flex h-[9%] min-h-6 shrink-0 items-center gap-[1.2%] bg-slate-200 px-[2%]">
                <span className="size-[1.4em] rounded-full bg-rose-400" />
                <span className="size-[1.4em] rounded-full bg-amber-400" />
                <span className="size-[1.4em] rounded-full bg-emerald-400" />
                <span className="ml-[2%] flex-1 truncate rounded bg-white px-[2%] text-[2.2em] text-slate-500">
                    {host}
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-[3%] p-[6%]">
                <div className="h-[9%] w-2/3 rounded bg-slate-300" />
                <div className="h-[4%] w-full rounded bg-slate-200" />
                <div className="h-[4%] w-11/12 rounded bg-slate-200" />
                <div className="h-[4%] w-4/5 rounded bg-slate-200" />
                <div className="mt-auto h-[28%] w-full rounded bg-slate-100" />
            </div>
        </div>
    );
}

/**
 * Placeholder schedule shown until a room is chosen: a meeting in progress
 * and two more later, so every part of the sign is visible while designing.
 */
function sampleRoomData(now: Date): Record<string, WidgetJsonValue> {
    const minute = 60_000;
    const at = (offset: number) => new Date(now.getTime() + offset * minute);
    const label = (date: Date) =>
        `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    const slot = (id: number, title: string, from: number, to: number) => ({
        id,
        title,
        organizer: 'Sample organizer',
        starts_at: at(from).toISOString(),
        ends_at: at(to).toISOString(),
        start_label: label(at(from)),
        end_label: label(at(to)),
    });

    return {
        room: { id: 0, name: 'Meeting room', capacity: 8, location: null },
        bookings: [
            slot(1, 'Weekly planning', -15, 45),
            slot(2, 'Client workshop', 90, 150),
            slot(3, 'Design review', 210, 240),
        ],
        booking_url: null,
    };
}

/**
 * Sample rooms for a board that has not been resolved against real rooms
 * yet, such as a catalog template in the gallery. A resolved board always
 * carries a `rooms` array, even when it is empty.
 */
function sampleBoardData(now: Date): Record<string, WidgetJsonValue> {
    const sign = sampleRoomData(now);
    const minute = 60_000;
    const shifted = (bookings: WidgetJsonValue, offset: number) =>
        (Array.isArray(bookings) ? bookings : []).map((booking) => {
            const row = booking as Record<string, string | number>;
            const start = new Date(
                Date.parse(String(row.starts_at)) + offset * minute,
            );
            const end = new Date(
                Date.parse(String(row.ends_at)) + offset * minute,
            );
            const label = (date: Date) =>
                `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;

            return {
                ...row,
                starts_at: start.toISOString(),
                ends_at: end.toISOString(),
                start_label: label(start),
                end_label: label(end),
            };
        });
    const room = (
        id: number,
        name: string,
        color: string,
        bookings: WidgetJsonValue,
    ) => ({
        room: { id, name, color },
        bookings,
    });

    return {
        rooms: [
            room(1, 'Boardroom', '#2563eb', sign.bookings),
            room(2, 'Focus room', '#16a34a', shifted(sign.bookings, 90)),
            room(3, 'Training studio', '#9333ea', shifted(sign.bookings, 5)),
            room(4, 'Huddle space', '#f59e0b', []),
            room(5, 'Innovation lab', '#0ea5e9', shifted(sign.bookings, 200)),
        ],
    };
}

function widgetTitle(key: string): string {
    const text = key.replaceAll('_', ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

const ROOM_STATE_STYLE = {
    free: { label: 'Available', background: '#15803d', accent: '#86efac' },
    soon: { label: 'Starting soon', background: '#b45309', accent: '#fde68a' },
    busy: { label: 'In use', background: '#b91c1c', accent: '#fecaca' },
} as const;

function screenBookings(value: WidgetJsonValue | undefined): ScreenBooking[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.flatMap((item) => {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return [];
        }

        const row = item as Record<string, WidgetJsonValue>;

        if (
            typeof row.starts_at !== 'string' ||
            typeof row.ends_at !== 'string'
        ) {
            return [];
        }

        return [
            {
                id: Number(row.id ?? 0),
                title: typeof row.title === 'string' ? row.title : 'Meeting',
                organizer:
                    typeof row.organizer === 'string' ? row.organizer : null,
                starts_at: row.starts_at,
                ends_at: row.ends_at,
                start_label:
                    typeof row.start_label === 'string' ? row.start_label : '',
                end_label:
                    typeof row.end_label === 'string' ? row.end_label : '',
            },
        ];
    });
}

function roomRecord(
    value: WidgetJsonValue | undefined,
): Record<string, WidgetJsonValue> {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, WidgetJsonValue>)
        : {};
}

/**
 * Meeting room door sign. The manifest carries today's bookings; the state
 * flips between free, starting soon and in use on the player's own clock.
 */
function RoomStatusSurface({
    data,
    settings,
    now,
    staticPreview,
    notice,
}: {
    data: Record<string, WidgetJsonValue>;
    settings: Record<string, WidgetJsonValue>;
    now: Date;
    staticPreview: boolean;
    /** Shown over a sample sign while the widget is not configured. */
    notice?: string;
}) {
    const room = roomRecord(data.room);
    const bookings = screenBookings(data.bookings);
    const soonMinutes = Math.max(0, Number(settings.soon_minutes ?? 10));
    const state = roomState(bookings, now, soonMinutes);
    const style = ROOM_STATE_STYLE[state.kind];
    const upcomingLimit = Math.max(
        0,
        Math.min(6, Number(settings.upcoming ?? 3)),
    );
    const upcoming = upcomingBookings(
        bookings,
        now,
        upcomingLimit + (state.kind === 'soon' ? 1 : 0),
    )
        .filter((item) => state.kind !== 'soon' || item.id !== state.next.id)
        .slice(0, upcomingLimit);
    const fontSize = widgetFontSize(settings, 48);
    const bookingUrl =
        typeof data.booking_url === 'string' ? data.booking_url : '';
    const showQr = settings.show_qr !== false && bookingUrl !== '';
    const small = Math.max(12, Math.round(fontSize * 0.36));

    let headline = 'Free for the rest of the day';
    let detail = '';

    if (state.kind === 'busy') {
        headline = state.current.title;
        detail = [
            state.current.organizer,
            `${state.current.start_label}–${state.current.end_label}`,
            `${formatMinutes(state.minutesLeft)} left`,
        ]
            .filter(Boolean)
            .join('  ·  ');
    } else if (state.kind === 'soon') {
        headline = state.next.title;
        detail = `Starts at ${state.next.start_label}  ·  in ${formatMinutes(state.minutesUntil)}`;
    } else if (state.next && state.minutesUntil !== null) {
        headline = `Free until ${state.next.start_label}`;
        detail = `${formatMinutes(state.minutesUntil)} available`;
    }

    return (
        <div
            className="relative flex h-full w-full overflow-hidden text-white"
            style={{ background: '#0f172a', fontFamily: 'Arial' }}
        >
            {notice ? (
                <div
                    role="status"
                    className="absolute inset-x-0 top-0 z-10 bg-amber-400 px-[3%] py-[1.5%] text-center font-semibold text-amber-950"
                    style={{
                        fontSize: Math.max(
                            12,
                            Math.round(widgetFontSize(settings, 48) * 0.36),
                        ),
                    }}
                >
                    Sample preview · {notice}
                </div>
            ) : null}
            <div
                className="flex min-w-0 flex-[3] flex-col justify-between p-[4%] transition-colors duration-700"
                style={{ background: style.background }}
            >
                <div className="min-w-0">
                    <p
                        className="truncate font-semibold"
                        style={{ fontSize: fontSize * 0.9, lineHeight: 1.1 }}
                    >
                        {String(room.name ?? 'Meeting room')}
                    </p>
                    <p className="opacity-80" style={{ fontSize: small }}>
                        {[
                            room.location ? String(room.location) : null,
                            room.capacity
                                ? `${String(room.capacity)} seats`
                                : null,
                        ]
                            .filter(Boolean)
                            .join('  ·  ')}
                    </p>
                </div>
                <div className="min-w-0 space-y-[2%]">
                    <p
                        className="font-bold tracking-[0.12em] uppercase"
                        style={{
                            color: style.accent,
                            fontSize: fontSize * 0.55,
                        }}
                    >
                        {style.label}
                    </p>
                    <p
                        className="line-clamp-2 font-bold"
                        style={{ fontSize: fontSize * 1.25, lineHeight: 1.05 }}
                    >
                        {headline}
                    </p>
                    {detail ? (
                        <p
                            className="opacity-90"
                            style={{ fontSize: small * 1.15 }}
                        >
                            {detail}
                        </p>
                    ) : null}
                    {state.kind === 'busy' ? (
                        <div
                            className="mt-[2%] h-[0.35em] overflow-hidden rounded-full bg-black/25"
                            style={{ fontSize }}
                        >
                            <div
                                className="h-full rounded-full bg-white/85"
                                style={{
                                    width: `${Math.round(state.progress * 100)}%`,
                                }}
                            />
                        </div>
                    ) : null}
                </div>
            </div>
            <div className="flex min-w-0 flex-[2] flex-col gap-[4%] p-[3.5%]">
                <p
                    className="tracking-[0.2em] text-white/60 uppercase"
                    style={{ fontSize: small }}
                >
                    Up next
                </p>
                <div className="flex min-h-0 flex-1 flex-col gap-[3%] overflow-hidden">
                    {upcoming.length === 0 ? (
                        <p
                            className="text-white/60"
                            style={{ fontSize: small * 1.1 }}
                        >
                            Nothing else booked today.
                        </p>
                    ) : (
                        upcoming.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-xl bg-white/10 px-[5%] py-[3%]"
                            >
                                <p
                                    className="font-mono text-white/70"
                                    style={{ fontSize: small }}
                                >
                                    {item.start_label}–{item.end_label}
                                </p>
                                <p
                                    className="truncate font-medium"
                                    style={{ fontSize: small * 1.25 }}
                                >
                                    {item.title}
                                </p>
                            </div>
                        ))
                    )}
                </div>
                {showQr ? (
                    <div className="flex shrink-0 items-center gap-[5%] rounded-xl bg-white p-[4%] text-slate-900">
                        {staticPreview ? (
                            <div className="aspect-square w-[34%] shrink-0 bg-slate-200" />
                        ) : (
                            <img
                                alt="Scan to book this room"
                                className="aspect-square w-[34%] shrink-0"
                                src={`https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=0&data=${encodeURIComponent(bookingUrl)}`}
                            />
                        )}
                        <p
                            className="font-semibold"
                            style={{ fontSize: small * 1.1, lineHeight: 1.15 }}
                        >
                            Scan to book this room
                        </p>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

/**
 * Lobby board listing every room with a live free / busy pill.
 */
function RoomBoardSurface({
    data,
    settings,
    now,
}: {
    data: Record<string, WidgetJsonValue>;
    settings: Record<string, WidgetJsonValue>;
    now: Date;
}) {
    const rooms = Array.isArray(data.rooms) ? data.rooms.map(roomRecord) : [];
    const fontSize = widgetFontSize(settings, 30);
    const color = widgetColor(settings);
    const small = Math.max(10, Math.round(fontSize * 0.6));

    return (
        <div className="flex h-full flex-col gap-[3%] p-[4%]" style={{ color }}>
            {settings.heading ? (
                <p
                    className="shrink-0 tracking-[0.2em] uppercase opacity-70"
                    style={{ fontSize: small }}
                >
                    {String(settings.heading)}
                </p>
            ) : null}
            {rooms.length === 0 ? (
                <p className="opacity-70">No rooms to show yet.</p>
            ) : (
                <div className="flex min-h-0 flex-1 flex-col justify-evenly gap-[1.5%]">
                    {rooms.map((entry, index) => {
                        const room = roomRecord(entry.room);
                        const state = roomState(
                            screenBookings(entry.bookings),
                            now,
                            10,
                        );
                        const style = ROOM_STATE_STYLE[state.kind];
                        const detail =
                            state.kind === 'busy'
                                ? `${state.current.title} · until ${state.current.end_label}`
                                : state.kind === 'soon'
                                  ? `${state.next.title} at ${state.next.start_label}`
                                  : state.next
                                    ? `Free until ${state.next.start_label}`
                                    : 'Free all day';

                        return (
                            <div
                                key={String(room.id ?? index)}
                                className="flex items-center gap-[3%] rounded-xl bg-white/10 px-[3%] py-[1.2%]"
                            >
                                <span
                                    className="size-[0.7em] shrink-0 rounded-full"
                                    style={{
                                        background: String(
                                            room.color ?? '#2563eb',
                                        ),
                                        fontSize,
                                    }}
                                />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="truncate font-semibold"
                                        style={{ fontSize }}
                                    >
                                        {String(room.name ?? 'Room')}
                                    </p>
                                    <p
                                        className="truncate opacity-70"
                                        style={{ fontSize: small }}
                                    >
                                        {detail}
                                    </p>
                                </div>
                                <span
                                    className="shrink-0 rounded-full px-[1.2em] py-[0.35em] font-semibold text-white"
                                    style={{
                                        background: style.background,
                                        fontSize: small,
                                    }}
                                >
                                    {style.label}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function Fallback({ title, body }: { title: string; body: string }) {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-2 p-8 text-center text-white">
            <p className="text-2xl font-semibold">{title}</p>
            <p className="text-white/70">{body}</p>
        </div>
    );
}
