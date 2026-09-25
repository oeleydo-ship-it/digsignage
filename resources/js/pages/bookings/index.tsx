import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    Cloud,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    addDays,
    addMonths,
    type BookingView,
    MonthView,
    rangeLabel,
    WeekView,
} from '@/components/bookings/booking-calendar-views';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type {
    BookingPermissions,
    BookingRoomSummary,
    RoomBookingRecord,
} from '@/types';

type Props = {
    date: string;
    view: BookingView;
    range: { start: string; end: string };
    today: string;
    filters: { room: number | null };
    rooms: BookingRoomSummary[];
    allRooms: { id: number; name: string }[];
    bookings: RoomBookingRecord[];
    pending: RoomBookingRecord[];
    permissions: BookingPermissions;
};

type Draft = {
    id?: number;
    meeting_room_id: string;
    title: string;
    date: string;
    start_time: string;
    end_time: string;
    organizer_name: string;
    organizer_email: string;
    attendees: string;
    notes: string;
};

const SLOT_MINUTES = 15;

function minutes(time: string): number {
    const [hours, mins] = time.split(':').map(Number);

    return (hours || 0) * 60 + (mins || 0);
}

function clock(total: number): string {
    const bounded = Math.max(0, Math.min(24 * 60 - 1, total));

    return `${String(Math.floor(bounded / 60)).padStart(2, '0')}:${String(bounded % 60).padStart(2, '0')}`;
}

const VIEWS: { value: BookingView; label: string }[] = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];

function step(view: BookingView, date: string, direction: 1 | -1): string {
    if (view === 'month') {
        return addMonths(date, direction);
    }

    return addDays(date, direction * (view === 'week' ? 7 : 1));
}

function longDate(date: string): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function statusTone(status: RoomBookingRecord['status']): string {
    return status === 'pending'
        ? 'border-amber-400 bg-amber-100 text-amber-950 dark:bg-amber-500/25 dark:text-amber-50'
        : 'border-sky-500 bg-sky-100 text-sky-950 dark:bg-sky-500/25 dark:text-sky-50';
}

