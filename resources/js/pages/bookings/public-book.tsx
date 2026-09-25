import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    CheckCircle2,
    Clock3,
    MapPin,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, type ReactNode } from 'react';

type Room = {
    name: string;
    description: string | null;
    capacity: number | null;
    amenities: string[];
    color: string;
    location: string | null;
    organization: string;
    timezone: string;
    opens_at: string;
    closes_at: string;
    min_duration: number;
    max_duration: number;
    requires_approval: boolean;
    token: string;
};

/** Minutes past local midnight, clipped to the day being shown. */
type Busy = { start: number; end: number; pending: boolean };

type Confirmation = {
    title: string;
    date: string;
    start: string;
    end: string;
    pending: boolean;
} | null;

type Props = {
    room: Room;
    date: string;
    today: string;
    lastDate: string;
    busy: Busy[];
    confirmation: Confirmation;
};

const STEP = 15;

function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return (hours || 0) * 60 + (minutes || 0);
}

function toClock(total: number): string {
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function nowInRoom(timezone: string): number {
    try {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date());

        return (
            Number(parts.find((part) => part.type === 'hour')?.value ?? 0) *
                60 +
            Number(parts.find((part) => part.type === 'minute')?.value ?? 0)
        );
    } catch {
        const now = new Date();

        return now.getHours() * 60 + now.getMinutes();
    }
}

