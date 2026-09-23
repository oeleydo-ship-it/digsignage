import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    name: string;
    slug: string;
    plan_name: string;
    subscription_status: string;
    status_label: string;
    trial_ends_at: string | null;
    suspended_at: string | null;
    screens_count: number;
};

type Props = {
    filters: { status: string };
    statuses: { value: string; label: string }[];
    subscriptions: Paginated<Row>;
};

export default function PlatformSubscriptions({ filters, statuses, subscriptions }: Props) {
    return (
        <>
            <Head title="Subscriptions" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Subscriptions" description="Plan and billing status for every organization." />
                <div className="max-w-xs space-y-1">
                    <Label htmlFor="status">Status</Label>
                    <select
                        id="status"
                        className="w-full rounded-md border bg-background p-2"
                        value={filters.status}
                        onChange={(event) =>
                            router.get('/platform/subscriptions', { status: event.target.value }, { preserveState: true })
                        }
                    >
                        <option value="">All</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Organization</th>
                                <th className="p-3">Plan</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Screens</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.data.map((row) => (
                                <tr key={row.id} className="border-b">
                                    <td className="p-3">
                                        <Link className="underline" href={`/platform/organizations/${row.slug}`}>
                                            {row.name}
                                        </Link>
                                        {row.suspended_at && <span className="ml-2 text-destructive">Suspended</span>}
                                    </td>
                                    <td className="p-3">{row.plan_name}</td>
                                    <td className="p-3">{row.status_label}</td>
                                    <td className="p-3">{row.screens_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Button asChild variant="outline">
                    <Link href="/platform/plans">View plans</Link>
                </Button>
            </div>
        </>
    );
}

PlatformSubscriptions.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Subscriptions', href: '/platform/subscriptions' },
    ],
});
