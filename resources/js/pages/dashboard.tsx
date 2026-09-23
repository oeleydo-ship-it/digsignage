import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowDownToLine,
    ArrowRight,
    CalendarClock,
    ChevronRight,
    CirclePause,
    Images,
    LayoutTemplate,
    Monitor,
    Radio,
    Sparkles,
    Wifi,
    WifiOff,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import type {
    DashboardInvitation,
    MonitoringSummary,
    TeamPlanSummary,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    monitoring?: MonitoringSummary;
    plan?: TeamPlanSummary | null;
};

function money(cents: number | null): string {
    if (cents === null) {
        return 'Custom';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);
}

function meter(used: number, limit: number | null): string {
    if (limit === null) {
        return `${used.toLocaleString()} / unlimited`;
    }

    return `${used.toLocaleString()} / ${limit.toLocaleString()}`;
}
const emptyMonitoring: MonitoringSummary = {
    counts: { healthy: 0, warning: 0, offline: 0, disabled: 0 },
    screens: [],
    thresholds: null,
};

export default function Dashboard({
    pendingInvitations = [],
    monitoring = emptyMonitoring,
    plan = null,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const { currentTeam, auth, announcements } = usePage().props;
    const slug = currentTeam?.slug;
    const href = (path: string) => (slug ? `/${slug}/${path}` : '/');
    const firstName = auth.user.name.split(' ')[0];
    const total = Object.values(monitoring.counts).reduce(
        (sum, count) => sum + count,
        0,
    );
    const onlinePercent =
        total > 0 ? Math.round((monitoring.counts.healthy / total) * 100) : 0;
    const shortcuts = [
        {
            label: 'Media library',
            description: 'Upload and organize your assets',
            path: 'media',
            icon: Images,
            color: 'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300',
        },
        {
            label: 'Channels',
            description: 'Bring your content together',
            path: 'channels',
            icon: Radio,
            color: 'bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300',
        },
        {
            label: 'Schedules',
            description: 'The right content, at the right time',
            path: 'schedules',
            icon: CalendarClock,
            color: 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-7 p-4 sm:p-8 lg:p-10">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            Your workspace at a glance
                        </p>
                        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Welcome back, {firstName}
                            <span className="text-blue-600">.</span>
                        </h1>
                        <p className="text-muted-foreground mt-3 text-sm">
                            A little perspective on everything happening across
                            your screens.
                        </p>
                    </div>
                    <Link
                        href={href('monitoring')}
                        className="bg-card inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-medium shadow-xs"
                    >
                        <Activity className="size-3.5 text-blue-600" />
                        Screen monitoring
                        <ChevronRight className="text-muted-foreground size-3.5" />
                    </Link>
                </div>

                <nav
                    aria-label="Workspace sections"
                    className="flex gap-6 overflow-x-auto border-b text-sm sm:gap-8"
                >
                    <Link
                        href={slug ? dashboard(slug) : '/'}
                        aria-current="page"
                        className="flex shrink-0 items-center gap-2 border-b-2 border-blue-600 pb-3 font-semibold text-blue-600"
                    >
                        <Monitor className="size-4" />
                        Overview
                    </Link>
                    {[
                        ['Content library', 'media'],
                        ['Screens', 'screens'],
                        ['Schedule', 'schedules'],
                    ].map(([label, path]) => (
                        <Link
                            key={path}
                            href={href(path)}
                            className="text-muted-foreground hover:text-foreground shrink-0 border-b-2 border-transparent pb-3 transition-colors hover:border-blue-300"
                        >
                            {label}
                        </Link>
                    ))}
                </nav>

                {announcements.map((item) => (
                    <div
                        key={item.id}
                        className="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40"
                    >
                        <h2 className="font-semibold">{item.title}</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {item.body}
                        </p>
                    </div>
                ))}

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_310px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <section
                            aria-labelledby="network-heading"
                            className="dashboard-card"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
                                <h2
                                    id="network-heading"
                                    className="text-base font-semibold"
                                >
                                    Your screen network
                                </h2>
                                <span className="text-muted-foreground text-xs">
                                    {total} monitored{' '}
                                    {total === 1 ? 'device' : 'devices'}
                                </span>
                            </div>
                            <div className="grid grid-cols-2 gap-y-6 px-2 py-6 lg:grid-cols-4">
                                <StatCard
                                    label="Online"
                                    value={monitoring.counts.healthy}
                                    icon={Wifi}
                                    color="text-emerald-600 dark:text-emerald-400"
                                    background="bg-emerald-50 dark:bg-emerald-950"
                                    hint="Connected & healthy"
                                />
                                <StatCard
                                    label="Offline"
                                    value={monitoring.counts.offline}
                                    icon={WifiOff}
                                    color="text-rose-600 dark:text-rose-400"
                                    background="bg-rose-50 dark:bg-rose-950"
                                    hint="Not connected"
                                />
                                <StatCard
                                    label="Warning"
                                    value={monitoring.counts.warning}
                                    icon={TriangleAlert}
                                    color="text-amber-600 dark:text-amber-400"
                                    background="bg-amber-50 dark:bg-amber-950"
                                    hint="Needs attention"
                                />
                                <StatCard
                                    label="Disabled"
                                    value={monitoring.counts.disabled}
                                    icon={CirclePause}
                                    color="text-slate-500 dark:text-slate-300"
                                    background="bg-slate-100 dark:bg-slate-800"
                                    hint="Currently inactive"
                                />
                            </div>
                            <div className="bg-muted/20 flex flex-wrap items-center gap-3 border-t px-6 py-3.5">
                                <span className="text-muted-foreground text-xs">
                                    Network health
                                </span>
                                <div
                                    className="bg-muted h-1.5 min-w-16 flex-1 overflow-hidden rounded-full"
                                    role="progressbar"
                                    aria-label="Online devices"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={onlinePercent}
                                >
                                    <div
                                        className="h-full rounded-full bg-emerald-500"
                                        style={{ width: `${onlinePercent}%` }}
                                    />
                                </div>
                                <span className="text-xs font-medium">
                                    {total
                                        ? `${onlinePercent}% online`
                                        : 'Awaiting your first screen'}
                                </span>
                            </div>
                        </section>

                        <section
                            aria-labelledby="health-heading"
                            className="dashboard-card overflow-hidden"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b p-5 sm:px-6">
                                <div>
                                    <h2
                                        id="health-heading"
                                        className="font-semibold"
                                    >
                                        Screen health
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Connection status and the latest player
                                        activity.
                                    </p>
                                </div>
                                <Link
                                    href={href('monitoring')}
                                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400"
                                >
                                    View monitoring
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            </div>
                            {monitoring.screens.length === 0 ? (
                                <div className="flex flex-col items-center px-6 py-12 text-center">
                                    <div className="mb-5 flex size-16 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950">
                                        <Monitor
                                            className="size-7"
                                            strokeWidth={1.5}
                                        />
                                    </div>
                                    <h3 className="text-base font-semibold">
                                        Your next big thing starts on screen
                                    </h3>
                                    <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">
                                        Connect your first player to keep an eye
                                        on screen health, playback, and content
                                        in one place.
                                    </p>
                                    <Link
                                        href={href('screens')}
                                        className="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                                    >
                                        <Monitor className="size-4" />
                                        Connect a screen
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-muted/30 text-muted-foreground">
                                            <tr>
                                                {[
                                                    'Screen',
                                                    'Status',
                                                    'Last seen',
                                                    'Channel',
                                                    'Content',
                                                    'Player',
                                                    'Errors',
                                                ].map((label) => (
                                                    <th
                                                        key={label}
                                                        className="px-5 py-3 font-medium whitespace-nowrap"
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {monitoring.screens.map(
                                                (screen) => (
                                                    <tr
                                                        key={screen.id}
                                                        className="hover:bg-muted/30 border-t transition-colors"
                                                    >
                                                        <td className="px-5 py-4 font-semibold whitespace-nowrap">
                                                            {screen.name}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1.5 font-medium whitespace-nowrap"
                                                            >
                                                                <span
                                                                    className={`size-1.5 rounded-full ${screen.status === 'online' ? 'bg-emerald-500' : screen.status === 'offline' ? 'bg-rose-500' : screen.status === 'warning' ? 'bg-amber-500' : 'bg-slate-400'}`}
                                                                />
                                                                {
                                                                    screen.status_label
                                                                }
                                                            </Badge>
                                                        </td>
                                                        <td className="text-muted-foreground px-5 py-4 whitespace-nowrap">
                                                            {screen.last_seen_at
                                                                ? new Date(
                                                                      screen.last_seen_at,
                                                                  ).toLocaleString()
                                                                : 'Never'}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            {screen.current_channel ??
                                                                '—'}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            {screen.current_content ??
                                                                '—'}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            {screen.app_version ??
                                                                '—'}
                                                        </td>
                                                        <td
                                                            className="text-destructive max-w-48 truncate px-5 py-4"
                                                            title={
                                                                screen.last_error ??
                                                                undefined
                                                            }
                                                        >
                                                            {screen.last_error ??
                                                                '—'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="text-muted-foreground flex items-center justify-between gap-3 border-t px-6 py-3.5 text-xs">
                                <span>Showing up to 8 screens</span>
                                <Link
                                    href={href('analytics')}
                                    className="hover:text-foreground"
                                >
                                    Explore analytics &rarr;
                                </Link>
                            </div>
                        </section>

                        <section aria-labelledby="workspace-heading">
                            <div className="mb-3 flex items-center gap-2">
                                <h2
                                    id="workspace-heading"
                                    className="text-sm font-semibold"
                                >
                                    Keep things moving
                                </h2>
                                <span className="text-muted-foreground text-xs">
                                    Your everyday essentials
                                </span>
                            </div>
                            <div className="grid gap-3 md:grid-cols-3">
                                {shortcuts.map((item) => (
                                    <Link
                                        key={item.path}
                                        href={href(item.path)}
                                        className="dashboard-card group p-4 transition-colors hover:border-blue-300"
                                    >
                                        <div className="mb-4 flex items-center justify-between">
                                            <span
                                                className={`flex size-9 items-center justify-center rounded-lg ${item.color}`}
                                            >
                                                <item.icon className="size-4" />
                                            </span>
                                            <ArrowRight className="text-muted-foreground size-4 transition-transform group-hover:translate-x-1" />
                                        </div>
                                        <h3 className="text-sm font-semibold">
                                            {item.label}
                                        </h3>
                                        <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                            {item.description}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    </div>

                    <aside className="grid gap-6 sm:grid-cols-2 xl:grid-cols-1">
                        {plan && (
                            <section
                                aria-labelledby="plan-heading"
                                className="dashboard-card p-5"
                            >
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.18em] uppercase">
                                    Current plan
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <h2
                                        id="plan-heading"
                                        className="text-xl font-semibold tracking-tight"
                                    >
                                        {plan.name}
                                    </h2>
                                    <span className="text-muted-foreground text-sm">
                                        {money(plan.price_cents)}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {plan.status_label}
                                </p>
                                <dl className="mt-4 space-y-2 text-xs">
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Screens
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {meter(
                                                plan.screens,
                                                plan.screens_limit,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <dt className="text-muted-foreground">
                                            Users
                                        </dt>
                                        <dd className="font-medium tabular-nums">
                                            {meter(plan.users, plan.users_limit)}
                                        </dd>
                                    </div>
                                </dl>
                                <Link
                                    href={href('billing')}
                                    className="mt-5 flex items-center justify-between rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
                                >
                                    Upgrade
                                    <ArrowRight className="size-4" />
                                </Link>
                            </section>
                        )}
                        <section className="dashboard-feature relative overflow-hidden rounded-2xl p-6 text-white">
                            <div className="relative z-10">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-semibold tracking-wider uppercase">
                                    <Sparkles className="size-3" />
                                    Make an impression
                                </span>
                                <h2 className="mt-5 text-2xl leading-tight font-semibold tracking-tight">
                                    Great content.
                                    <br />
                                    Every screen.
                                </h2>
                                <p className="mt-3 text-sm leading-relaxed text-blue-100/75">
                                    Turn everyday displays into something worth
                                    looking at.
                                </p>
                            </div>
                            <div
                                aria-hidden="true"
                                className="relative my-7 flex h-28 items-center justify-center"
                            >
                                <div className="absolute h-20 w-32 -translate-x-12 -rotate-12 rounded-lg border border-white/25 bg-blue-400/15 p-2">
                                    <div className="h-full rounded bg-blue-400/20" />
                                </div>
                                <div className="relative ml-9 h-28 w-44 rotate-6 rounded-lg border border-white/40 bg-[#162b55] p-2 shadow-2xl">
                                    <div className="flex h-full flex-col justify-end rounded bg-gradient-to-tr from-blue-600 via-indigo-500 to-cyan-300 p-3">
                                        <div className="mb-2 h-1.5 w-8 rounded bg-white/50" />
                                        <div className="h-2 w-20 rounded bg-white/90" />
                                        <div className="mt-1.5 h-1 w-14 rounded bg-white/50" />
                                    </div>
                                </div>
                            </div>
                            <Link
                                href={href('templates')}
                                className="relative flex items-center justify-between rounded-lg bg-white px-4 py-3 text-sm font-semibold text-blue-950 transition-colors hover:bg-blue-50"
                            >
                                Explore templates
                                <ArrowRight className="size-4" />
                            </Link>
                        </section>
                        <section className="dashboard-card p-5">
                            <h2 className="mb-2 text-sm font-semibold">
                                Quick actions
                            </h2>
                            {[
                                {
                                    label: 'Create a design',
                                    path: 'designs',
                                    icon: LayoutTemplate,
                                },
                                {
                                    label: 'Upload media',
                                    path: 'media',
                                    icon: ArrowDownToLine,
                                },
                                {
                                    label: 'Manage screens',
                                    path: 'screens',
                                    icon: Monitor,
                                },
                                {
                                    label: 'Schedule content',
                                    path: 'schedules',
                                    icon: CalendarClock,
                                },
                            ].map((item) => (
                                <Link
                                    key={item.path}
                                    href={href(item.path)}
                                    className="group hover:bg-muted flex items-center gap-3 rounded-lg py-3 text-sm transition-colors"
                                >
                                    <item.icon className="text-muted-foreground ml-1 size-4" />
                                    <span className="flex-1">{item.label}</span>
                                    <ChevronRight className="text-muted-foreground mr-1 size-3.5 group-hover:text-blue-600" />
                                </Link>
                            ))}
                        </section>
                        <div className="flex items-start gap-3 px-1 sm:col-span-2 xl:col-span-1">
                            <span className="bg-muted flex size-8 shrink-0 items-center justify-center rounded-full">
                                <Activity className="text-muted-foreground size-4" />
                            </span>
                            <p className="text-muted-foreground text-xs leading-relaxed">
                                A connected workspace starts here. Manage your
                                content, screens, and schedules from one place.
                            </p>
                        </div>
                    </aside>
                </div>
                <footer className="text-muted-foreground flex flex-wrap justify-between gap-2 border-t pt-5 text-[11px]">
                    <span>{currentTeam?.name ?? 'Your workspace'}</span>
                    <span>Made for content. Built for connection.</span>
                </footer>
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    color,
    background,
}: {
    label: string;
    value: number;
    hint: string;
    icon: LucideIcon;
    color: string;
    background: string;
}) {
    return (
        <div className="px-4 sm:px-5">
            <div className="mb-3 flex items-center gap-2">
                <span
                    className={`flex size-7 items-center justify-center rounded-lg ${color} ${background}`}
                >
                    <Icon className="size-3.5" />
                </span>
                <span className="text-muted-foreground text-xs font-medium">
                    {label}
                </span>
            </div>
            <p className="text-3xl font-semibold tracking-tight tabular-nums">
                {value}
            </p>
            <p className="text-muted-foreground mt-1.5 text-[11px]">{hint}</p>
        </div>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
