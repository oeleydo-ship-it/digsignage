import { QueuePageShell, queueBreadcrumbs } from '@/pages/queue/queue-page-shell';
import type { QueueLiveService, QueuePermissions } from '@/types';

type Props = {
    title: string;
    services: QueueLiveService[];
    permissions: QueuePermissions;
};

function formatCreated(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function QueueLive({ services }: Props) {
    const hasWaiting = services.some((service) => service.waiting_count > 0);

    return (
        <QueuePageShell
            title="Live Queue"
            description="Waiting tickets in the order the dispatch engine will call them. FIFO is oldest first; Priority is highest weight, with starvation when configured."
        >
            {!hasWaiting ? (
                <p className="text-muted-foreground dashboard-card px-5 py-12 text-center text-sm">
                    No one is waiting. Issued tickets appear here in engine
                    order.
                </p>
            ) : (
                <div className="grid gap-4">
                    {services.map((service) => (
                        <section
                            key={service.id}
                            className="dashboard-card overflow-x-auto"
                            data-test={`live-queue-service-${service.name}`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3">
                                <h2 className="text-base font-semibold">
                                    {service.name}
                                </h2>
                                <p className="text-muted-foreground text-xs">
                                    {service.queue_strategy_label} ·{' '}
                                    {service.waiting_count} waiting
                                </p>
                            </div>
                            {service.waiting.length === 0 ? (
                                <p className="text-muted-foreground px-5 py-6 text-sm">
                                    Empty
                                </p>
                            ) : (
                                <table className="w-full min-w-[520px] text-left text-sm">
                                    <thead className="text-muted-foreground border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-5 py-2 font-medium">
                                                Pos
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Ticket
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Priority
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Customer
                                            </th>
                                            <th className="px-5 py-2 font-medium">
                                                Issued
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {service.waiting.map((ticket, index) => (
                                            <tr
                                                key={ticket.id}
                                                data-test={`live-ticket-${ticket.number}`}
                                            >
                                                <td className="px-5 py-3 tabular-nums">
                                                    {ticket.queue_position ??
                                                        index + 1}
                                                </td>
                                                <td className="px-3 py-3 font-mono font-semibold">
                                                    {ticket.number}
                                                </td>
                                                <td className="px-3 py-3 tabular-nums">
                                                    {ticket.priority}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {ticket.customer_name ??
                                                        '—'}
                                                </td>
                                                <td className="text-muted-foreground px-5 py-3">
                                                    {formatCreated(
                                                        ticket.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </section>
                    ))}
                </div>
            )}
        </QueuePageShell>
    );
}

QueueLive.layout = queueBreadcrumbs('Live Queue', '/live');
