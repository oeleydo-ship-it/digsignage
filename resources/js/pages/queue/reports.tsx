import { router, usePage } from '@inertiajs/react';
import { BarChart3, Download, Timer, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    QueuePageShell,
    queueBreadcrumbs,
} from '@/pages/queue/queue-page-shell';
import type {
    QueueAnalyticsBar,
    QueueAnalyticsReport,
    QueuePermissions,
} from '@/types';

type Option = { value: string; label: string };
type Filters = {
    from: string;
    until: string;
    location_id: number | null;
    service_id: number | null;
    counter_id: number | null;
    employee_id: number | null;
    sla_minutes: number;
};
type Props = {
    filters: Filters;
    report: QueueAnalyticsReport;
    options: {
        locations: Option[];
        services: Option[];
        counters: Option[];
        employees: Option[];
    };
    permissions: QueuePermissions;
};
type VolumePeriod = 'hour' | 'day' | 'week' | 'month';

function duration(seconds: number | null | undefined): string {
    if (seconds == null) return '—';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return remainder === 0 ? `${minutes}m` : `${minutes}m ${remainder}s`;
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: number | null;
    options: Option[];
    onChange: (value: number | null) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <select
                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                value={value ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value ? Number(event.target.value) : null,
                    )
                }
            >
                <option value="">All</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint: string;
}) {
    return (
        <div className="dashboard-card p-4">
            <p className="text-muted-foreground text-xs font-medium">{label}</p>
            <p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p>
            <p className="text-muted-foreground mt-1 text-[11px]">{hint}</p>
        </div>
    );
}

