import { Users } from 'lucide-react';
import type { BookingRoomSummary, RoomBookingRecord } from '@/types';

export type BookingView = 'day' | 'week' | 'month';

/** Dates are plain "YYYY-MM-DD" strings, handled at local noon to dodge DST. */
function parse(date: string): Date {
    return new Date(`${date}T12:00:00`);
}

function format(value: Date): string {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}

export function addDays(date: string, days: number): string {
    const value = parse(date);
    value.setDate(value.getDate() + days);

    return format(value);
}

export function addMonths(date: string, months: number): string {
    const value = parse(date);
    const day = value.getDate();
    value.setDate(1);
    value.setMonth(value.getMonth() + months);
    // Clamp 31 Jan + 1 month to the end of February.
    const last = new Date(
        value.getFullYear(),
        value.getMonth() + 1,
        0,
    ).getDate();
    value.setDate(Math.min(day, last));

    return format(value);
}

/** Monday of the week containing the date. */
export function startOfWeek(date: string): string {
    const value = parse(date);

    return addDays(date, -((value.getDay() + 6) % 7));
}

/** Every date from start to end, inclusive. */
export function datesBetween(start: string, end: string): string[] {
    const dates: string[] = [];

    for (let day = start; day <= end; day = addDays(day, 1)) {
        dates.push(day);
    }

    return dates;
}

export function rangeLabel(view: BookingView, date: string): string {
    const value = parse(date);

    if (view === 'month') {
        return value.toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric',
        });
    }

    if (view === 'week') {
        const monday = parse(startOfWeek(date));
        const sunday = parse(addDays(startOfWeek(date), 6));
        const sameMonth = monday.getMonth() === sunday.getMonth();

        return `${monday.toLocaleDateString(undefined, {
            day: 'numeric',
            ...(sameMonth ? {} : { month: 'short' }),
        })} – ${sunday.toLocaleDateString(undefined, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        })}`;
    }

    return value.toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function chipTone(status: RoomBookingRecord['status']): string {
    return status === 'pending'
        ? 'bg-amber-100 text-amber-950 hover:bg-amber-200 dark:bg-amber-500/25 dark:text-amber-50'
        : 'bg-sky-100 text-sky-950 hover:bg-sky-200 dark:bg-sky-500/25 dark:text-sky-50';
}

type Shared = {
    today: string;
    rooms: BookingRoomSummary[];
    bookings: RoomBookingRecord[];
    canCreate: boolean;
    onSelect: (booking: RoomBookingRecord) => void;
    onCreate: (roomId: number | undefined, date: string) => void;
    onOpenDay: (date: string) => void;
};

function byDate(
    bookings: RoomBookingRecord[],
): Map<string, RoomBookingRecord[]> {
    const map = new Map<string, RoomBookingRecord[]>();

    for (const booking of bookings) {
        const list = map.get(booking.date) ?? [];
        list.push(booking);
        map.set(booking.date, list);
    }

    for (const list of map.values()) {
        list.sort((a, b) => a.start_time.localeCompare(b.start_time));
    }

    return map;
}

function DayHeading({
    date,
    today,
    onOpenDay,
    compact = false,
}: {
    date: string;
    today: string;
    onOpenDay: (date: string) => void;
    compact?: boolean;
}) {
    const value = parse(date);
    const isToday = date === today;

    return (
        <button
            type="button"
            onClick={() => onOpenDay(date)}
            title="Open this day"
            className="hover:text-primary inline-flex items-baseline gap-1.5"
        >
            {!compact && (
                <span className="text-muted-foreground text-xs uppercase">
                    {value.toLocaleDateString(undefined, { weekday: 'short' })}
                </span>
            )}
            <span
                className={`inline-flex size-6 items-center justify-center rounded-full text-sm tabular-nums ${
                    isToday
                        ? 'bg-primary text-primary-foreground font-semibold'
                        : ''
                }`}
            >
                {value.getDate()}
            </span>
        </button>
    );
}

function Chip({
    booking,
    color,
    showRoom,
    onSelect,
}: {
    booking: RoomBookingRecord;
    color?: string;
    showRoom: boolean;
    onSelect: (booking: RoomBookingRecord) => void;
}) {
    return (
        <button
            type="button"
            data-test="booking-chip"
            onClick={(event) => {
                event.stopPropagation();
                onSelect(booking);
            }}
            title={`${booking.start_time}–${booking.end_time} ${booking.title} · ${booking.room_name}`}
            className={`flex w-full min-w-0 items-center gap-1.5 rounded px-1.5 py-0.5 text-left text-xs ${chipTone(booking.status)}`}
        >
            {showRoom && (
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full"
                    style={{ background: color }}
                />
            )}
            <span className="shrink-0 tabular-nums opacity-80">
                {booking.start_time}
            </span>
            <span className="truncate font-medium">{booking.title}</span>
        </button>
    );
}

/**
 * Rooms down the side, Monday to Sunday across: every booking of the week
 * as a chip in its room's day cell.
 */
