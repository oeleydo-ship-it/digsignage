import { Link, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    ExternalLink,
    LayoutTemplate,
    Monitor,
    Radio,
    Rocket,
    Ticket,
    Wifi,
    WifiOff,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    QueuePageShell,
    queueBreadcrumbs,
} from '@/pages/queue/queue-page-shell';

type Screen = {
    id: number;
    name: string;
    location_name: string | null;
    status: string;
    status_label: string;
    paired: boolean;
    last_seen_at: string | null;
    current_channel_id: number | null;
    current_channel: string | null;
    current_content: string | null;
};
type NowServing = {
    id: number;
    number: string;
    service_name: string | null;
    counter_name: string | null;
    status: string;
};
type Preset = { key: string; name: string; description: string };
type Board = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    width: number;
    height: number;
    updated_at: string | null;
};
type Option = { id: number; name: string; code?: string };
type Props = {
    screens: Screen[];
    nowServing: NowServing[];
    waitingCount: number;
    presets: Preset[];
    queueBoards: Board[];
    services: Option[];
    locations: Option[];
    counters: Option[];
    displayPermissions: {
        canCreateBoard: boolean;
        canDeploy: boolean;
    };
};

function formatSeen(value: string | null) {
    if (!value) return 'Never connected';
    return (
        'Last seen ' +
        new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value))
    );
}