function VolumeChart({ rows }: { rows: QueueAnalyticsBar[] }) {
    const max = Math.max(1, ...rows.map((row) => row.value));

    return rows.length === 0 ? (
        <p className="text-muted-foreground py-8 text-center text-sm">
            No tickets in this range.
        </p>
    ) : (
        <div className="max-h-80 space-y-2 overflow-y-auto pr-2">
            {rows.map((row) => (
                <div
                    key={row.label}
                    className="grid grid-cols-[90px_1fr_45px] items-center gap-3 text-sm"
                >
                    <span className="text-muted-foreground truncate">
                        {row.label}
                    </span>
                    <div className="bg-muted h-2.5 overflow-hidden rounded-full">
                        <div
                            className="h-full rounded-full bg-blue-600"
                            style={{
                                width: `${Math.max(2, Math.round((row.value / max) * 100))}%`,
                            }}
                        />
                    </div>
                    <span className="text-right font-medium tabular-nums">
                        {row.value}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function QueueReports({ filters, report, options }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [form, setForm] = useState(filters);
    const [period, setPeriod] = useState<VolumePeriod>('day');
    const query = {
        ...form,
        location_id: form.location_id || undefined,
        service_id: form.service_id || undefined,
        counter_id: form.counter_id || undefined,
        employee_id: form.employee_id || undefined,
    };
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '')
            params.set(key, String(value));
    });
    const reportUrl = `/${slug}/queue/reports`;

    return (
        <QueuePageShell
            title="Queue analytics"
            description="Operational trends, customer wait outcomes, and staff, counter, and service performance."
        >
            <div className="flex justify-end">
                <Button
                    asChild
                    variant="outline"
                    data-test="export-queue-analytics"
                >
                    <a href={`${reportUrl}/export?${params.toString()}`}>
                        <Download className="size-4" />
                        Export CSV
                    </a>
                </Button>
            </div>

            <form
                className="dashboard-card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(reportUrl, query, {
                        preserveState: true,
                        replace: true,
                    });
                }}
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="queue-report-from">From</Label>
                    <Input
                        id="queue-report-from"
                        type="date"
                        value={form.from}
                        onChange={(event) =>
                            setForm({ ...form, from: event.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="queue-report-until">Until</Label>
                    <Input
                        id="queue-report-until"
                        type="date"
                        value={form.until}
                        onChange={(event) =>
                            setForm({ ...form, until: event.target.value })
                        }
                    />
                </div>
                <FilterSelect
                    label="Location"
                    value={form.location_id}
                    options={options.locations}
                    onChange={(location_id) =>
                        setForm({ ...form, location_id })
                    }
                />
                <FilterSelect
                    label="Service"
                    value={form.service_id}
                    options={options.services}
                    onChange={(service_id) => setForm({ ...form, service_id })}
                />
                <FilterSelect
                    label="Counter"
                    value={form.counter_id}
                    options={options.counters}
                    onChange={(counter_id) => setForm({ ...form, counter_id })}
                />
                <FilterSelect
                    label="Employee"
                    value={form.employee_id}
                    options={options.employees}
                    onChange={(employee_id) =>
                        setForm({ ...form, employee_id })
                    }
                />
                <div className="grid gap-1.5">
                    <Label htmlFor="queue-report-sla">
                        SLA wait target (minutes)
                    </Label>
                    <Input
                        id="queue-report-sla"
                        type="number"
                        min={1}
                        max={1440}
                        value={form.sla_minutes}
                        onChange={(event) =>
                            setForm({
                                ...form,
                                sla_minutes: Number(event.target.value),
                            })
                        }
                    />
                </div>
                <div className="flex items-end">
                    <Button type="submit" data-test="apply-queue-analytics">
                        Apply filters
                    </Button>
                </div>
            </form>

            <section
                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                aria-label="Queue report summary"
            >
                <SummaryCard
                    label="Tickets issued"
                    value={report.summary.tickets}
                    hint="Within the selected range"
                />
                <SummaryCard
                    label="Completed"
                    value={report.summary.completed}
                    hint="Successfully served"
                />
                <SummaryCard
                    label="Abandonment rate"
                    value={`${report.summary.abandonment_rate}%`}
                    hint="Cancelled ÷ issued"
                />
                <SummaryCard
                    label="No-show rate"
                    value={`${report.summary.no_show_rate}%`}
                    hint="No-shows ÷ issued"
                />
                <SummaryCard
                    label="SLA compliance"
                    value={`${report.summary.sla_compliance}%`}
                    hint={`${report.summary.sla_eligible} measured tickets`}
                />
                <SummaryCard
                    label="Peak hour"
                    value={report.peak_hours[0]?.label ?? '—'}
                    hint={
                        report.peak_hours[0]
                            ? `${report.peak_hours[0].value} tickets`
                            : 'No volume'
                    }
                />
            </section>

            <section className="grid gap-4 lg:grid-cols-2">
                <div className="dashboard-card p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Timer className="size-4 text-violet-600" />
                        <h2 className="font-semibold">Waiting time</h2>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <SummaryCard
                            label="Average"
                            value={duration(report.waiting_time.average)}
                            hint=""
                        />
                        <SummaryCard
                            label="Median"
                            value={duration(report.waiting_time.median)}
                            hint=""
                        />
                        <SummaryCard
                            label="Maximum"
                            value={duration(report.waiting_time.maximum)}
                            hint=""
                        />
                        <SummaryCard
                            label="Minimum"
                            value={duration(report.waiting_time.minimum)}
                            hint={`${report.waiting_time.samples} samples`}
                        />
                    </div>
                </div>
                <div className="dashboard-card p-5">
                    <div className="mb-4 flex items-center gap-2">
                        <Timer className="size-4 text-cyan-600" />
                        <h2 className="font-semibold">Service time</h2>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <SummaryCard
                            label="Average"
                            value={duration(report.service_time.average)}
                            hint=""
                        />
                        <SummaryCard
                            label="Median"
                            value={duration(report.service_time.median)}
                            hint=""
                        />
                        <SummaryCard
                            label="Maximum"
                            value={duration(report.service_time.maximum)}
                            hint={`${report.service_time.samples} samples`}
                        />
                    </div>
                </div>
            </section>

            <section className="dashboard-card p-5">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="size-4 text-blue-600" />
                        <h2 className="font-semibold">Ticket volume</h2>
                    </div>
                    <div className="flex gap-1">
                        {(
                            ['hour', 'day', 'week', 'month'] as VolumePeriod[]
                        ).map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={period === value ? 'default' : 'ghost'}
                                onClick={() => setPeriod(value)}
                            >
                                {value[0].toUpperCase() + value.slice(1)}
                            </Button>
                        ))}
                    </div>
                </div>
                <VolumeChart rows={report.volume[period]} />
            </section>

            <section className="dashboard-card overflow-hidden">
                <div className="px-5 py-4">
                    <h2 className="font-semibold">Peak hours</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Busiest issue hours across the selected dates.
                    </p>
                </div>
                <div className="grid gap-3 border-t p-5 sm:grid-cols-2 lg:grid-cols-5">
                    {report.peak_hours.map((row, index) => (
                        <div key={row.label} className="rounded-lg border p-3">
                            <span className="text-muted-foreground text-xs">
                                #{index + 1}
                            </span>
                            <p className="mt-1 font-semibold">{row.label}</p>
                            <p className="text-muted-foreground text-sm">
                                {row.value} tickets
                            </p>
                        </div>
                    ))}
                </div>
            </section>

            <PerformanceTable
                title="Location performance"
                headers={[
                    'Location',
                    'Tickets',
                    'Completed',
                    'Avg wait',
                    'Avg service',
                    'SLA',
                    'No shows',
                    'Abandoned',
                ]}
                empty="No branch activity in this period."
                rows={report.locations.map((row) => [
                    row.name,
                    row.tickets,
                    row.completed,
                    duration(row.average_wait_seconds),
                    duration(row.average_service_seconds),
                    `${row.sla_compliance}%`,
                    row.no_shows,
                    row.abandoned,
                ])}
            />
            <PerformanceTable
                title="Staff performance"
                icon={<Users className="size-4 text-blue-600" />}
                headers={[
                    'Employee',
                    'Tickets served',
                    'Avg handling',
                    'No shows',
                ]}
                empty="No assigned staff activity."
                rows={report.staff.map((row) => [
                    row.name,
                    row.tickets_served,
                    duration(row.average_handling_seconds),
                    row.no_shows,
                ])}
            />
            <PerformanceTable
                title="Counter performance"
                headers={[
                    'Counter',
                    'Tickets served',
                    'Avg wait',
                    'Avg handling',
                    'No shows',
                ]}
                empty="No counter activity."
                rows={report.counters.map((row) => [
                    row.name,
                    row.tickets_served,
                    duration(row.average_wait_seconds),
                    duration(row.average_handling_seconds),
                    row.no_shows,
                ])}
            />
            <PerformanceTable
                title="Service performance"
                headers={[
                    'Service',
                    'Tickets',
                    'Completed',
                    'Avg wait',
                    'Avg service',
                    'No shows',
                    'Abandoned',
                ]}
                empty="No service activity."
                rows={report.services.map((row) => [
                    row.name,
                    row.tickets,
                    row.completed,
                    duration(row.average_wait_seconds),
                    duration(row.average_service_seconds),
                    row.no_shows,
                    row.abandoned,
                ])}
            />
        </QueuePageShell>
    );
}

function PerformanceTable({
    title,
    icon,
    headers,
    rows,
    empty,
}: {
    title: string;
    icon?: React.ReactNode;
    headers: string[];
    rows: Array<Array<string | number>>;
    empty: string;
}) {
    return (
        <section className="dashboard-card overflow-hidden">
            <div className="flex items-center gap-2 px-5 py-4">
                {icon}
                <h2 className="font-semibold">{title}</h2>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[680px] text-left text-sm">
                    <thead className="text-muted-foreground bg-muted/40 text-xs uppercase">
                        <tr>
                            {headers.map((header) => (
                                <th
                                    key={header}
                                    className="px-4 py-2 font-medium"
                                >
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    className="text-muted-foreground px-4 py-8 text-center"
                                    colSpan={headers.length}
                                >
                                    {empty}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row, index) => (
                                <tr
                                    key={`${row[0]}-${index}`}
                                    className="border-t"
                                >
                                    {row.map((cell, cellIndex) => (
                                        <td
                                            key={`${cellIndex}-${cell}`}
                                            className={`px-4 py-3 ${cellIndex === 0 ? 'font-medium' : 'tabular-nums'}`}
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

QueueReports.layout = queueBreadcrumbs('Reports', '/reports');
