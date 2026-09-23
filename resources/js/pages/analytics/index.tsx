import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { AnalyticsBar, AnalyticsDailyOps, AnalyticsReport } from '@/types';

type Option = { value: string; label: string };

type Props = {
    filters: {
        screen_id: number | null;
        location_id: number | null;
        from: string;
        until: string;
    };
    report: AnalyticsReport;
    options: {
        screens: Option[];
        locations: Option[];
    };
};

function durationLabel(ms: number): string {
    if (ms <= 0) {
        return '0s';
    }

    const seconds = Math.round(ms / 1000);

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return rest ? `${minutes}m ${rest}s` : `${minutes}m`;
}

export default function AnalyticsIndex({ filters, report, options }: Props) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const [form, setForm] = useState({
        screen_id: filters.screen_id ? String(filters.screen_id) : '',
        location_id: filters.location_id ? String(filters.location_id) : '',
        from: filters.from,
        until: filters.until,
    });

    const apply = () => {
        router.get(
            `/${slug}/analytics`,
            {
                screen_id: form.screen_id || undefined,
                location_id: form.location_id || undefined,
                from: form.from,
                until: form.until,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Analytics"
                    description="Screen health, content plays, and operational failures for the selected range."
                />

                <form
                    className="grid gap-3 rounded-lg border p-4 md:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply();
                    }}
                >
                    <FilterSelect
                        label="Screen"
                        value={form.screen_id}
                        options={options.screens}
                        onChange={(screen_id) =>
                            setForm((current) => ({ ...current, screen_id }))
                        }
                    />
                    <FilterSelect
                        label="Location"
                        value={form.location_id}
                        options={options.locations}
                        onChange={(location_id) =>
                            setForm((current) => ({ ...current, location_id }))
                        }
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.from}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    from: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="until">Until</Label>
                        <Input
                            id="until"
                            type="date"
                            value={form.until}
                            onChange={(event) =>
                                setForm((current) => ({
                                    ...current,
                                    until: event.target.value,
                                }))
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" data-test="apply-analytics">
                            Apply range
                        </Button>
                    </div>
                </form>

                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Screen analytics</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Online" value={report.screens.online} />
                        <Stat label="Warning" value={report.screens.warning} />
                        <Stat label="Offline" value={report.screens.offline} />
                        <Stat
                            label="Average uptime"
                            value={`${report.screens.uptime_percent}%`}
                        />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <ChartCard title="Fleet status" bars={report.screens.status} />
                        <ChartCard
                            title="Player versions"
                            bars={report.screens.versions}
                        />
                    </div>
                    <Stat
                        label="Storage warnings"
                        value={report.screens.storage_warnings}
                    />
                </section>

                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Content analytics</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Total plays" value={report.content.plays} />
                        <Stat
                            label="Play duration"
                            value={durationLabel(report.content.duration_ms)}
                        />
                        <Stat
                            label="Screens reached"
                            value={report.content.screens_reached}
                        />
                        <Stat
                            label="Locations reached"
                            value={report.content.locations_reached}
                        />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <ChartCard
                            title="Most played content"
                            bars={report.content.top}
                        />
                        <ChartCard title="Plays by day" bars={report.content.daily} />
                    </div>
                </section>

                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Operational analytics</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <Stat
                            label="Failed downloads"
                            value={report.operations.failed_downloads}
                        />
                        <Stat label="Player crashes" value={report.operations.crashes} />
                        <Stat
                            label="Sync failures"
                            value={report.operations.sync_failures}
                        />
                        <Stat
                            label="Device errors"
                            value={report.operations.device_errors}
                        />
                        <Stat
                            label="Command failures"
                            value={report.operations.command_failures}
                        />
                    </div>
                    <OpsChart rows={report.operations.daily} />
                </section>
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}

function ChartCard({ title, bars }: { title: string; bars: AnalyticsBar[] }) {
    const max = Math.max(1, ...bars.map((bar) => bar.value));

    return (
        <div className="rounded-lg border p-4">
            <h3 className="mb-3 font-medium">{title}</h3>
            {bars.length === 0 ? (
                <p className="text-muted-foreground text-sm">No data in this range.</p>
            ) : (
                <div className="space-y-3">
                    {bars.map((bar) => (
                        <div key={bar.label}>
                            <div className="mb-1 flex justify-between text-sm">
                                <span>{bar.label}</span>
                                <span>{bar.value}</span>
                            </div>
                            <div className="bg-muted h-2 rounded-full">
                                <div
                                    className="bg-primary h-2 rounded-full"
                                    style={{
                                        width: `${Math.round((bar.value / max) * 100)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function OpsChart({ rows }: { rows: AnalyticsDailyOps[] }) {
    const max = Math.max(
        1,
        ...rows.flatMap((row) => [
            row.downloads,
            row.crashes,
            row.sync,
            row.errors,
            row.commands,
        ]),
    );

    return (
        <div className="rounded-lg border p-4">
            <h3 className="mb-3 font-medium">Failures by day</h3>
            {rows.length === 0 ? (
                <p className="text-muted-foreground text-sm">No operational events.</p>
            ) : (
                <div className="space-y-4">
                    {rows.map((row) => (
                        <div key={row.label}>
                            <p className="mb-1 text-sm font-medium">{row.label}</p>
                            <Stacked
                                max={max}
                                segments={[
                                    { label: 'Downloads', value: row.downloads, className: 'bg-amber-500' },
                                    { label: 'Crashes', value: row.crashes, className: 'bg-red-600' },
                                    { label: 'Sync', value: row.sync, className: 'bg-orange-500' },
                                    { label: 'Errors', value: row.errors, className: 'bg-rose-400' },
                                    { label: 'Commands', value: row.commands, className: 'bg-slate-500' },
                                ]}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function Stacked({
    max,
    segments,
}: {
    max: number;
    segments: { label: string; value: number; className: string }[];
}) {
    return (
        <div className="bg-muted flex h-3 overflow-hidden rounded-full">
            {segments
                .filter((segment) => segment.value > 0)
                .map((segment) => (
                    <div
                        key={segment.label}
                        title={`${segment.label}: ${segment.value}`}
                        className={segment.className}
                        style={{ width: `${Math.round((segment.value / max) * 100)}%` }}
                    />
                ))}
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
}) {
    const id = label.toLowerCase();

    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
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

AnalyticsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Analytics',
            href: props.currentTeam ? `/${props.currentTeam.slug}/analytics` : '/',
        },
    ],
});
