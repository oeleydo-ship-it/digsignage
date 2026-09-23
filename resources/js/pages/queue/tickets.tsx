import { router, usePage } from '@inertiajs/react';
import { Search, Ticket } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import { createQueueRequestKey } from '@/lib/queue-idempotency';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { QueuePageShell, queueBreadcrumbs } from '@/pages/queue/queue-page-shell';
import { cancel, store } from '@/routes/queue/tickets';
import type {
    QueuePermissions,
    QueuePriorityRecord,
    QueueTicketEventRecord,
    QueueTicketRecord,
    QueueTicketServiceOption,
    QueueTicketStatusValue,
} from '@/types';
import type { Paginated } from '@/types/signage';

type StatusOption = { value: QueueTicketStatusValue; label: string };

type Props = {
    tickets: Paginated<QueueTicketRecord>;
    filters: {
        search: string;
        status: string;
        queue_service_id: number | null;
    };
    services: QueueTicketServiceOption[];
    statuses: StatusOption[];
    priorities: Pick<
        QueuePriorityRecord,
        'id' | 'name' | 'code' | 'weight' | 'color'
    >[];
    permissions: QueuePermissions;
};

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

function formatCreated(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function payloadValue(payload: Record<string, unknown> | null, key: string): string | null {
    if (!payload || !(key in payload)) {
        return null;
    }

    const value = payload[key];

    if (typeof value === 'string' && value !== '') {
        return value;
    }

    if (typeof value === 'number') {
        return String(value);
    }

    return null;
}

function eventSummary(event: QueueTicketEventRecord): string {
    const payload = event.payload;

    if (event.type === 'transferred') {
        const fromService = payloadValue(payload, 'previous_service_name');
        const toService = payloadValue(payload, 'new_service_name');
        const reason = payloadValue(payload, 'reason');
        const parts = [
            fromService && toService ? `${fromService} → ${toService}` : null,
            reason,
        ].filter((part): part is string => part !== null);

        return parts.join(' · ') || 'Transferred';
    }

    const from = payloadValue(payload, 'from');
    const to = payloadValue(payload, 'to');

    if (from && to) {
        return `${from} → ${to}`;
    }

    const number = payloadValue(payload, 'number');

    return number ? `Issued ${number}` : '';
}

function statusVariant(
    status: QueueTicketStatusValue,
): 'secondary' | 'outline' | 'destructive' | 'default' {
    if (status === 'waiting') {
        return 'secondary';
    }

    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'completed') {
        return 'outline';
    }

    return 'default';
}