export default function QueueDisplays({
    screens,
    nowServing,
    waitingCount,
    presets,
    queueBoards,
    services,
    locations,
    counters,
    displayPermissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const online = screens.filter(
        (screen) => screen.status === 'online',
    ).length;
    const screensUrl = `/${slug}/screens`;
    const publishedBoards = queueBoards.filter(
        (board) => board.status === 'published',
    );
    const createBoard = useForm({
        preset: presets[0]?.key ?? 'classic',
        name: '',
        service_id: '',
        location_id: '',
        counter_id: '',
    });
    const deploy = useForm<{
        design_id: string;
        screen_ids: number[];
    }>({
        design_id: publishedBoards[0]?.id.toString() ?? '',
        screen_ids: [],
    });

    const toggleScreen = (id: number) => {
        deploy.setData(
            'screen_ids',
            deploy.data.screen_ids.includes(id)
                ? deploy.data.screen_ids.filter((screenId) => screenId !== id)
                : [...deploy.data.screen_ids, id],
        );
    };

    return (
        <QueuePageShell
            title="Displays"
            description="Build queue boards in the Designer and deploy them through the existing DigSignage player."
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat
                    label="Registered displays"
                    value={screens.length}
                    icon={Monitor}
                />
                <Stat label="Online now" value={online} icon={Wifi} />
                <Stat
                    label="Waiting tickets"
                    value={waitingCount}
                    icon={Ticket}
                />
            </div>

            <section className="dashboard-card overflow-hidden">
                <div className="border-b px-5 py-4">
                    <div className="flex items-center gap-2">
                        <LayoutTemplate className="size-4 text-blue-600" />
                        <h2 className="font-semibold">Create a queue board</h2>
                    </div>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Start with a 1920 × 1080 layout, then customize queue,
                        media, news, weather, clock, and announcement elements
                        in the Designer.
                    </p>
                </div>
                <form
                    className="space-y-5 p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        createBoard.post(`/${slug}/queue/displays/boards`);
                    }}
                >
                    <div className="grid gap-3 lg:grid-cols-3">
                        {presets.map((preset) => {
                            const selected =
                                createBoard.data.preset === preset.key;

                            return (
                                <button
                                    key={preset.key}
                                    type="button"
                                    className={`rounded-xl border p-4 text-left transition ${
                                        selected
                                            ? 'border-blue-500 bg-blue-500/5 ring-1 ring-blue-500'
                                            : 'hover:bg-muted/40'
                                    }`}
                                    onClick={() =>
                                        createBoard.setData(
                                            'preset',
                                            preset.key,
                                        )
                                    }
                                >
                                    <p className="font-medium">{preset.name}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {preset.description}
                                    </p>
                                </button>
                            );
                        })}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Field label="Board name">
                            <Input
                                value={createBoard.data.name}
                                placeholder="Use preset name"
                                onChange={(event) =>
                                    createBoard.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <SelectField
                            label="Service"
                            allLabel="All services"
                            value={createBoard.data.service_id}
                            options={services}
                            onChange={(value) =>
                                createBoard.setData('service_id', value)
                            }
                        />
                        <SelectField
                            label="Location"
                            allLabel="All locations"
                            value={createBoard.data.location_id}
                            options={locations}
                            onChange={(value) =>
                                createBoard.setData('location_id', value)
                            }
                        />
                        <SelectField
                            label="Counter"
                            allLabel="All counters"
                            value={createBoard.data.counter_id}
                            options={counters}
                            onChange={(value) =>
                                createBoard.setData('counter_id', value)
                            }
                        />
                    </div>
                    {Object.values(createBoard.errors).length > 0 && (
                        <p className="text-destructive text-sm">
                            {Object.values(createBoard.errors)[0]}
                        </p>
                    )}
                    <Button
                        type="submit"
                        disabled={
                            !displayPermissions.canCreateBoard ||
                            createBoard.processing
                        }
                    >
                        <LayoutTemplate className="size-4" />
                        Create and open Designer
                    </Button>
                </form>
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <section className="dashboard-card overflow-hidden">
                    <div className="flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <h2 className="font-semibold">
                                Deploy a published board
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Deployment creates a standard playlist and
                                channel, then assigns it to the selected
                                screens.
                            </p>
                        </div>
                        <Rocket className="size-4 text-blue-600" />
                    </div>
                    <form
                        className="space-y-4 p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            deploy.post(`/${slug}/queue/displays/deploy`, {
                                preserveScroll: true,
                                onSuccess: () =>
                                    deploy.setData('screen_ids', []),
                            });
                        }}
                    >
                        <Field label="Queue board">
                            <select
                                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                                value={deploy.data.design_id}
                                onChange={(event) =>
                                    deploy.setData(
                                        'design_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    Choose a published board
                                </option>
                                {publishedBoards.map((board) => (
                                    <option key={board.id} value={board.id}>
                                        {board.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <div>
                            <Label>Target screens</Label>
                            <div className="mt-2 max-h-64 divide-y overflow-auto rounded-lg border">
                                {screens.length === 0 ? (
                                    <p className="text-muted-foreground p-4 text-sm">
                                        Register a screen before deployment.
                                    </p>
                                ) : (
                                    screens.map((screen) => (
                                        <label
                                            key={screen.id}
                                            className="hover:bg-muted/40 flex cursor-pointer items-center gap-3 px-3 py-3"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={deploy.data.screen_ids.includes(
                                                    screen.id,
                                                )}
                                                onChange={() =>
                                                    toggleScreen(screen.id)
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">
                                                    {screen.name}
                                                </span>
                                                <span className="text-muted-foreground block truncate text-xs">
                                                    {screen.current_channel ??
                                                        'No channel assigned'}
                                                </span>
                                            </span>
                                            <Badge variant="secondary">
                                                {screen.status_label}
                                            </Badge>
                                        </label>
                                    ))
                                )}
                            </div>
                        </div>
                        {Object.values(deploy.errors).length > 0 && (
                            <p className="text-destructive text-sm">
                                {Object.values(deploy.errors)[0]}
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={
                                !displayPermissions.canDeploy ||
                                deploy.processing ||
                                !deploy.data.design_id ||
                                deploy.data.screen_ids.length === 0
                            }
                        >
                            <Rocket className="size-4" />
                            Deploy to {deploy.data.screen_ids.length} screen(s)
                        </Button>
                        {publishedBoards.length === 0 && (
                            <p className="text-muted-foreground text-xs">
                                Create a preset, customize it, and publish it
                                from the Designer before deployment.
                            </p>
                        )}
                    </form>
                </section>

                <section className="dashboard-card overflow-hidden">
                    <div className="border-b px-5 py-4">
                        <div className="flex items-center gap-2">
                            <Radio className="size-4 text-blue-600" />
                            <h2 className="font-semibold">
                                Live board preview
                            </h2>
                        </div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Current queue information from the same source used
                            by Designer widgets.
                        </p>
                    </div>
                    <div className="bg-slate-950 p-5 text-white">
                        <p className="text-center text-xs font-semibold tracking-[0.2em] text-blue-300 uppercase">
                            Now serving
                        </p>
                        {nowServing.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No tickets are being served.
                            </p>
                        ) : (
                            <div className="mt-4 divide-y divide-slate-800">
                                {nowServing.slice(0, 5).map((ticket) => (
                                    <div
                                        key={ticket.id}
                                        className="flex items-center justify-between gap-3 py-3"
                                    >
                                        <span className="text-2xl font-bold tracking-wide">
                                            {ticket.number}
                                        </span>
                                        <span className="min-w-0 flex-1 truncate text-right text-sm text-slate-300">
                                            {ticket.service_name ?? 'Queue'}
                                        </span>
                                        <span className="rounded bg-blue-600 px-2 py-1 text-xs font-semibold">
                                            {ticket.counter_name ?? '—'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="flex items-center justify-between px-5 py-4 text-sm">
                        <span className="text-muted-foreground">
                            Waiting in queue
                        </span>
                        <span className="font-semibold tabular-nums">
                            {waitingCount}
                        </span>
                    </div>
                </section>
            </div>

            <section className="dashboard-card overflow-hidden">
                <div className="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 className="font-semibold">Queue board designs</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Boards remain ordinary Designer content and can be
                            mixed with all existing elements.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/${slug}/designs`}>
                            All designs <ExternalLink className="size-3.5" />
                        </Link>
                    </Button>
                </div>
                {queueBoards.length === 0 ? (
                    <EmptyState
                        title="No queue boards yet"
                        description="Choose a preset above to create your first editable queue board."
                    />
                ) : (
                    <div className="divide-y">
                        {queueBoards.map((board) => (
                            <div
                                key={board.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                            >
                                <div>
                                    <p className="font-medium">{board.name}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {board.width} × {board.height}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            board.status === 'published'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {board.status_label}
                                    </Badge>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={`/${slug}/designs/${board.id}/edit`}
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={`/${slug}/designs/${board.id}`}
                                        >
                                            Preview
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="dashboard-card overflow-hidden">
                <div className="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 className="font-semibold">Registered displays</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Current connectivity and assigned playback channel.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={screensUrl}>
                            Manage screens <ExternalLink className="size-3.5" />
                        </Link>
                    </Button>
                </div>
                {screens.length === 0 ? (
                    <EmptyState
                        title="No displays registered"
                        description="Pair a DigSignage player first, then return here to deploy a queue board."
                        action={
                            <Button asChild>
                                <Link href={screensUrl}>
                                    Register a display
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="divide-y">
                        {screens.map((screen) => {
                            const isOnline = screen.status === 'online';
                            return (
                                <div
                                    key={screen.id}
                                    className="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span
                                            className={
                                                isOnline
                                                    ? 'flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600'
                                                    : 'text-muted-foreground bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg'
                                            }
                                        >
                                            {isOnline ? (
                                                <Wifi className="size-4" />
                                            ) : (
                                                <WifiOff className="size-4" />
                                            )}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {screen.name}
                                            </p>
                                            <p className="text-muted-foreground truncate text-xs">
                                                {screen.location_name ??
                                                    'All locations'}{' '}
                                                ·{' '}
                                                {formatSeen(
                                                    screen.last_seen_at,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="text-right">
                                            <p className="text-muted-foreground text-xs">
                                                Currently playing
                                            </p>
                                            <p className="max-w-64 truncate text-sm">
                                                {screen.current_channel ??
                                                    screen.current_content ??
                                                    'No channel assigned'}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                isOnline
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {screen.paired
                                                ? screen.status_label
                                                : 'Not paired'}
                                        </Badge>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </section>
        </QueuePageShell>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function SelectField({
    label,
    allLabel,
    value,
    options,
    onChange,
}: {
    label: string;
    allLabel: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
}) {
    return (
        <Field label={label}>
            <select
                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">{allLabel}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name}
                        {option.code ? ` (${option.code})` : ''}
                    </option>
                ))}
            </select>
        </Field>
    );
}

function Stat({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: typeof Monitor;
}) {
    return (
        <div className="dashboard-card flex items-center gap-3 p-4">
            <span className="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600">
                <Icon className="size-4" />
            </span>
            <div>
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
            </div>
        </div>
    );
}

QueueDisplays.layout = queueBreadcrumbs('Displays', '/displays');
