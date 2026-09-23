import { router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock3,
    Gauge,
    MonitorCheck,
    MonitorX,
    Radio,
    Timer,
    UserX,
    Users,
} from 'lucide-react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ReverbConfig } from '@/lib/player-echo';
import { subscribeQueueDashboardUpdates } from '@/lib/queue-echo';
import {
    QueuePageShell,
    QueueStatCard,
    queueBreadcrumbs,
} from '@/pages/queue/queue-page-shell';
import type {
    QueueCongestion,
    QueueOperationsDashboard,
    QueueOperationsService,
    QueuePermissions,
} from '@/types';

type Props = {
    operations: QueueOperationsDashboard;
    reverb: ReverbConfig;
    permissions: QueuePermissions;
    settings: Record<string, unknown>;
    filters: { location_id: number | null };
    locations: Array<{ id: number; name: string; depth: number }>;
};

function formatDuration(seconds: number | null): string {
    if (seconds === null) return '—';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return remainder === 0 ? `${minutes}m` : `${minutes}m ${remainder}s`;
}

function CongestionBadge({ congestion }: { congestion: QueueCongestion }) {
    const styles = {
        normal: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
        moderate:
            'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
        high: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300',
        critical:
            'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    };

    return (
        <Badge variant="outline" className={styles[congestion.level]}>
            {congestion.label}
        </Badge>
    );
}

function ServiceRows({ services }: { services: QueueOperationsService[] }) {
    return services.map((service) => (
        <tr key={service.id} className="border-t">
            <td className="px-4 py-3 font-medium">{service.name}</td>
            <td className="px-3 py-3 tabular-nums">{service.waiting}</td>
            <td className="px-3 py-3 tabular-nums">{service.serving}</td>
            <td className="px-3 py-3 tabular-nums">
                {formatDuration(service.longest_wait_seconds)}
            </td>
            <td className="px-3 py-3 tabular-nums">
                {service.open_counters}/{service.total_counters}
            </td>
            <td className="px-4 py-3 text-right">
                <CongestionBadge congestion={service.congestion} />
            </td>
        </tr>
    ));
}

export default function QueueOverview({
    operations,
    reverb,
    filters,
    locations,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const { stats } = operations;
    const serviceKey = operations.service_ids.join(',');

    useEffect(() => {
        const serviceIds =
            serviceKey === '' ? [] : serviceKey.split(',').map(Number);
        let unsubscribe: () => void = () => undefined;
        let stopped = false;
        let refreshTimer: number | undefined;
        const refresh = () => {
            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(
                () => router.reload({ only: ['operations'] }),
                400,
            );
        };

        void subscribeQueueDashboardUpdates(serviceIds, reverb, refresh).then(
            (cleanup) => {
                if (stopped) cleanup();
                else unsubscribe = cleanup;
            },
        );
        const poll = window.setInterval(refresh, 15_000);

        return () => {
            stopped = true;
            window.clearInterval(poll);
            window.clearTimeout(refreshTimer);
            unsubscribe();
        };
    }, [serviceKey, reverb]);

    return (
        <QueuePageShell
            title="Operations dashboard"
            description="Live queue health across every location, service, and counter in this workspace."
        >
            <div className="dashboard-card flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <p className="text-sm font-medium">Location scope</p>
                    <p className="text-muted-foreground text-xs">
                        View one branch or the combined corporate operation.
                    </p>
                </div>
                <Select
                    value={filters.location_id ? String(filters.location_id) : 'all'}
                    onValueChange={(value) =>
                        router.get(
                            `/${slug}/queue`,
                            { location_id: value === 'all' ? undefined : Number(value) },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <SelectTrigger className="w-full sm:w-64" data-test="queue-location-scope">
                        <SelectValue placeholder="All locations" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All locations · Corporate</SelectItem>
                        {locations.map((location) => (
                            <SelectItem key={location.id} value={String(location.id)}>
                                {`${'— '.repeat(location.depth)}${location.name}`}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <section
                aria-labelledby="queue-stats-heading"
                className="dashboard-card"
            >
                <div className="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
                    <h2
                        id="queue-stats-heading"
                        className="text-base font-semibold"
                    >
                        Today&apos;s queue
                    </h2>
                    <span className="text-muted-foreground text-xs">
                        Updated{' '}
                        {new Date(operations.refreshed_at).toLocaleTimeString()}
                    </span>
                </div>
                <div className="grid grid-cols-1 gap-y-7 px-2 py-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <QueueStatCard
                        label="Waiting customers"
                        value={stats.waiting}
                        icon={Users}
                        color="text-amber-600 dark:text-amber-400"
                        background="bg-amber-50 dark:bg-amber-950"
                        hint="Tickets currently in line"
                    />
                    <QueueStatCard
                        label="Currently serving"
                        value={stats.serving}
                        icon={Radio}
                        color="text-blue-600 dark:text-blue-400"
                        background="bg-blue-50 dark:bg-blue-950"
                        hint="Called or being helped"
                    />
                    <QueueStatCard
                        label="Completed today"
                        value={stats.completed_today}
                        icon={CheckCircle2}
                        color="text-emerald-600 dark:text-emerald-400"
                        background="bg-emerald-50 dark:bg-emerald-950"
                        hint="Successfully finished"
                    />
                    <QueueStatCard
                        label="No shows"
                        value={stats.no_shows_today}
                        icon={UserX}
                        color="text-rose-600 dark:text-rose-400"
                        background="bg-rose-50 dark:bg-rose-950"
                        hint="Marked no-show today"
                    />
                    <QueueStatCard
                        label="Open counters"
                        value={stats.open_counters}
                        icon={MonitorCheck}
                        color="text-emerald-600 dark:text-emerald-400"
                        background="bg-emerald-50 dark:bg-emerald-950"
                        hint="Open or currently busy"
                    />
                    <QueueStatCard
                        label="Closed counters"
                        value={stats.closed_counters}
                        icon={MonitorX}
                        color="text-slate-600 dark:text-slate-300"
                        background="bg-slate-100 dark:bg-slate-900"
                        hint="Closed or paused"
                    />
                    <QueueStatCard
                        label="Average wait"
                        value={formatDuration(stats.average_wait_seconds)}
                        icon={Clock3}
                        color="text-violet-600 dark:text-violet-400"
                        background="bg-violet-50 dark:bg-violet-950"
                        hint="Across today and active tickets"
                    />
                    <QueueStatCard
                        label="Average service"
                        value={formatDuration(stats.average_service_seconds)}
                        icon={Timer}
                        color="text-cyan-600 dark:text-cyan-400"
                        background="bg-cyan-50 dark:bg-cyan-950"
                        hint="Completed tickets today"
                    />
                    <div
                        className="px-4 sm:px-5"
                        data-test="longest-waiting-card"
                    >
                        <div className="mb-3 flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-lg bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-400">
                                <Gauge className="size-3.5" />
                            </span>
                            <span className="text-muted-foreground text-xs font-medium">
                                Longest waiting
                            </span>
                        </div>
                        <p className="font-mono text-3xl font-semibold tracking-tight">
                            {stats.longest_waiting?.number ?? '—'}
                        </p>
                        <p className="text-muted-foreground mt-1.5 text-[11px]">
                            {stats.longest_waiting
                                ? `${formatDuration(stats.longest_waiting.seconds)} · ${stats.longest_waiting.service_name}`
                                : 'No customer waiting'}
                        </p>
                    </div>
                </div>
            </section>

            <section className="grid gap-4" aria-labelledby="locations-heading">
                <div>
                    <h2
                        id="locations-heading"
                        className="text-lg font-semibold"
                    >
                        By location
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        Each branch with its service queues and current
                        pressure.
                    </p>
                </div>
                {operations.locations.length === 0 ? (
                    <p className="dashboard-card text-muted-foreground px-5 py-10 text-center text-sm">
                        No queue services configured.
                    </p>
                ) : (
                    operations.locations.map((location) => (
                        <article
                            key={location.id ?? 'all'}
                            className="dashboard-card overflow-hidden"
                            data-test={`queue-location-${location.id ?? 'all'}`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div>
                                    <h3 className="font-semibold">
                                        {location.name}
                                    </h3>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {location.waiting} waiting ·{' '}
                                        {location.serving} serving ·{' '}
                                        {location.open_counters}/
                                        {location.total_counters} counters open
                                    </p>
                                </div>
                                <CongestionBadge
                                    congestion={location.congestion}
                                />
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[680px] text-left text-sm">
                                    <thead className="text-muted-foreground bg-muted/40 text-xs uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Service
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Waiting
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Serving
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Longest
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Counters
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Congestion
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <ServiceRows
                                            services={location.services}
                                        />
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    ))
                )}
            </section>

            <section
                className="dashboard-card overflow-hidden"
                aria-labelledby="services-heading"
            >
                <div className="px-5 py-4">
                    <h2 id="services-heading" className="text-lg font-semibold">
                        By service
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        All queues with their live congestion.
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="text-muted-foreground bg-muted/40 text-xs uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Service
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Location
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Waiting
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Serving
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Longest
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Counters
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Congestion
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {operations.services.map((service) => (
                                <tr key={service.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {service.name}
                                    </td>
                                    <td className="text-muted-foreground px-3 py-3">
                                        {service.location_name}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {service.waiting}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {service.serving}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {formatDuration(
                                            service.longest_wait_seconds,
                                        )}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {service.open_counters}/
                                        {service.total_counters}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <CongestionBadge
                                            congestion={service.congestion}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                className="dashboard-card overflow-hidden"
                aria-labelledby="counters-heading"
            >
                <div className="px-5 py-4">
                    <h2 id="counters-heading" className="text-lg font-semibold">
                        By counter
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Desk availability, current ticket, and eligible waiting
                        load.
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="text-muted-foreground bg-muted/40 text-xs uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Counter
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Location
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Services
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Current ticket
                                </th>
                                <th className="px-4 py-2 text-right font-medium">
                                    Eligible waiting
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {operations.counters.map((counter) => (
                                <tr
                                    key={counter.id}
                                    className="border-t"
                                    data-test={`queue-counter-${counter.id}`}
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {counter.name}
                                        <span className="text-muted-foreground ml-2 font-mono text-xs">
                                            {counter.code}
                                        </span>
                                    </td>
                                    <td className="text-muted-foreground px-3 py-3">
                                        {counter.location_name}
                                    </td>
                                    <td className="px-3 py-3">
                                        {counter.services.join(', ') || '—'}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge variant="outline">
                                            {counter.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-3 font-mono font-semibold">
                                        {counter.current_ticket ?? '—'}
                                        {counter.current_status ? (
                                            <span className="text-muted-foreground ml-2 font-sans text-xs font-normal">
                                                {counter.current_status}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {counter.waiting}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </QueuePageShell>
    );
}

QueueOverview.layout = queueBreadcrumbs('Overview', '');