export function WeekView({
    date,
    today,
    rooms,
    bookings,
    canCreate,
    onSelect,
    onCreate,
    onOpenDay,
}: Shared & { date: string }) {
    const days = datesBetween(startOfWeek(date), addDays(startOfWeek(date), 6));

    return (
        <div
            className="bg-card overflow-x-auto rounded-xl border shadow-sm"
            data-test="week-view"
        >
            <div className="min-w-[900px]">
                <div className="bg-muted/40 grid grid-cols-[12rem_repeat(7,minmax(0,1fr))] border-b">
                    <div className="border-r px-3 py-2 text-xs font-medium">
                        Room
                    </div>
                    {days.map((day) => (
                        <div
                            key={day}
                            className={`border-r px-2 py-1.5 last:border-r-0 ${day === today ? 'bg-primary/5' : ''}`}
                        >
                            <DayHeading
                                date={day}
                                today={today}
                                onOpenDay={onOpenDay}
                            />
                        </div>
                    ))}
                </div>
                {rooms.map((room) => {
                    const perDay = byDate(
                        bookings.filter(
                            (booking) => booking.room_id === room.id,
                        ),
                    );

                    return (
                        <div
                            key={room.id}
                            className="grid grid-cols-[12rem_repeat(7,minmax(0,1fr))] border-b last:border-b-0"
                        >
                            <div className="border-r px-3 py-3">
                                <div className="flex items-center gap-2">
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{ background: room.color }}
                                    />
                                    <p className="truncate text-sm font-medium">
                                        {room.name}
                                    </p>
                                </div>
                                {room.capacity ? (
                                    <p className="text-muted-foreground mt-0.5 flex items-center gap-1 text-xs">
                                        <Users className="size-3" />
                                        {room.capacity}
                                    </p>
                                ) : null}
                            </div>
                            {days.map((day) => {
                                const clickable =
                                    canCreate && room.is_active && day >= today;

                                return (
                                    <div
                                        key={day}
                                        data-test={`week-cell-${room.id}-${day}`}
                                        className={`min-h-20 space-y-1 border-r p-1.5 last:border-r-0 ${
                                            day === today ? 'bg-primary/5' : ''
                                        } ${clickable ? 'hover:bg-accent/50 cursor-copy' : ''}`}
                                        onClick={() =>
                                            clickable && onCreate(room.id, day)
                                        }
                                    >
                                        {(perDay.get(day) ?? []).map(
                                            (booking) => (
                                                <Chip
                                                    key={booking.id}
                                                    booking={booking}
                                                    showRoom={false}
                                                    onSelect={onSelect}
                                                />
                                            ),
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const MONTH_LIMIT = 3;

/**
 * A month calendar: each day lists its first bookings across all visible
 * rooms, with a link to the full day.
 */
export function MonthView({
    date,
    range,
    today,
    rooms,
    bookings,
    canCreate,
    onSelect,
    onCreate,
    onOpenDay,
}: Shared & { date: string; range: { start: string; end: string } }) {
    const days = datesBetween(range.start, range.end);
    const month = parse(date).getMonth();
    const perDay = byDate(bookings);
    const colors = new Map(rooms.map((room) => [room.id, room.color]));
    const weekdays = days
        .slice(0, 7)
        .map((day) =>
            parse(day).toLocaleDateString(undefined, { weekday: 'short' }),
        );

    return (
        <div
            className="bg-card overflow-x-auto rounded-xl border shadow-sm"
            data-test="month-view"
        >
            <div className="min-w-[760px]">
                <div className="bg-muted/40 grid grid-cols-7 border-b">
                    {weekdays.map((weekday) => (
                        <div
                            key={weekday}
                            className="text-muted-foreground border-r px-2 py-2 text-xs font-medium uppercase last:border-r-0"
                        >
                            {weekday}
                        </div>
                    ))}
                </div>
                <div className="grid grid-cols-7">
                    {days.map((day) => {
                        const list = perDay.get(day) ?? [];
                        const inMonth = parse(day).getMonth() === month;
                        const clickable = canCreate && day >= today;

                        return (
                            <div
                                key={day}
                                data-test={`month-cell-${day}`}
                                className={`min-h-28 space-y-1 border-r border-b p-1.5 [&:nth-child(7n)]:border-r-0 ${
                                    inMonth
                                        ? ''
                                        : 'bg-muted/30 text-muted-foreground'
                                } ${clickable ? 'hover:bg-accent/40 cursor-copy' : ''}`}
                                onClick={() =>
                                    clickable && onCreate(undefined, day)
                                }
                            >
                                <div
                                    className="flex items-center justify-between"
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <DayHeading
                                        date={day}
                                        today={today}
                                        onOpenDay={onOpenDay}
                                        compact
                                    />
                                    {list.length > 0 && (
                                        <span className="text-muted-foreground text-[11px] tabular-nums">
                                            {list.length}
                                        </span>
                                    )}
                                </div>
                                {list.slice(0, MONTH_LIMIT).map((booking) => (
                                    <Chip
                                        key={booking.id}
                                        booking={booking}
                                        color={colors.get(booking.room_id)}
                                        showRoom
                                        onSelect={onSelect}
                                    />
                                ))}
                                {list.length > MONTH_LIMIT && (
                                    <button
                                        type="button"
                                        className="text-primary px-1.5 text-xs font-medium hover:underline"
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            onOpenDay(day);
                                        }}
                                    >
                                        +{list.length - MONTH_LIMIT} more
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
