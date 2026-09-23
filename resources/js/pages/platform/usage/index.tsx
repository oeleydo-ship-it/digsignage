import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Row = {
    id: number;
    name: string;
    plan_name: string;
    storage_bytes: number;
    media_files: number;
    bandwidth_bytes: number;
};

type Props = {
    organizations: Row[];
    totals: { storage_bytes: number; media_files: number; bandwidth_bytes: number };
};

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

export default function PlatformUsage({ organizations, totals }: Props) {
    return (
        <>
            <Head title="Usage" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Storage, media, and bandwidth" description="Consumption across the platform." />
                <p className="text-sm text-muted-foreground">
                    Totals: {bytes(totals.storage_bytes)} storage · {totals.media_files} files · {bytes(totals.bandwidth_bytes)} bandwidth
                </p>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Organization</th>
                                <th className="p-3">Plan</th>
                                <th className="p-3">Storage</th>
                                <th className="p-3">Media files</th>
                                <th className="p-3">Bandwidth</th>
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.map((row) => (
                                <tr key={row.id} className="border-b">
                                    <td className="p-3">{row.name}</td>
                                    <td className="p-3">{row.plan_name}</td>
                                    <td className="p-3">{bytes(row.storage_bytes)}</td>
                                    <td className="p-3">{row.media_files}</td>
                                    <td className="p-3">{bytes(row.bandwidth_bytes)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

PlatformUsage.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Usage', href: '/platform/usage' },
    ],
});
