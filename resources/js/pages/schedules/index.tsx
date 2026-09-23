import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Globe,
    Monitor,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { destroy, index, store, update } from '@/routes/schedules';
import type {
    ResolvedPlayback,
    ScheduleOccurrence,
    SchedulePermissions,
    ScheduleRecord,
    ScheduleTargetRecord,
} from '@/types';

type Option = { value: string; label: string };
type NamedOption = { id: number; name: string };
type ScreenOption = { id: number; name: string; timezone: string };
type LocationOption = { id: number; name: string; depth: number };
type WeekDay = { date: string; label: string; is_today: boolean };

type Props = {
    schedules: ScheduleRecord[];
    occurrences: ScheduleOccurrence[];
    week: {
        start: string;
        end: string;
        previous: string;
        next: string;
        days: WeekDay[];
    };
    filters: {
        week: string;
        screen_id: number | null;
        timezone: string;
    };
    resolved: ResolvedPlayback | null;
    channels: NamedOption[];
    playlists: NamedOption[];
    screens: ScreenOption[];
    groups: NamedOption[];
    locations: LocationOption[];
    timezones: Option[];
    recurrences: Option[];
    contentTypes: Option[];
    targetTypes: Option[];
    permissions: SchedulePermissions;
};

const HOUR_HEIGHT = 40;
const WEEKDAYS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const COLORS = [
    'bg-blue-600',
    'bg-slate-700',
    'bg-blue-800',
    'bg-indigo-800',
];

function emptyForm(timezone: string): Omit<ScheduleRecord, 'id' | 'content_type_label' | 'recurrence_label' | 'channel_name' | 'playlist_name'> {
    return {
        name: '',
        description: null,
        content_type: 'channel',
        channel_id: null,
        playlist_id: null,
        timezone,
        starts_on: new Date().toISOString().slice(0, 10),
        ends_on: '',
        start_time: '06:00',
        end_time: '11:00',
        recurrence: 'daily',
        weekdays: [1, 2, 3, 4, 5],
        priority: 100,
        is_enabled: true,
        targets: [],
    };
}

function colorFor(id: number): string {
    return COLORS[id % COLORS.length];
}

function parseIsoDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function formatWeekRange(start: string, end: string): string {
    const from = parseIsoDate(start);
    const to = parseIsoDate(end);
    const fromMonth = from.toLocaleDateString('en-US', { month: 'short' });
    const toMonth = to.toLocaleDateString('en-US', { month: 'short' });

    return `${fromMonth} ${from.getDate()} – ${toMonth} ${to.getDate()}, ${to.getFullYear()}`;
}

function formatHour(hour: number): string {
    return `${String(hour).padStart(2, '0')}:00`;
}

function formatClock(minutes: number): string {
    return formatHour(Math.floor(minutes / 60));
}

