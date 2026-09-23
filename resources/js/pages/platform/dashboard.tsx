import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { PlatformMetrics } from '@/types';

type Props = { metrics: PlatformMetrics };

function money(cents: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(cents / 100);
}

function bytes(value: number): string {
    if (value < 1024) {
        return `${value} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = value / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(1)} ${units[unit]}`;
}

export default function PlatformDashboard({ metrics }: Props) {
    return (
        <>
            <Head title="Platform" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Platform" description="Organizations, subscriptions, screens, usage, and queue health." />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Card label="Organizations" value={metrics.organizations} />
                    <Card label="Active subscriptions" value={metrics.active_subscriptions} />
                    <Card label="MRR" value={money(metrics.mrr_cents)} />
                    <Card label="Users" value={metrics.users} />
                    <Card label="Screens" value={metrics.screens} />
                    <Card label="Online screens" value={metrics.online_screens} />
                    <Card label="Offline screens" value={metrics.offline_screens} />
                    <Card label="Storage" value={bytes(metrics.storage_bytes)} />
                    <Card label="Bandwidth" value={bytes(metrics.bandwidth_bytes)} />
                    <Card label="Queue" value={`${metrics.queue.pending} pending / ${metrics.queue.failed} failed`} />
                </div>
            </div>
        </>
    );
}

function Card({ label, value }: { label: string; value: string | number }) {
    return (
        <article className="rounded-lg border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </article>
    );
}

PlatformDashboard.layout = () => ({
    breadcrumbs: [{ title: 'Platform', href: '/platform' }],
});