export default function BookingsIndex({
    date,
    view,
    range,
    today,
    filters,
    rooms,
    allRooms,
    bookings,
    pending,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const base = `/${slug}/bookings`;
    const [draft, setDraft] = useState<Draft | null>(null);
    const [selected, setSelected] = useState<RoomBookingRecord | null>(null);
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 60_000);

        return () => window.clearInterval(timer);
    }, []);

    // Show the widest opening window of the visible rooms.
    const [dayStart, dayEnd] = useMemo(() => {
        if (rooms.length === 0) {
            return [7 * 60, 21 * 60];
        }

        const open = Math.min(...rooms.map((room) => minutes(room.opens_at)));
        const close = Math.max(...rooms.map((room) => minutes(room.closes_at)));

        return [
            Math.floor(open / 60) * 60,
            Math.min(24 * 60, Math.ceil(close / 60) * 60),
        ];
    }, [rooms]);
    const span = Math.max(60, dayEnd - dayStart);
    const hours = Array.from(
        { length: Math.floor(span / 60) + 1 },
        (_, index) => dayStart + index * 60,
    );
    const nowMinutes = now.getHours() * 60 + now.getMinutes();
    const showNow =
        date === today && nowMinutes >= dayStart && nowMinutes <= dayEnd;

    const visit = (params: {
        date?: string;
        room?: number | null;
        view?: BookingView;
    }) => {
        const nextView = params.view ?? view;

        router.get(
            base,
            {
                date: params.date ?? date,
                room: params.room === undefined ? filters.room : params.room,
                view: nextView === 'day' ? undefined : nextView,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openNew = (roomId?: number, start?: number, onDate?: string) => {
        const bookingDate = onDate ?? date;
        const startAt =
            start ??
            (bookingDate === today
                ? Math.max(dayStart, Math.ceil(nowMinutes / 30) * 30)
                : Math.max(dayStart, 9 * 60));
        setDraft({
            meeting_room_id: String(roomId ?? rooms[0]?.id ?? ''),
            title: '',
            date: bookingDate,
            start_time: clock(startAt),
            end_time: clock(startAt + 60),
            organizer_name: '',
            organizer_email: '',
            attendees: '',
            notes: '',
        });
    };

    const openEdit = (booking: RoomBookingRecord) => {
        setSelected(null);
        setDraft({
            id: booking.id,
            meeting_room_id: String(booking.room_id),
            title: booking.title,
            date: booking.date,
            start_time: booking.start_time,
            end_time: booking.end_time,
            organizer_name: booking.organizer_name ?? '',
            organizer_email: booking.organizer_email ?? '',
            attendees: booking.attendees ? String(booking.attendees) : '',
            notes: booking.notes ?? '',
        });
    };

    const act = (
        booking: RoomBookingRecord,
        action: 'cancel' | 'approve' | 'decline',
    ) =>
        router.post(
            `${base}/${booking.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setSelected(null),
            },
        );

    return (
        <>
            <Head title="Room bookings" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <Heading
                        title="Room bookings"
                        description="See every meeting room at a glance. Click an empty slot to book it, or a booking to change it."
                    />
                    {permissions.canCreateBooking && rooms.length > 0 && (
                        <Button
                            onClick={() => openNew()}
                            data-test="new-booking"
                        >
                            <CalendarPlus className="size-4" />
                            New booking
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div
                        role="tablist"
                        aria-label="Calendar view"
                        className="bg-muted flex gap-1 rounded-lg p-1"
                    >
                        {VIEWS.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="tab"
                                aria-selected={view === option.value}
                                data-test={`view-${option.value}`}
                                onClick={() => visit({ view: option.value })}
                                className={`rounded-md px-3 py-1 text-sm font-medium ${
                                    view === option.value
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            size="icon"
                            variant="outline"
                            aria-label={`Previous ${view}`}
                            onClick={() =>
                                visit({ date: step(view, date, -1) })
                            }
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            disabled={
                                view === 'day'
                                    ? date === today
                                    : today >= range.start && today <= range.end
                            }
                            onClick={() => visit({ date: today })}
                        >
                            Today
                        </Button>
                        <Button
                            size="icon"
                            variant="outline"
                            aria-label={`Next ${view}`}
                            onClick={() => visit({ date: step(view, date, 1) })}
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    <Input
                        type="date"
                        aria-label="Date"
                        className="w-auto"
                        value={date}
                        onChange={(event) =>
                            event.target.value &&
                            visit({ date: event.target.value })
                        }
                    />
                    <select
                        aria-label="Room"
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.room ?? ''}
                        onChange={(event) =>
                            visit({
                                room: event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            })
                        }
                    >
                        <option value="">All rooms</option>
                        {allRooms.map((room) => (
                            <option key={room.id} value={room.id}>
                                {room.name}
                            </option>
                        ))}
                    </select>
                    <p className="text-muted-foreground text-sm font-medium">
                        {rangeLabel(view, date)}
                    </p>
                </div>

                {pending.length > 0 && (
                    <section className="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <h2 className="font-semibold">
                            {pending.length} request
                            {pending.length === 1 ? '' : 's'} awaiting approval
                        </h2>
                        <div className="mt-3 grid gap-2">
                            {pending.map((booking) => (
                                <div
                                    key={booking.id}
                                    className="bg-background flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <button
                                        type="button"
                                        className="min-w-0 text-left"
                                        onClick={() => setSelected(booking)}
                                    >
                                        <p className="truncate font-medium">
                                            {booking.title}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {booking.room_name} ·{' '}
                                            {longDate(booking.date)} ·{' '}
                                            {booking.start_time}–
                                            {booking.end_time} ·{' '}
                                            {booking.organizer_name}
                                        </p>
                                    </button>
                                    {booking.can_approve && (
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    act(booking, 'approve')
                                                }
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    act(booking, 'decline')
                                                }
                                            >
                                                Decline
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {rooms.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">No meeting rooms yet</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {permissions.canManageRooms
                                ? 'Add your first room under Rooms, or import them from Microsoft 365.'
                                : 'Ask an administrator to add meeting rooms.'}
                        </p>
                        {permissions.canManageRooms && (
                            <Button
                                className="mt-4"
                                variant="outline"
                                onClick={() => router.get(`/${slug}/rooms`)}
                            >
                                Go to rooms
                            </Button>
                        )}
                    </div>
                ) : view === 'week' ? (
                    <WeekView
                        date={date}
                        today={today}
                        rooms={rooms}
                        bookings={bookings}
                        canCreate={permissions.canCreateBooking}
                        onSelect={setSelected}
                        onCreate={(roomId, day) =>
                            openNew(roomId, undefined, day)
                        }
                        onOpenDay={(day) => visit({ date: day, view: 'day' })}
                    />
                ) : view === 'month' ? (
                    <MonthView
                        date={date}
                        range={range}
                        today={today}
                        rooms={rooms}
                        bookings={bookings}
                        canCreate={permissions.canCreateBooking}
                        onSelect={setSelected}
                        onCreate={(roomId, day) =>
                            openNew(roomId, undefined, day)
                        }
                        onOpenDay={(day) => visit({ date: day, view: 'day' })}
                    />
                ) : (
                    <div className="bg-card overflow-x-auto rounded-xl border shadow-sm">
                        <div className="min-w-[760px]">
                            <div className="bg-muted/40 flex border-b text-xs">
                                <div className="w-48 shrink-0 border-r px-3 py-2 font-medium">
                                    Room
                                </div>
                                <div className="relative h-8 flex-1">
                                    {hours.map((hour) => (
                                        <span
                                            key={hour}
                                            className="text-muted-foreground absolute top-2 -translate-x-1/2 tabular-nums"
                                            style={{
                                                left: `${((hour - dayStart) / span) * 100}%`,
                                            }}
                                        >
                                            {hour < dayEnd || hour === dayStart
                                                ? clock(hour)
                                                : ''}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            {rooms.map((room) => {
                                const roomBookings = bookings.filter(
                                    (booking) => booking.room_id === room.id,
                                );

                                return (
                                    <div
                                        key={room.id}
                                        className="flex border-b last:border-b-0"
                                    >
                                        <div className="w-48 shrink-0 border-r px-3 py-3">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="size-2.5 shrink-0 rounded-full"
                                                    style={{
                                                        background: room.color,
                                                    }}
                                                />
                                                <p className="truncate text-sm font-medium">
                                                    {room.name}
                                                </p>
                                                {room.microsoft && (
                                                    <Cloud
                                                        className="text-muted-foreground size-3.5 shrink-0"
                                                        aria-label="Synced with Microsoft 365"
                                                    />
                                                )}
                                            </div>
                                            <p className="text-muted-foreground mt-0.5 flex items-center gap-1 text-xs">
                                                {room.capacity ? (
                                                    <>
                                                        <Users className="size-3" />{' '}
                                                        {room.capacity}
                                                    </>
                                                ) : null}
                                                {room.location
                                                    ? ` · ${room.location}`
                                                    : ''}
                                                {!room.is_active
                                                    ? ' · inactive'
                                                    : ''}
                                            </p>
                                        </div>
                                        <div
                                            className={`relative min-h-16 flex-1 ${permissions.canCreateBooking && room.is_active ? 'cursor-copy' : ''}`}
                                            onClick={(event) => {
                                                if (
                                                    !permissions.canCreateBooking ||
                                                    !room.is_active ||
                                                    event.target !==
                                                        event.currentTarget
                                                ) {
                                                    return;
                                                }

                                                const bounds =
                                                    event.currentTarget.getBoundingClientRect();
                                                const ratio =
                                                    (event.clientX -
                                                        bounds.left) /
                                                    bounds.width;
                                                const start =
                                                    dayStart +
                                                    Math.floor(
                                                        (ratio * span) /
                                                            SLOT_MINUTES,
                                                    ) *
                                                        SLOT_MINUTES;
                                                openNew(room.id, start);
                                            }}
                                        >
                                            {hours.slice(1, -1).map((hour) => (
                                                <span
                                                    key={hour}
                                                    aria-hidden
                                                    className="bg-border pointer-events-none absolute inset-y-0 w-px"
                                                    style={{
                                                        left: `${((hour - dayStart) / span) * 100}%`,
                                                    }}
                                                />
                                            ))}
                                            {/* Shade hours the room is closed. */}
                                            <span
                                                aria-hidden
                                                className="bg-muted/60 pointer-events-none absolute inset-y-0 left-0"
                                                style={{
                                                    width: `${Math.max(0, ((minutes(room.opens_at) - dayStart) / span) * 100)}%`,
                                                }}
                                            />
                                            <span
                                                aria-hidden
                                                className="bg-muted/60 pointer-events-none absolute inset-y-0 right-0"
                                                style={{
                                                    width: `${Math.max(0, ((dayEnd - minutes(room.closes_at)) / span) * 100)}%`,
                                                }}
                                            />
                                            {showNow && (
                                                <span
                                                    aria-hidden
                                                    className="pointer-events-none absolute inset-y-0 z-10 w-0.5 bg-rose-500"
                                                    style={{
                                                        left: `${((nowMinutes - dayStart) / span) * 100}%`,
                                                    }}
                                                />
                                            )}
                                            {roomBookings.map((booking) => {
                                                const startsOnDay =
                                                    booking.date === date;
                                                const from = startsOnDay
                                                    ? minutes(
                                                          booking.start_time,
                                                      )
                                                    : dayStart;
                                                const until =
                                                    booking.end_time <=
                                                        booking.start_time &&
                                                    startsOnDay
                                                        ? dayEnd
                                                        : minutes(
                                                              booking.end_time,
                                                          );
                                                const left = Math.max(
                                                    0,
                                                    ((from - dayStart) / span) *
                                                        100,
                                                );
                                                const width = Math.max(
                                                    1.5,
                                                    ((Math.min(until, dayEnd) -
                                                        Math.max(
                                                            from,
                                                            dayStart,
                                                        )) /
                                                        span) *
                                                        100,
                                                );

                                                return (
                                                    <button
                                                        key={booking.id}
                                                        type="button"
                                                        data-test="booking-block"
                                                        className={`absolute inset-y-1.5 z-20 overflow-hidden rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-sm hover:brightness-95 ${statusTone(booking.status)}`}
                                                        style={{
                                                            left: `${left}%`,
                                                            width: `${width}%`,
                                                        }}
                                                        onClick={() =>
                                                            setSelected(booking)
                                                        }
                                                        title={`${booking.start_time}–${booking.end_time} ${booking.title}`}
                                                    >
                                                        <p className="truncate font-semibold">
                                                            {booking.title}
                                                        </p>
                                                        <p className="truncate opacity-80">
                                                            {booking.start_time}
                                                            –{booking.end_time}
                                                            {booking.status ===
                                                            'pending'
                                                                ? ' · pending'
                                                                : ''}
                                                        </p>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            <BookingDetails
                booking={selected}
                onClose={() => setSelected(null)}
                onEdit={openEdit}
                onAction={act}
            />

            {draft && (
                <BookingForm
                    key={
                        draft.id ??
                        `${draft.meeting_room_id}-${draft.date}-${draft.start_time}`
                    }
                    draft={draft}
                    rooms={rooms.length > 0 ? rooms : []}
                    allRooms={allRooms}
                    action={draft.id ? `${base}/${draft.id}` : base}
                    method={draft.id ? 'patch' : 'post'}
                    onClose={() => setDraft(null)}
                />
            )}
        </>
    );
}

function BookingDetails({
    booking,
    onClose,
    onEdit,
    onAction,
}: {
    booking: RoomBookingRecord | null;
    onClose: () => void;
    onEdit: (booking: RoomBookingRecord) => void;
    onAction: (
        booking: RoomBookingRecord,
        action: 'cancel' | 'approve' | 'decline',
    ) => void;
}) {
    const [confirmCancel, setConfirmCancel] = useState(false);

    useEffect(() => setConfirmCancel(false), [booking?.id]);

    return (
        <Dialog
            open={booking !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                {booking && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{booking.title}</DialogTitle>
                            <DialogDescription>
                                {booking.room_name} · {longDate(booking.date)} ·{' '}
                                {booking.start_time}–{booking.end_time}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex flex-wrap gap-1.5">
                            <Badge
                                variant={
                                    booking.status === 'pending'
                                        ? 'outline'
                                        : 'secondary'
                                }
                            >
                                {booking.status_label}
                            </Badge>
                            <Badge variant="outline">
                                {booking.source_label}
                            </Badge>
                            {booking.microsoft && (
                                <Badge variant="outline">
                                    <Cloud className="size-3" /> In Outlook
                                </Badge>
                            )}
                        </div>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            {booking.organizer_name && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Organizer
                                    </dt>
                                    <dd>{booking.organizer_name}</dd>
                                </>
                            )}
                            {booking.organizer_email && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd className="break-all">
                                        {booking.organizer_email}
                                    </dd>
                                </>
                            )}
                            {booking.attendees && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Attendees
                                    </dt>
                                    <dd>{booking.attendees}</dd>
                                </>
                            )}
                            {booking.notes && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Notes
                                    </dt>
                                    <dd className="whitespace-pre-wrap">
                                        {booking.notes}
                                    </dd>
                                </>
                            )}
                        </dl>
                        {booking.sync_error && (
                            <p className="text-destructive text-sm">
                                {booking.sync_error}
                            </p>
                        )}
                        <DialogFooter className="gap-2 sm:justify-between">
                            <div className="flex gap-2">
                                {booking.status === 'pending' &&
                                    booking.can_approve && (
                                        <>
                                            <Button
                                                onClick={() =>
                                                    onAction(booking, 'approve')
                                                }
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    onAction(booking, 'decline')
                                                }
                                            >
                                                Decline
                                            </Button>
                                        </>
                                    )}
                            </div>
                            {booking.can_edit && (
                                <div className="flex gap-2">
                                    {confirmCancel ? (
                                        <Button
                                            variant="destructive"
                                            onClick={() =>
                                                onAction(booking, 'cancel')
                                            }
                                        >
                                            Confirm cancel
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() =>
                                                setConfirmCancel(true)
                                            }
                                        >
                                            Cancel booking
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        onClick={() => onEdit(booking)}
                                    >
                                        Edit
                                    </Button>
                                </div>
                            )}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function BookingForm({
    draft,
    allRooms,
    action,
    method,
    onClose,
}: {
    draft: Draft;
    rooms: BookingRoomSummary[];
    allRooms: { id: number; name: string }[];
    action: string;
    method: 'post' | 'patch';
    onClose: () => void;
}) {
    const form = useForm<Draft>(draft);
    // Booking rules report under starts_at / ends_at, which are not form fields.
    const errors = form.errors as Record<string, string | undefined>;
    const editing = method === 'patch';

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            form.patch(action, options);
        } else {
            form.post(action, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit booking' : 'Book a meeting room'}
                        </DialogTitle>
                        <DialogDescription>
                            Times are in the room&apos;s local time. The room is
                            checked for clashes when you save.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="booking-room">Room</Label>
                        <select
                            id="booking-room"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={form.data.meeting_room_id}
                            onChange={(event) =>
                                form.setData(
                                    'meeting_room_id',
                                    event.target.value,
                                )
                            }
                            required
                        >
                            {allRooms.map((room) => (
                                <option key={room.id} value={room.id}>
                                    {room.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.meeting_room_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="booking-title">Meeting title</Label>
                        <Input
                            id="booking-title"
                            data-test="booking-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder="Weekly planning"
                            required
                            autoFocus
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="booking-date">Date</Label>
                            <Input
                                id="booking-date"
                                type="date"
                                value={form.data.date}
                                onChange={(event) =>
                                    form.setData('date', event.target.value)
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="booking-start">Start</Label>
                            <Input
                                id="booking-start"
                                type="time"
                                step={SLOT_MINUTES * 60}
                                value={form.data.start_time}
                                onChange={(event) =>
                                    form.setData(
                                        'start_time',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="booking-end">End</Label>
                            <Input
                                id="booking-end"
                                type="time"
                                step={SLOT_MINUTES * 60}
                                value={form.data.end_time}
                                onChange={(event) =>
                                    form.setData('end_time', event.target.value)
                                }
                                required
                            />
                        </div>
                    </div>
                    <InputError
                        message={
                            errors.starts_at ?? errors.start_time ?? errors.date
                        }
                    />
                    <InputError message={errors.ends_at ?? errors.end_time} />

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="booking-organizer">Organizer</Label>
                            <Input
                                id="booking-organizer"
                                value={form.data.organizer_name}
                                onChange={(event) =>
                                    form.setData(
                                        'organizer_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Defaults to you"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="booking-email">
                                Organizer email
                            </Label>
                            <Input
                                id="booking-email"
                                type="email"
                                value={form.data.organizer_email}
                                onChange={(event) =>
                                    form.setData(
                                        'organizer_email',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.organizer_email} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="booking-attendees">Attendees</Label>
                        <Input
                            id="booking-attendees"
                            type="number"
                            min={1}
                            className="w-32"
                            value={form.data.attendees}
                            onChange={(event) =>
                                form.setData('attendees', event.target.value)
                            }
                        />
                        <InputError message={form.errors.attendees} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="booking-notes">Notes</Label>
                        <textarea
                            id="booking-notes"
                            rows={3}
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="Catering, equipment, dial-in details"
                        />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            data-test="save-booking"
                        >
                            {editing ? 'Save changes' : 'Book room'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

BookingsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Room bookings',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/bookings`
                : '/',
        },
    ],
});