function durationLabel(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} minutes`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0
        ? `${hours} hour${hours === 1 ? '' : 's'}`
        : `${hours} h ${rest} min`;
}

export default function PublicRoomBooking({
    room,
    date,
    today,
    lastDate,
    busy,
    confirmation,
}: Props) {
    const open = toMinutes(room.opens_at);
    const close =
        room.closes_at === '00:00' ? 24 * 60 : toMinutes(room.closes_at);
    // The server sends minutes past midnight, already clipped to this day.
    const blocks = busy;
    const earliest =
        date === today ? Math.ceil(nowInRoom(room.timezone) / STEP) * STEP : 0;

    const starts = useMemo(() => {
        const options: number[] = [];

        for (
            let start = Math.ceil(open / STEP) * STEP;
            start + room.min_duration <= close;
            start += STEP
        ) {
            const clear = blocks.every(
                (block) =>
                    start + room.min_duration <= block.start ||
                    start >= block.end,
            );

            if (start >= earliest && clear) {
                options.push(start);
            }
        }

        return options;
    }, [open, close, earliest, room.min_duration, busy]); // eslint-disable-line react-hooks/exhaustive-deps

    const form = useForm({
        title: '',
        organizer_name: '',
        organizer_email: '',
        date,
        start_time: starts[0] !== undefined ? toClock(starts[0]) : '',
        duration: String(
            Math.max(room.min_duration, Math.min(60, room.max_duration)),
        ),
        attendees: '',
        notes: '',
        website: '',
    });

    // Booking rules report under starts_at / ends_at, which are not form fields.
    const errors = form.errors as Record<string, string | undefined>;
    const start = form.data.start_time ? toMinutes(form.data.start_time) : null;
    const durations = useMemo(() => {
        if (start === null) {
            return [];
        }

        const nextBusy = Math.min(
            close,
            ...blocks
                .filter((block) => block.start >= start + 1)
                .map((block) => block.start),
        );
        const options = new Set<number>();

        for (
            let length = room.min_duration;
            length <= room.max_duration;
            length += length < 60 ? STEP : 30
        ) {
            if (start + length <= nextBusy) {
                options.add(length);
            }
        }

        return [...options];
    }, [start, close, room.min_duration, room.max_duration, busy]); // eslint-disable-line react-hooks/exhaustive-deps

    const changeDate = (value: string) => {
        if (value) {
            router.get(
                `/book/${room.token}`,
                { date: value },
                { preserveScroll: true, replace: true },
            );
        }
    };

    const submit = () => {
        form.post(`/book/${room.token}`, {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'notes', 'attendees'),
        });
    };

    const selectedDuration = Number(form.data.duration);
    const durationValid = durations.includes(selectedDuration);

    // Keep the length valid when the chosen start leaves less room.
    useEffect(() => {
        if (!durationValid && durations[0] !== undefined) {
            form.setData('duration', String(durations[0]));
        }
    }, [durationValid, durations]); // eslint-disable-line react-hooks/exhaustive-deps
    const dayLength = Math.max(60, close - open);

    return (
        <>
            <Head title={`Book ${room.name}`} />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:py-12">
                <div className="mx-auto max-w-lg space-y-5">
                    <header className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div
                            className="h-2"
                            style={{ background: room.color }}
                        />
                        <div className="space-y-2 p-5">
                            <p className="text-sm font-medium text-slate-500">
                                {room.organization}
                            </p>
                            <h1 className="text-2xl font-bold">{room.name}</h1>
                            <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                                {room.location && (
                                    <span className="inline-flex items-center gap-1">
                                        <MapPin className="size-4" />{' '}
                                        {room.location}
                                    </span>
                                )}
                                {room.capacity && (
                                    <span className="inline-flex items-center gap-1">
                                        <Users className="size-4" /> Up to{' '}
                                        {room.capacity}
                                    </span>
                                )}
                                <span className="inline-flex items-center gap-1">
                                    <Clock3 className="size-4" />{' '}
                                    {room.opens_at}–{room.closes_at}
                                </span>
                            </p>
                            {room.description && (
                                <p className="text-sm text-slate-600">
                                    {room.description}
                                </p>
                            )}
                            {room.amenities.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 pt-1">
                                    {room.amenities.map((amenity) => (
                                        <span
                                            key={amenity}
                                            className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700"
                                        >
                                            {amenity}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </div>
                    </header>

                    {confirmation && (
                        <section
                            role="status"
                            className={`rounded-2xl p-5 shadow-sm ring-1 ${
                                confirmation.pending
                                    ? 'bg-amber-50 ring-amber-200'
                                    : 'bg-emerald-50 ring-emerald-200'
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                <CheckCircle2
                                    className={`mt-0.5 size-6 shrink-0 ${confirmation.pending ? 'text-amber-600' : 'text-emerald-600'}`}
                                />
                                <div>
                                    <p className="font-semibold">
                                        {confirmation.pending
                                            ? 'Request sent'
                                            : 'You’re booked'}
                                    </p>
                                    <p className="text-sm text-slate-700">
                                        {confirmation.title} ·{' '}
                                        {confirmation.date},{' '}
                                        {confirmation.start}–{confirmation.end}
                                    </p>
                                    {confirmation.pending && (
                                        <p className="mt-1 text-sm text-slate-600">
                                            A booking manager will confirm your
                                            request.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div className="flex items-end justify-between gap-3">
                            <label className="grid flex-1 gap-1.5 text-sm font-medium">
                                Date
                                <input
                                    type="date"
                                    className="h-11 rounded-lg border border-slate-300 px-3 text-base"
                                    min={today}
                                    max={lastDate}
                                    value={date}
                                    onChange={(event) =>
                                        changeDate(event.target.value)
                                    }
                                />
                            </label>
                        </div>

                        <div aria-label="Availability" className="space-y-1">
                            <div className="relative h-7 overflow-hidden rounded-md bg-emerald-100">
                                {blocks.map((block, index) => (
                                    <span
                                        key={index}
                                        className={`absolute inset-y-0 ${block.pending ? 'bg-amber-300' : 'bg-rose-400'}`}
                                        style={{
                                            left: `${Math.max(0, ((block.start - open) / dayLength) * 100)}%`,
                                            width: `${Math.max(1, ((Math.min(block.end, close) - Math.max(block.start, open)) / dayLength) * 100)}%`,
                                        }}
                                    />
                                ))}
                            </div>
                            <div className="flex justify-between text-xs text-slate-500">
                                <span>{room.opens_at}</span>
                                <span className="flex gap-3">
                                    <span className="inline-flex items-center gap-1">
                                        <span className="size-2 rounded-full bg-emerald-400" />{' '}
                                        Free
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <span className="size-2 rounded-full bg-rose-400" />{' '}
                                        Booked
                                    </span>
                                </span>
                                <span>{room.closes_at}</span>
                            </div>
                        </div>

                        {starts.length === 0 ? (
                            <p className="rounded-lg bg-slate-100 p-4 text-center text-sm text-slate-600">
                                No free times left on this day. Try another
                                date.
                            </p>
                        ) : (
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    submit();
                                }}
                            >
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Start
                                        <select
                                            className="h-11 rounded-lg border border-slate-300 px-3 text-base"
                                            value={form.data.start_time}
                                            onChange={(event) =>
                                                form.setData(
                                                    'start_time',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {starts.map((option) => (
                                                <option
                                                    key={option}
                                                    value={toClock(option)}
                                                >
                                                    {toClock(option)}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Length
                                        <select
                                            className="h-11 rounded-lg border border-slate-300 px-3 text-base"
                                            value={
                                                durationValid
                                                    ? form.data.duration
                                                    : String(durations[0] ?? '')
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'duration',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            {durations.map((option) => (
                                                <option
                                                    key={option}
                                                    value={option}
                                                >
                                                    {durationLabel(option)}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </div>
                                <FieldError
                                    message={
                                        errors.start_time ??
                                        errors.starts_at ??
                                        errors.date ??
                                        errors.ends_at ??
                                        errors.duration
                                    }
                                />

                                <Field
                                    label="Meeting title"
                                    error={form.errors.title}
                                >
                                    <input
                                        className="h-11 w-full rounded-lg border border-slate-300 px-3 text-base"
                                        value={form.data.title}
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        maxLength={160}
                                    />
                                </Field>
                                <Field
                                    label="Your name"
                                    error={form.errors.organizer_name}
                                >
                                    <input
                                        className="h-11 w-full rounded-lg border border-slate-300 px-3 text-base"
                                        value={form.data.organizer_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'organizer_name',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="name"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Email"
                                    error={form.errors.organizer_email}
                                >
                                    <input
                                        type="email"
                                        className="h-11 w-full rounded-lg border border-slate-300 px-3 text-base"
                                        value={form.data.organizer_email}
                                        onChange={(event) =>
                                            form.setData(
                                                'organizer_email',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="email"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="People attending (optional)"
                                    error={form.errors.attendees}
                                >
                                    <input
                                        type="number"
                                        min={1}
                                        max={room.capacity ?? undefined}
                                        className="h-11 w-32 rounded-lg border border-slate-300 px-3 text-base"
                                        value={form.data.attendees}
                                        onChange={(event) =>
                                            form.setData(
                                                'attendees',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Notes (optional)"
                                    error={form.errors.notes}
                                >
                                    <textarea
                                        rows={3}
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                {/* Honeypot, hidden from people and assistive tech. */}
                                <input
                                    type="text"
                                    name="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    aria-hidden
                                    className="absolute -left-[9999px] h-0 w-0 opacity-0"
                                    value={form.data.website}
                                    onChange={(event) =>
                                        form.setData(
                                            'website',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError
                                    message={
                                        errors.meeting_room_id ??
                                        errors.website ??
                                        errors.attendees
                                    }
                                />

                                <button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        durations.length === 0
                                    }
                                    className="flex h-12 w-full items-center justify-center gap-2 rounded-xl text-base font-semibold text-white shadow-sm disabled:opacity-60"
                                    style={{ background: room.color }}
                                >
                                    <CalendarCheck className="size-5" />
                                    {form.processing
                                        ? 'Booking…'
                                        : room.requires_approval
                                          ? 'Request this room'
                                          : 'Book this room'}
                                </button>
                                <p className="text-center text-xs text-slate-500">
                                    Times shown in {room.timezone}.
                                </p>
                            </form>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            {children}
            <FieldError message={error} />
        </label>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <span className="text-sm font-normal text-rose-600">{message}</span>
    ) : null;
}
