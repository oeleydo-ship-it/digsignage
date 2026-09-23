import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Props = {
    health: {
        app: string;
        env: string;
        php: string;
        laravel: string;
        cache: string;
        queue: string;
        database: string;
        pending_jobs: number;
        failed_jobs: number;
    };
};

export default function PlatformHealth({ health }: Props) {
    const rows = [
        ['Application', health.app],
        ['Environment', health.env],
        ['PHP', health.php],
        ['Laravel', health.laravel],
        ['Cache', health.cache],
        ['Queue', health.queue],
        ['Database', health.database],
        ['Pending jobs', String(health.pending_jobs)],
        ['Failed jobs', String(health.failed_jobs)],
    ];

    return (
        <>
            <Head title="System health" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="System health" description="Runtime, queue, and datastore status." />
                <dl className="grid max-w-xl gap-2 rounded-lg border p-4 text-sm">
                    {rows.map(([label, value]) => (
                        <div key={label} className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd>{value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </>
    );
}

PlatformHealth.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'System health', href: '/platform/health' },
    ],
});