export default function QueueTickets({
    tickets,
    filters,
    services,
    statuses,
    permissions,
    priorities,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [cancelling, setCancelling] = useState<QueueTicketRecord | null>(
        null,
    );
    const [journey, setJourney] = useState<QueueTicketRecord | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [serviceId, setServiceId] = useState('');
    const [priorityId, setPriorityId] = useState('');
    const [customerName, setCustomerName] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const issueRequestKey = useRef(createQueueRequestKey());

    const activeServices = services.filter((service) => service.is_active);
    const hasTickets = tickets.total > 0;
    const hasFilters =
        filters.search !== '' ||
        filters.status !== '' ||
        filters.queue_service_id !== null;

    const defaultPriorityId = () => {
        const normal = priorities.find((priority) => priority.code === 'normal');

        return normal ? String(normal.id) : (priorities[0] ? String(priorities[0].id) : '');
    };

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            `/${slug}/queue/tickets`,
            {
                search: next.search ?? search,
                status: next.status ?? filters.status,
                queue_service_id:
                    next.queue_service_id ?? filters.queue_service_id ?? '',
            },
            { preserveState: true, replace: true },
        );
    };

    const issueTicket = () => {
        if (!serviceId) {
            setErrors({ queue_service_id: 'Select a service.' });

            return;
        }

        router.post(
            store.url(slug),
            {
                queue_service_id: Number(serviceId),
                customer_name: customerName || null,
                source: 'staff',
                queue_priority_id:
                    priorityId === '' ? null : Number(priorityId),
                idempotency_key: issueRequestKey.current,
            },
            {
                ...stayOnPage(),
                onError: (next) => setErrors(next),
                onSuccess: () => {
                    issueRequestKey.current = createQueueRequestKey();
                    setOpen(false);
                    setServiceId('');
                    setPriorityId(defaultPriorityId());
                    setCustomerName('');
                    setErrors({});
                },
            },
        );
    };

    const confirmCancel = () => {
        if (!cancelling) {
            return;
        }

        router.post(cancel.url({ current_team: slug, queueTicket: cancelling.id }), {}, stayOnPage());
        setCancelling(null);
    };

    return (
        <>
            <QueuePageShell
                title="Tickets"
                description="Issue staff tickets, inspect waiting numbers, and cancel tickets that are still in line."
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        {tickets.total} ticket{tickets.total === 1 ? '' : 's'}
                    </p>
                    {permissions.canManageQueue && (
                        <Button
                            onClick={() => {
                                issueRequestKey.current = createQueueRequestKey();
                                setErrors({});
                                setPriorityId(defaultPriorityId());
                                setOpen(true);
                            }}
                            data-test="issue-queue-ticket"
                        >
                            <Ticket className="size-4" />
                            Issue ticket
                        </Button>
                    )}
                </div>

                {!hasTickets && !hasFilters ? (
                    <EmptyState
                        title="No tickets yet"
                        description="Issue a staff ticket for an active service. The next number uses that service's prefix and numbering period."
                        action={
                            permissions.canManageQueue ? (
                                <Button
                                    onClick={() => {
                                        setPriorityId(defaultPriorityId());
                                        setOpen(true);
                                    }}
                                    data-test="issue-queue-ticket-empty"
                                >
                                    Issue ticket
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <>
                        <form
                            className="dashboard-card flex flex-wrap items-center gap-3 px-4 py-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters({ search });
                            }}
                        >
                            <div className="relative min-w-56 flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search ticket number"
                                    aria-label="Search ticket number"
                                    className="pl-9"
                                />
                            </div>
                            <Select
                                value={
                                    filters.queue_service_id
                                        ? String(filters.queue_service_id)
                                        : 'all'
                                }
                                onValueChange={(value) =>
                                    applyFilters({
                                        queue_service_id:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger className="w-52">
                                    <SelectValue placeholder="Service" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All services
                                    </SelectItem>
                                    {services.map((service) => (
                                        <SelectItem
                                            key={service.id}
                                            value={String(service.id)}
                                        >
                                            {service.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        status: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>

                        {tickets.data.length === 0 ? (
                            <EmptyState
                                title="No matching tickets"
                                description="Try a different number, service, or status."
                            />
                        ) : (
                            <section className="dashboard-card overflow-x-auto">
                                <table className="w-full min-w-[880px] text-left text-sm">
                                    <thead className="border-b text-xs font-semibold tracking-wide uppercase">
                                        <tr>
                                            <th className="px-5 py-3">
                                                Number
                                            </th>
                                            <th className="px-3 py-3">
                                                Service
                                            </th>
                                            <th className="px-3 py-3">
                                                Status
                                            </th>
                                            <th className="px-3 py-3">
                                                Position
                                            </th>
                                            <th className="px-3 py-3">
                                                Source
                                            </th>
                                            <th className="px-3 py-3">
                                                Created
                                            </th>
                                            <th className="px-3 py-3">
                                                Priority
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tickets.data.map((ticket) => (
                                            <tr
                                                key={ticket.id}
                                                className="border-b last:border-0"
                                                data-test={`queue-ticket-${ticket.number}`}
                                            >
                                                <td className="px-5 py-3.5 font-mono text-sm font-semibold">
                                                    {ticket.number}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {ticket.service_name ??
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <Badge
                                                        variant={statusVariant(
                                                            ticket.status,
                                                        )}
                                                    >
                                                        {ticket.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {ticket.queue_position ??
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {ticket.source_label}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-3.5">
                                                    {formatCreated(
                                                        ticket.created_at,
                                                    )}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {ticket.priority_name ??
                                                        ticket.priority}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            data-test={`queue-ticket-journey-${ticket.number}`}
                                                            onClick={() =>
                                                                setJourney(ticket)
                                                            }
                                                        >
                                                            Journey
                                                        </Button>
                                                        {permissions.canCancelQueue &&
                                                            ticket.status ===
                                                                'waiting' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    data-test={`cancel-queue-ticket-${ticket.number}`}
                                                                    onClick={() =>
                                                                        setCancelling(
                                                                            ticket,
                                                                        )
                                                                    }
                                                                >
                                                                    Cancel
                                                                </Button>
                                                            )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>
                        )}

                        {tickets.last_page > 1 && (
                            <div className="flex flex-wrap gap-2">
                                {tickets.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                router.get(link.url, {}, { preserveState: true });
                                            }
                                        }}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </QueuePageShell>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Issue ticket</DialogTitle>
                        <DialogDescription>
                            Staff tickets use the selected service prefix. The
                            next number is assigned atomically for this
                            workspace.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label>Service</Label>
                            <Select
                                value={serviceId || undefined}
                                onValueChange={setServiceId}
                            >
                                <SelectTrigger
                                    className="w-full"
                                    data-test="issue-ticket-service"
                                >
                                    <SelectValue placeholder="Select a service" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activeServices.map((service) => (
                                        <SelectItem
                                            key={service.id}
                                            value={String(service.id)}
                                            data-test={`issue-ticket-service-${service.name}`}
                                        >
                                            {service.name} (
                                            {service.next_ticket_number})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.queue_service_id} />
                        </div>
                        {priorities.length > 0 && (
                            <div className="grid gap-2">
                                <Label>Priority</Label>
                                <Select
                                    value={priorityId || undefined}
                                    onValueChange={setPriorityId}
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        data-test="issue-ticket-priority"
                                    >
                                        <SelectValue placeholder="Normal" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((priority) => (
                                            <SelectItem
                                                key={priority.id}
                                                value={String(priority.id)}
                                                data-test={`issue-ticket-priority-${priority.name}`}
                                            >
                                                {priority.name} (
                                                {priority.weight})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.queue_priority_id} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="issue-ticket-customer">
                                Customer name (optional)
                            </Label>
                            <Input
                                id="issue-ticket-customer"
                                value={customerName}
                                onChange={(event) =>
                                    setCustomerName(event.target.value)
                                }
                            />
                            <InputError message={errors.customer_name} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => setOpen(false)}
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            data-test="issue-ticket-submit"
                            onClick={issueTicket}
                        >
                            Issue
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={cancelling !== null}
                title="Cancel ticket"
                description={
                    cancelling
                        ? `Cancel waiting ticket ${cancelling.number}? Remaining tickets for this service will move up.`
                        : ''
                }
                confirmLabel="Cancel ticket"
                onConfirm={confirmCancel}
                onOpenChange={(next) => {
                    if (!next) {
                        setCancelling(null);
                    }
                }}
            />

            <Sheet
                open={journey !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setJourney(null);
                    }
                }}
            >
                <SheetContent className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            {journey
                                ? `Ticket ${journey.number}`
                                : 'Ticket journey'}
                        </SheetTitle>
                        <SheetDescription>
                            Full history for this ticket. Transfers keep the
                            original number.
                        </SheetDescription>
                    </SheetHeader>
                    <ol className="flex flex-1 flex-col gap-4 overflow-y-auto px-4 pb-6">
                        {(journey?.events ?? []).map((event) => (
                            <li
                                key={event.id}
                                className="border-border relative border-l-2 pl-4"
                                data-test={`queue-ticket-event-${event.type}`}
                            >
                                <p className="text-sm font-medium">
                                    {event.type_label}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {formatCreated(event.created_at)}
                                    {event.user_name
                                        ? ` · ${event.user_name}`
                                        : ''}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {eventSummary(event)}
                                </p>
                            </li>
                        ))}
                        {journey !== null && journey.events.length === 0 && (
                            <li className="text-muted-foreground text-sm">
                                No journey events recorded.
                            </li>
                        )}
                    </ol>
                </SheetContent>
            </Sheet>
        </>
    );
}

QueueTickets.layout = queueBreadcrumbs('Tickets', '/tickets');
