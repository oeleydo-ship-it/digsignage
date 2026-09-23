import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Pending = { id: number; queue: string; attempts: number; available_at: string };
type Failed = {
    id: number;
    uuid: string;
    queue: string;
    connection: string;
    failed_at: string;
    exception: string;
};

type Props = { pending: Pending[]; failed: Failed[] };

export default function PlatformJobs({ pending, failed }: Props) {
    return (
        <>
            <Head title="Jobs" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Jobs" description="Queued work and failed jobs." />
                <section>
                    <h2 className="mb-2 font-medium">Pending ({pending.length})</h2>
                    <ul className="space-y-1 text-sm">
                        {pending.length === 0 ? <li className="text-muted-foreground">Queue is empty.</li> : pending.map((job) => (
                            <li key={job.id}>
                                #{job.id} · {job.queue} · {job.attempts} attempts
                            </li>
                        ))}
                    </ul>
                </section>
                <section>
                    <h2 className="mb-2 font-medium">Failed ({failed.length})</h2>
                    <div className="space-y-3">
                        {failed.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No failed jobs.</p>
                        ) : (
                            failed.map((job) => (
                                <article key={job.uuid} className="rounded-lg border p-3 text-sm">
                                    <p>
                                        {job.queue} · {job.connection} · {job.failed_at}
                                    </p>
                                    <pre className="mt-2 max-h-32 overflow-auto whitespace-pre-wrap text-xs">{job.exception}</pre>
                                    <div className="mt-2 flex gap-2">
                                        <Button size="sm" onClick={() => router.post(`/platform/jobs/${job.uuid}/retry`)}>
                                            Retry
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.delete(`/platform/jobs/${job.uuid}`)}
                                        >
                                            Discard
                                        </Button>
                                    </div>
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

PlatformJobs.layout = () => ({
    breadcrumbs: [
        { title: 'Platform', href: '/platform' },
        { title: 'Jobs', href: '/platform/jobs' },
    ],
});