export default function SchedulesIndex({
    schedules,
    occurrences,
    week,
    filters,
    resolved,
    channels,
    playlists,
    screens,
    groups,
    locations,
    timezones,
    recurrences,
    contentTypes,
    targetTypes,
    permissions,
}: Props) {
    const { currentTeam, errors } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const formErrors = (errors ?? {}) as Record<string, string>;
    const [editing, setEditing] = useState<ScheduleRecord | 'new' | null>(null);
    const [deleting, setDeleting] = useState<ScheduleRecord | null>(null);
    const [form, setForm] = useState(() => emptyForm(filters.timezone));
    const [targetDraft, setTargetDraft] = useState({
        target_type: 'screen' as ScheduleTargetRecord['target_type'],
        id: '',
    });

    const hours = useMemo(
        () => Array.from({ length: 24 }, (_, hour) => hour),
        [],
    );

    const eventsByDate = useMemo(() => {
        const grouped: Record<string, ScheduleOccurrence[]> = {};

        for (const occurrence of occurrences) {
            grouped[occurrence.date] ??= [];
            grouped[occurrence.date].push(occurrence);
        }

        return grouped;
    }, [occurrences]);

    const applyFilters = (next: Partial<typeof filters>) => {
        const screenId = 'screen_id' in next ? next.screen_id : filters.screen_id;

        router.get(
            index.url(slug),
            {
                week: next.week ?? filters.week,
                screen_id: screenId ?? '',
                timezone: next.timezone ?? filters.timezone,
            },
            { preserveState: true, replace: true },
        );
    };

    const openNew = () => {
        setForm(emptyForm(filters.timezone));
        setEditing('new');
    };

    const openEdit = (schedule: ScheduleRecord) => {
        setForm({
            name: schedule.name,
            description: schedule.description,
            content_type: schedule.content_type,
            channel_id: schedule.channel_id,
            playlist_id: schedule.playlist_id,
            timezone: schedule.timezone,
            starts_on: schedule.starts_on,
            ends_on: schedule.ends_on ?? '',
            start_time: schedule.start_time,
            end_time: schedule.end_time,
            recurrence: schedule.recurrence,
            weekdays: schedule.weekdays,
            priority: schedule.priority,
            is_enabled: schedule.is_enabled,
            targets: schedule.targets,
        });
        setEditing(schedule);
    };

    const addTarget = () => {
        const id = Number(targetDraft.id);

        if (! id) {
            return;
        }

        const next: ScheduleTargetRecord = {
            target_type: targetDraft.target_type,
            screen_id: targetDraft.target_type === 'screen' ? id : null,
            screen_group_id:
                targetDraft.target_type === 'screen_group' ? id : null,
            location_id: targetDraft.target_type === 'location' ? id : null,
            label:
                targetDraft.target_type === 'screen'
                    ? screens.find((item) => item.id === id)?.name
                    : targetDraft.target_type === 'screen_group'
                      ? groups.find((item) => item.id === id)?.name
                      : locations.find((item) => item.id === id)?.name,
        };

        setForm((current) => ({
            ...current,
            targets: [...current.targets, next],
        }));
        setTargetDraft({ ...targetDraft, id: '' });
    };

    const payload = () => ({
        name: form.name,
        description: form.description,
        content_type: form.content_type,
        channel_id: form.content_type === 'channel' ? form.channel_id : null,
        playlist_id: form.content_type === 'playlist' ? form.playlist_id : null,
        timezone: form.timezone,
        starts_on: form.starts_on,
        ends_on: form.ends_on === '' ? null : form.ends_on,
        start_time: form.start_time,
        end_time: form.end_time,
        recurrence: form.recurrence,
        weekdays: form.weekdays,
        priority: form.priority,
        is_enabled: form.is_enabled,
        targets: form.targets.map((target) => ({
            target_type: target.target_type,
            screen_id: target.screen_id,
            screen_group_id: target.screen_group_id,
            location_id: target.location_id,
        })),
    });

    const stayOnPage = () => ({
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    });

    const save = () => {
        if (editing === null) {
            return;
        }

        if (editing === 'new') {
            router.post(store.url(slug), payload(), {
                ...stayOnPage(),
                onSuccess: () => setEditing(null),
            });

            return;
        }

        router.patch(
            update.url({ current_team: slug, schedule: editing.id }),
            payload(),
            {
                ...stayOnPage(),
                onSuccess: () => setEditing(null),
            },
        );
    };

    const targetOptions =
        targetDraft.target_type === 'screen'
            ? screens
            : targetDraft.target_type === 'screen_group'
              ? groups
              : locations;

    const weekLabel = formatWeekRange(week.start, week.end);

    return (
        <>
            <Head title="Schedule" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            Dayparts and windows
                        </p>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Schedule
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed">
                            Assign channels and playlists to screens, groups, or
                            locations in time windows. Higher priority wins when
                            windows overlap.
                        </p>
                    </div>
                    {permissions.canCreateSchedule && (
                        <Button onClick={openNew} data-test="create-schedule">
                            <CalendarClock className="size-4" />
                            New schedule
                        </Button>
                    )}
                </div>

                <div className="dashboard-card flex flex-wrap items-center gap-3 px-4 py-3">
                    <div
                        className="inline-flex overflow-hidden rounded-lg border"
                        role="group"
                        aria-label="Week"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 rounded-none border-r px-3"
                            onClick={() =>
                                applyFilters({ week: week.previous })
                            }
                        >
                            <ChevronLeft className="size-4" />
                            Previous
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 rounded-none px-3"
                            onClick={() => applyFilters({ week: week.next })}
                        >
                            Next
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    <p className="min-w-48 text-sm font-semibold tracking-tight">
                        {weekLabel}
                    </p>
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        <Select
                            value={
                                filters.screen_id
                                    ? String(filters.screen_id)
                                    : 'all'
                            }
                            onValueChange={(value) => {
                                if (value === 'all') {
                                    applyFilters({ screen_id: null });

                                    return;
                                }

                                const screen = screens.find(
                                    (item) => item.id === Number(value),
                                );

                                applyFilters({
                                    screen_id: Number(value),
                                    timezone:
                                        screen?.timezone ?? filters.timezone,
                                });
                            }}
                        >
                            <SelectTrigger
                                className="w-[180px]"
                                aria-label="Screen"
                            >
                                <Monitor className="text-muted-foreground size-3.5" />
                                <SelectValue placeholder="All screens" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All screens</SelectItem>
                                {screens.map((screen) => (
                                    <SelectItem
                                        key={screen.id}
                                        value={String(screen.id)}
                                    >
                                        {screen.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.timezone}
                            onValueChange={(value) =>
                                applyFilters({ timezone: value })
                            }
                        >
                            <SelectTrigger
                                className="w-[200px]"
                                aria-label="Timezone"
                            >
                                <Globe className="text-muted-foreground size-3.5" />
                                <SelectValue placeholder="Timezone" />
                            </SelectTrigger>
                            <SelectContent>
                                {timezones.map((timezone) => (
                                    <SelectItem
                                        key={timezone.value}
                                        value={timezone.value}
                                    >
                                        {timezone.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {resolved && (
                            <Badge variant="secondary">
                                Now:{' '}
                                {resolved.schedule_name ??
                                    'Assigned channel fallback'}
                                {' · '}
                                {resolved.timezone}
                            </Badge>
                        )}
                    </div>
                </div>

                {schedules.length === 0 ? (
                    <section className="dashboard-card">
                        <div className="flex flex-col items-center px-6 py-16 text-center">
                            <div className="mb-5 flex size-16 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950">
                                <CalendarClock
                                    className="size-7"
                                    strokeWidth={1.5}
                                />
                            </div>
                            <h2 className="text-base font-semibold">
                                No schedules yet
                            </h2>
                            <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">
                                Create breakfast, lunch, and dinner windows — or
                                any other daypart — and assign them to screens.
                            </p>
                            {permissions.canCreateSchedule && (
                                <Button className="mt-6" onClick={openNew}>
                                    <CalendarClock className="size-4" />
                                    New schedule
                                </Button>
                            )}
                        </div>
                    </section>
                ) : (
                    <>
                        <section className="dashboard-card overflow-hidden">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                                <div>
                                    <h2 className="font-semibold">
                                        Week calendar
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {occurrences.length === 0
                                            ? 'No windows fall in this week for the selected screen.'
                                            : `${occurrences.length} window${occurrences.length === 1 ? '' : 's'} this week. Click a block to edit.`}
                                    </p>
                                </div>
                            </div>
                            <div className="overflow-auto">
                                <div
                                    className="grid min-w-[960px]"
                                    style={{
                                        gridTemplateColumns:
                                            '64px repeat(7, minmax(0, 1fr))',
                                    }}
                                >
                                    <div className="bg-muted/20 sticky top-0 z-10 border-b" />
                                    {week.days.map((day) => {
                                        const parsed = parseIsoDate(day.date);

                                        return (
                                            <div
                                                key={day.date}
                                                className={`sticky top-0 z-10 border-b border-l px-2 py-2 text-center ${
                                                    day.is_today
                                                        ? 'bg-blue-50 dark:bg-blue-950/40'
                                                        : 'bg-muted/20'
                                                }`}
                                            >
                                                <p
                                                    className={`text-[11px] font-medium tracking-wide uppercase ${
                                                        day.is_today
                                                            ? 'text-blue-600'
                                                            : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {parsed.toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            weekday: 'short',
                                                        },
                                                    )}
                                                </p>
                                                <p
                                                    className={`text-sm font-semibold tabular-nums ${
                                                        day.is_today
                                                            ? 'text-blue-600'
                                                            : ''
                                                    }`}
                                                >
                                                    {parsed.getDate()}
                                                </p>
                                            </div>
                                        );
                                    })}
                                    <div className="relative">
                                        {hours.map((hour) => (
                                            <div
                                                key={hour}
                                                className="text-muted-foreground border-t px-2 pt-0.5 text-[11px] tabular-nums"
                                                style={{
                                                    height: HOUR_HEIGHT,
                                                }}
                                            >
                                                {formatHour(hour)}
                                            </div>
                                        ))}
                                    </div>
                                    {week.days.map((day) => (
                                        <div
                                            key={day.date}
                                            className={`relative border-l ${
                                                day.is_today
                                                    ? 'bg-blue-50/40 dark:bg-blue-950/20'
                                                    : ''
                                            }`}
                                            style={{
                                                height: HOUR_HEIGHT * 24,
                                            }}
                                        >
                                            {hours.map((hour) => (
                                                <div
                                                    key={hour}
                                                    className="border-t"
                                                    style={{
                                                        height: HOUR_HEIGHT,
                                                    }}
                                                />
                                            ))}
                                            {(eventsByDate[day.date] ?? []).map(
                                                (event) => (
                                                    <button
                                                        key={`${event.schedule_id}-${event.start_minutes}`}
                                                        type="button"
                                                        className={`absolute inset-x-1 overflow-hidden rounded-md px-1.5 py-1 text-left text-xs text-white ${colorFor(event.schedule_id)} ${event.is_enabled ? '' : 'opacity-50'}`}
                                                        style={{
                                                            top:
                                                                (event.start_minutes /
                                                                    60) *
                                                                HOUR_HEIGHT,
                                                            height: Math.max(
                                                                22,
                                                                ((event.end_minutes -
                                                                    event.start_minutes) /
                                                                    60) *
                                                                    HOUR_HEIGHT,
                                                            ),
                                                        }}
                                                        onClick={() => {
                                                            const schedule =
                                                                schedules.find(
                                                                    (item) =>
                                                                        item.id ===
                                                                        event.schedule_id,
                                                                );

                                                            if (schedule) {
                                                                openEdit(
                                                                    schedule,
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <span className="block font-medium">
                                                            {event.name}
                                                        </span>
                                                        <span className="block opacity-80">
                                                            {formatClock(
                                                                event.start_minutes,
                                                            )}
                                                            –
                                                            {formatClock(
                                                                event.end_minutes,
                                                            )}
                                                        </span>
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section className="dashboard-card overflow-hidden">
                            <div className="border-b px-5 py-4">
                                <h2 className="font-semibold">Dayparts</h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Recurrence, priority, and targets for each
                                    window.
                                </p>
                            </div>
                            <div className="divide-y">
                                {schedules.map((schedule) => (
                                    <div
                                        key={schedule.id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                                    >
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-medium">
                                                    {schedule.name}
                                                </h3>
                                                <Badge variant="secondary">
                                                    {schedule.start_time}–
                                                    {schedule.end_time}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {schedule.recurrence_label}
                                                </Badge>
                                                <Badge variant="outline">
                                                    Priority {schedule.priority}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        schedule.is_enabled
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {schedule.is_enabled
                                                        ? 'On'
                                                        : 'Off'}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {schedule.channel_name ??
                                                    schedule.playlist_name}{' '}
                                                ·{' '}
                                                {schedule.targets
                                                    .map(
                                                        (target) =>
                                                            target.label,
                                                    )
                                                    .join(', ') ||
                                                    'No targets'}{' '}
                                                · {schedule.timezone}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            {permissions.canUpdateSchedule && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        openEdit(schedule)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                            )}
                                            {permissions.canDeleteSchedule && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        setDeleting(schedule)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <div className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {editing === 'new'
                                    ? 'New schedule'
                                    : 'Edit schedule'}
                            </DialogTitle>
                        </DialogHeader>
                        <ScheduleFields
                            form={form}
                            setForm={setForm}
                            errors={formErrors}
                            channels={channels}
                            playlists={playlists}
                            timezones={timezones}
                            recurrences={recurrences}
                            contentTypes={contentTypes}
                            targetTypes={targetTypes}
                            targetDraft={targetDraft}
                            setTargetDraft={setTargetDraft}
                            targetOptions={targetOptions}
                            addTarget={addTarget}
                        />
                        <DialogFooter>
                            <Button onClick={save} data-test="save-schedule">
                                {editing === 'new' ? 'Create' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete schedule?"
                description="Screens will fall back to their assigned channel when this window no longer applies."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            schedule: deleting.id,
                        }),
                        stayOnPage(),
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />
        </>
    );
}

function ScheduleFields({
    form,
    setForm,
    errors,
    channels,
    playlists,
    timezones,
    recurrences,
    contentTypes,
    targetTypes,
    targetDraft,
    setTargetDraft,
    targetOptions,
    addTarget,
}: {
    form: ReturnType<typeof emptyForm>;
    setForm: React.Dispatch<React.SetStateAction<ReturnType<typeof emptyForm>>>;
    errors: Record<string, string>;
    channels: NamedOption[];
    playlists: NamedOption[];
    timezones: Option[];
    recurrences: Option[];
    contentTypes: Option[];
    targetTypes: Option[];
    targetDraft: { target_type: ScheduleTargetRecord['target_type']; id: string };
    setTargetDraft: React.Dispatch<
        React.SetStateAction<{
            target_type: ScheduleTargetRecord['target_type'];
            id: string;
        }>
    >;
    targetOptions: { id: number; name: string }[];
    addTarget: () => void;
}) {
    return (
        <>
            <div className="space-y-2">
                <Label htmlFor="schedule-name">Name</Label>
                <Input
                    id="schedule-name"
                    name="name"
                    value={form.name}
                    onChange={(event) =>
                        setForm((current) => ({
                            ...current,
                            name: event.target.value,
                        }))
                    }
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                    <Label>Content</Label>
                    <select
                        name="content_type"
                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        value={form.content_type}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                content_type: event.target
                                    .value as typeof current.content_type,
                            }))
                        }
                    >
                        {contentTypes.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="space-y-2">
                    {form.content_type === 'channel' ? (
                        <>
                            <Label>Channel</Label>
                            <select
                                name="channel_id"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.channel_id ?? ''}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        channel_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    }))
                                }
                            >
                                <option value="">Select</option>
                                {channels.map((channel) => (
                                    <option key={channel.id} value={channel.id}>
                                        {channel.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.channel_id} />
                        </>
                    ) : (
                        <>
                            <Label>Playlist</Label>
                            <select
                                name="playlist_id"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.playlist_id ?? ''}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        playlist_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    }))
                                }
                            >
                                <option value="">Select</option>
                                {playlists.map((playlist) => (
                                    <option
                                        key={playlist.id}
                                        value={playlist.id}
                                    >
                                        {playlist.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.playlist_id} />
                        </>
                    )}
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                    <Label>Start date</Label>
                    <Input
                        type="date"
                        name="starts_on"
                        value={form.starts_on}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                starts_on: event.target.value,
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label>End date</Label>
                    <Input
                        type="date"
                        name="ends_on"
                        value={form.ends_on ?? ''}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                ends_on: event.target.value,
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label>Start time</Label>
                    <Input
                        type="time"
                        name="start_time"
                        value={form.start_time}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                start_time: event.target.value,
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label>End time</Label>
                    <Input
                        type="time"
                        name="end_time"
                        value={form.end_time}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                end_time: event.target.value,
                            }))
                        }
                    />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                    <Label>Recurrence</Label>
                    <select
                        name="recurrence"
                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        value={form.recurrence}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                recurrence: event.target
                                    .value as typeof current.recurrence,
                            }))
                        }
                    >
                        {recurrences.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="space-y-2">
                    <Label>Priority</Label>
                    <Input
                        type="number"
                        name="priority"
                        min={1}
                        max={1000}
                        value={form.priority}
                        onChange={(event) =>
                            setForm((current) => ({
                                ...current,
                                priority: Number(event.target.value),
                            }))
                        }
                    />
                    <InputError message={errors.priority} />
                </div>
            </div>
            {form.recurrence === 'weekly' && (
                <div className="flex flex-wrap gap-1">
                    {WEEKDAYS.map((day) => {
                        const selected = form.weekdays.includes(day.value);

                        return (
                            <Button
                                key={day.value}
                                type="button"
                                size="sm"
                                variant={selected ? 'default' : 'outline'}
                                onClick={() =>
                                    setForm((current) => ({
                                        ...current,
                                        weekdays: selected
                                            ? current.weekdays.filter(
                                                  (value) => value !== day.value,
                                              )
                                            : [...current.weekdays, day.value],
                                    }))
                                }
                            >
                                {day.label}
                            </Button>
                        );
                    })}
                </div>
            )}
            <div className="space-y-2">
                <Label>Timezone fallback</Label>
                <select
                    name="timezone"
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                    value={form.timezone}
                    onChange={(event) =>
                        setForm((current) => ({
                            ...current,
                            timezone: event.target.value,
                        }))
                    }
                >
                    {timezones.map((timezone) => (
                        <option key={timezone.value} value={timezone.value}>
                            {timezone.label}
                        </option>
                    ))}
                </select>
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.is_enabled}
                    onChange={(event) =>
                        setForm((current) => ({
                            ...current,
                            is_enabled: event.target.checked,
                        }))
                    }
                />
                Enabled
            </label>
            <div className="space-y-2">
                <Label>Targets</Label>
                <div className="flex gap-2">
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={targetDraft.target_type}
                        onChange={(event) =>
                            setTargetDraft({
                                target_type: event.target
                                    .value as ScheduleTargetRecord['target_type'],
                                id: '',
                            })
                        }
                    >
                        {targetTypes.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-9 min-w-0 flex-1 rounded-md border px-3 text-sm"
                        value={targetDraft.id}
                        onChange={(event) =>
                            setTargetDraft((current) => ({
                                ...current,
                                id: event.target.value,
                            }))
                        }
                    >
                        <option value="">Select</option>
                        {targetOptions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {'depth' in item && typeof item.depth === 'number'
                                    ? `${'— '.repeat(item.depth)}${item.name}`
                                    : item.name}
                            </option>
                        ))}
                    </select>
                    <Button type="button" variant="outline" onClick={addTarget}>
                        Add
                    </Button>
                </div>
                <InputError message={errors.targets} />
                <div className="flex flex-wrap gap-1">
                    {form.targets.map((target, index) => (
                        <Badge key={`${target.label}-${index}`} variant="secondary">
                            {target.label}
                            <button
                                type="button"
                                className="ml-1"
                                onClick={() =>
                                    setForm((current) => ({
                                        ...current,
                                        targets: current.targets.filter(
                                            (_, currentIndex) =>
                                                currentIndex !== index,
                                        ),
                                    }))
                                }
                            >
                                ×
                            </button>
                        </Badge>
                    ))}
                </div>
            </div>
        </>
    );
}

SchedulesIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Schedule',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
