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
}: {
    widget: WidgetPayload;
    timezone?: string;
}) {
    const key = canonicalWidgetKey(widget.key);
    const settings = widget.settings;
    const data = widget.data;
    const [now, setNow] = useState(() => new Date());
    const menuBox = useWidgetBoxMinEdge();

    useEffect(() => {
        if (
            !['clock', 'date', 'countdown', 'world_clock', 'booking'].includes(
                key,
            )
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

    if (typeof data.error === 'string') {
        return (
            <Fallback
                title={String(settings.location ?? key)}
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

        return <WebPageEmbed url={url} title="Web page" />;
    }

    if (key === 'youtube') {
        const id = youtubeId(String(settings.url ?? ''));

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

    return (
        <Fallback
            title={key.replaceAll('_', ' ')}
            body="Widget is configured."
        />
    );
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

function wasJustCalled(row: Record<string, WidgetJsonValue>): boolean {
    if (row.status !== 'called' && row.status !== 'serving') return false;
    if (typeof row.called_at !== 'string') return false;

    const age = Date.now() - Date.parse(row.called_at);
    return Number.isFinite(age) && age >= -5_000 && age < 10_000;
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
    const rows =
        widgetKey === 'queue_recently_called'
            ? recentlyCalled
            : widgetKey === 'queue_waiting_tickets'
              ? waiting
              : nowServing;

    const ticketRows = (
        <div className="min-h-0 flex-1 space-y-2 overflow-hidden">
            {rows.length === 0 ? (
                <p className="opacity-60" style={{ fontSize: rowSize }}>
                    No tickets
                </p>
            ) : (
                rows.map((row, index) => (
                    <div
                        key={String(row.id ?? `${row.number}-${index}`)}
                        className={`grid grid-cols-[1fr_auto] items-center gap-4 rounded-xl bg-white/10 px-4 py-2 ${widgetKey !== 'queue_waiting_tickets' && wasJustCalled(row) ? 'queue-call-blink' : ''}`}
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
                highlight={nowServing[0] ? wasJustCalled(nowServing[0]) : false}
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
                {ticketRows}
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
        <div className={`flex flex-1 flex-col items-center justify-center gap-2 rounded-xl ${highlight ? 'queue-call-blink' : ''}`}>
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

function Fallback({ title, body }: { title: string; body: string }) {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-2 p-8 text-center text-white">
            <p className="text-2xl font-semibold">{title}</p>
            <p className="text-white/70">{body}</p>
        </div>
    );
}
