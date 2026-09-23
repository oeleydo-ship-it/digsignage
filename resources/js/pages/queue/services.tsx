import { router, usePage } from '@inertiajs/react';
import { QrCode, Search, Ticket } from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    QueuePageShell,
    queueBreadcrumbs,
} from '@/pages/queue/queue-page-shell';
import { destroy, store, update } from '@/routes/queue/services';
import type {
    QueueNumberingResetValue,
    QueueOpeningHours,
    QueuePermissions,
    QueueServiceRecord,
    QueueStrategyValue,
} from '@/types';

type Option = { value: string; label: string };
type LocationOption = {
    id: number;
    name: string;
    depth: number;
    join_url: string;
};

type Props = {
    services: QueueServiceRecord[];
    filters: {
        search: string;
        status: string;
        location_id: number | null;
    };
    locations: LocationOption[];
    numberingResets: Option[];
    strategies: Option[];
    permissions: QueuePermissions;
    virtualJoinUrl: string;
};

const WEEKDAYS: { key: keyof QueueOpeningHours; label: string }[] = [
    { key: 'mon', label: 'Mon' },
    { key: 'tue', label: 'Tue' },
    { key: 'wed', label: 'Wed' },
    { key: 'thu', label: 'Thu' },
    { key: 'fri', label: 'Fri' },
    { key: 'sat', label: 'Sat' },
    { key: 'sun', label: 'Sun' },
];

function defaultHours(): QueueOpeningHours {
    const day = (closed: boolean) => ({
        open: '09:00',
        close: '17:00',
        closed,
    });

    return {
        mon: day(false),
        tue: day(false),
        wed: day(false),
        thu: day(false),
        fri: day(false),
        sat: day(true),
        sun: day(true),
    };
}

const emptyForm = {
    name: '',
    code: '',
    ticket_prefix: '',
    description: '',
    location_id: '',
    display_color: '#2563eb',
    average_service_duration_minutes: '5',
    max_queue_capacity: '',
    numbering_reset: 'daily' as QueueNumberingResetValue,
    queue_strategy: 'fifo' as QueueStrategyValue,
    default_priority: '0',
    is_active: true,
    opening_hours: defaultHours(),
};

type FormState = typeof emptyForm;

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: {
            'X-Stay-On-Page': window.location.href,
        },
    };
}

export default function QueueServices({
    services,
    filters,
    locations,
    numberingResets,
    strategies,
    permissions,
    virtualJoinUrl,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<QueueServiceRecord | null>(null);
    const [deleting, setDeleting] = useState<QueueServiceRecord | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [search, setSearch] = useState(filters.search);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [qrOpen, setQrOpen] = useState(false);
    const [qrUrl, setQrUrl] = useState(virtualJoinUrl);
    const [qrLabel, setQrLabel] = useState('All services');

    const openQr = (label: string, url: string) => {
        setQrLabel(label);
        setQrUrl(url);
        setQrOpen(true);
    };

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            `/${slug}/queue/services`,
            {
                search: next.search ?? search,
                status: next.status ?? filters.status,
                location_id: next.location_id ?? filters.location_id ?? '',
            },
            { preserveState: true, replace: true },
        );
    };

    const visibleCount = useMemo(() => services.length, [services]);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (service: QueueServiceRecord) => {
        setEditing(service);
        setForm({
            name: service.name,
            code: service.code,
            ticket_prefix: service.ticket_prefix,
            description: service.description ?? '',
            location_id: service.location_id ? String(service.location_id) : '',
            display_color: service.display_color,
            average_service_duration_minutes: String(
                Math.max(
                    1,
                    Math.round(service.average_service_duration_seconds / 60),
                ),
            ),
            max_queue_capacity: service.max_queue_capacity
                ? String(service.max_queue_capacity)
                : '',
            numbering_reset: service.numbering_reset,
            queue_strategy: service.queue_strategy,
            default_priority: String(service.default_priority),
            is_active: service.is_active,
            opening_hours: service.opening_hours ?? defaultHours(),
        });
        setErrors({});
        setOpen(true);
    };

    const payload = () => ({
        name: form.name,
        code: form.code,
        ticket_prefix: form.ticket_prefix,
        description: form.description === '' ? null : form.description,
        location_id: form.location_id === '' ? null : Number(form.location_id),
        display_color: form.display_color,
        average_service_duration_seconds:
            Number(form.average_service_duration_minutes) * 60,
        max_queue_capacity:
            form.max_queue_capacity === ''
                ? null
                : Number(form.max_queue_capacity),
        numbering_reset: form.numbering_reset,
        queue_strategy: form.queue_strategy,
        default_priority: Number(form.default_priority),
        priority_rules: [],
        is_active: form.is_active,
        opening_hours: form.opening_hours,
    });

    const onError = (next: Record<string, string>) => {
        setErrors(next);
    };

    const save = () => {
        if (editing) {
            router.patch(update.url([slug, editing.id]), payload(), {
                ...stayOnPage(),
                onError,
                onSuccess: () => setOpen(false),
            });

            return;
        }

        router.post(store.url(slug), payload(), {
            ...stayOnPage(),
            onError,
            onSuccess: () => setOpen(false),
        });
    };

    const toggleActive = (service: QueueServiceRecord) => {
        router.patch(
            update.url([slug, service.id]),
            {
                name: service.name,
                code: service.code,
                ticket_prefix: service.ticket_prefix,
                description: service.description,
                location_id: service.location_id,
                display_color: service.display_color,
                average_service_duration_seconds:
                    service.average_service_duration_seconds,
                max_queue_capacity: service.max_queue_capacity,
                numbering_reset: service.numbering_reset,
                queue_strategy: service.queue_strategy,
                default_priority: service.default_priority,
                priority_rules: [],
                is_active: !service.is_active,
                opening_hours: service.opening_hours,
            },
            stayOnPage(),
        );
    };

    return (
        <>
            <QueuePageShell
                title="Services"
                description="Define the queues customers join — registration, billing, pharmacy, and more. Counters attach to services in Phase 5."
            >
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        Ticket labels look like A001. Numbering can reset daily,
                        weekly, monthly, or never.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                openQr('All services', virtualJoinUrl)
                            }
                            data-test="queue-virtual-qr"
                        >
                            <QrCode className="size-4" />
                            Join QR codes
                        </Button>
                        {permissions.canManageServices && (
                            <Button
                                onClick={openCreate}
                                data-test="create-queue-service"
                            >
                                <Ticket className="size-4" />
                                Add service
                            </Button>
                        )}
                    </div>
                </div>

                {services.length === 0 &&
                filters.search === '' &&
                filters.status === '' &&
                !filters.location_id ? (
                    <EmptyState
                        title="No queue services yet"
                        description="Create Registration, Billing, or any other desk customers wait for. Ticket prefixes stay unique inside this workspace."
                        action={
                            permissions.canManageServices ? (
                                <Button
                                    onClick={openCreate}
                                    data-test="create-queue-service-empty"
                                >
                                    Add service
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
                                    placeholder="Search name, code, or prefix"
                                    aria-label="Search services"
                                    className="pl-9"
                                />
                            </div>
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
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={
                                    filters.location_id
                                        ? String(filters.location_id)
                                        : 'all'
                                }
                                onValueChange={(value) =>
                                    applyFilters({
                                        location_id:
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                    })
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="Location" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All locations
                                    </SelectItem>
                                    {locations.map((location) => (
                                        <SelectItem
                                            key={location.id}
                                            value={String(location.id)}
                                        >
                                            {`${'— '.repeat(location.depth)}${location.name}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit" variant="secondary">
                                Filter
                            </Button>
                            <p className="text-muted-foreground text-sm">
                                {`${visibleCount} service${visibleCount === 1 ? '' : 's'}`}
                            </p>
                        </form>

                        {services.length === 0 ? (
                            <p className="text-muted-foreground dashboard-card px-5 py-12 text-center text-sm">
                                No services match those filters.
                            </p>
                        ) : (
                            <section className="dashboard-card overflow-x-auto">
                                <table className="w-full min-w-[720px] text-left text-sm">
                                    <thead className="border-b text-xs tracking-wide uppercase">
                                        <tr className="text-muted-foreground">
                                            <th className="px-5 py-3 font-medium">
                                                Service
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Code
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Prefix
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Location
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Color
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Reset
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Strategy
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Next ticket
                                            </th>
                                            <th className="px-5 py-3 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {services.map((service) => (
                                            <tr
                                                key={service.id}
                                                data-test={`queue-service-row-${service.id}`}
                                            >
                                                <td className="px-5 py-3.5 font-medium">
                                                    {service.name}
                                                </td>
                                                <td className="px-3 py-3.5 font-mono text-xs">
                                                    {service.code}
                                                </td>
                                                <td className="px-3 py-3.5 font-mono text-xs">
                                                    {service.ticket_prefix}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-3.5">
                                                    {service.location_name ??
                                                        'All locations'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <span
                                                        className="inline-block size-4 rounded-full border"
                                                        style={{
                                                            backgroundColor:
                                                                service.display_color,
                                                        }}
                                                        title={
                                                            service.display_color
                                                        }
                                                    />
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <Badge
                                                        variant={
                                                            service.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {service.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {
                                                        service.numbering_reset_label
                                                    }
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {
                                                        service.queue_strategy_label
                                                    }
                                                </td>
                                                <td className="px-5 py-3.5 font-mono text-xs">
                                                    {service.next_ticket_number}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        {service.is_active ? (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    openQr(
                                                                        service.name,
                                                                        service.join_url,
                                                                    )
                                                                }
                                                            >
                                                                QR
                                                            </Button>
                                                        ) : null}
                                                        {permissions.canManageServices && (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            service,
                                                                        )
                                                                    }
                                                                >
                                                                    Edit
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        toggleActive(
                                                                            service,
                                                                        )
                                                                    }
                                                                >
                                                                    {service.is_active
                                                                        ? 'Deactivate'
                                                                        : 'Activate'}
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    onClick={() =>
                                                                        setDeleting(
                                                                            service,
                                                                        )
                                                                    }
                                                                >
                                                                    Delete
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </section>
                        )}
                    </>
                )}
            </QueuePageShell>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit service' : 'Add service'}
                        </DialogTitle>
                        <DialogDescription>
                            Prefixes and internal codes must be unique in this
                            workspace. Counters attach in Phase 5.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="queue-service-name">Name</Label>
                            <Input
                                id="queue-service-name"
                                value={form.name}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        name: event.target.value,
                                    })
                                }
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-code">Code</Label>
                                <Input
                                    id="queue-service-code"
                                    value={form.code}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            code: event.target.value.toUpperCase(),
                                        })
                                    }
                                    placeholder="REG"
                                    required
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-prefix">
                                    Ticket prefix
                                </Label>
                                <Input
                                    id="queue-service-prefix"
                                    value={form.ticket_prefix}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            ticket_prefix:
                                                event.target.value.toUpperCase(),
                                        })
                                    }
                                    placeholder="A"
                                    required
                                />
                                <InputError message={errors.ticket_prefix} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="queue-service-description">
                                Description
                            </Label>
                            <Input
                                id="queue-service-description"
                                value={form.description}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        description: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Location</Label>
                            <Select
                                value={form.location_id || 'none'}
                                onValueChange={(value) =>
                                    setForm({
                                        ...form,
                                        location_id:
                                            value === 'none' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="All locations" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        All locations
                                    </SelectItem>
                                    {locations.map((location) => (
                                        <SelectItem
                                            key={location.id}
                                            value={String(location.id)}
                                        >
                                            {`${'— '.repeat(location.depth)}${location.name}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.location_id} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-duration">
                                    Avg. minutes
                                </Label>
                                <Input
                                    id="queue-service-duration"
                                    type="number"
                                    min={1}
                                    value={
                                        form.average_service_duration_minutes
                                    }
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            average_service_duration_minutes:
                                                event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={
                                        errors.average_service_duration_seconds
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-capacity">
                                    Max capacity
                                </Label>
                                <Input
                                    id="queue-service-capacity"
                                    type="number"
                                    min={1}
                                    value={form.max_queue_capacity}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            max_queue_capacity:
                                                event.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label>Numbering reset</Label>
                                <Select
                                    value={form.numbering_reset}
                                    onValueChange={(value) =>
                                        setForm({
                                            ...form,
                                            numbering_reset:
                                                value as QueueNumberingResetValue,
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {numberingResets.map((reset) => (
                                            <SelectItem
                                                key={reset.value}
                                                value={reset.value}
                                            >
                                                {reset.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Queue strategy</Label>
                                <Select
                                    value={form.queue_strategy}
                                    onValueChange={(value) =>
                                        setForm({
                                            ...form,
                                            queue_strategy:
                                                value as QueueStrategyValue,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        data-test="queue-service-strategy"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {strategies.map((strategy) => (
                                            <SelectItem
                                                key={strategy.value}
                                                value={strategy.value}
                                            >
                                                {strategy.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-priority">
                                    Default priority
                                </Label>
                                <Input
                                    id="queue-service-priority"
                                    type="number"
                                    min={0}
                                    value={form.default_priority}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            default_priority:
                                                event.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="queue-service-color">
                                    Display color
                                </Label>
                                <Input
                                    id="queue-service-color"
                                    type="color"
                                    value={form.display_color}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            display_color: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 self-end pb-2 text-sm">
                                <Checkbox
                                    checked={form.is_active}
                                    onCheckedChange={(checked) =>
                                        setForm({
                                            ...form,
                                            is_active: checked === true,
                                        })
                                    }
                                />
                                Active
                            </label>
                        </div>
                        <div className="grid gap-2">
                            <Label>Opening hours</Label>
                            <div className="space-y-2 rounded-lg border p-3">
                                {WEEKDAYS.map(({ key, label }) => {
                                    const day = form.opening_hours[key];

                                    return (
                                        <div
                                            key={key}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <span className="w-10 text-xs font-medium">
                                                {label}
                                            </span>
                                            <Checkbox
                                                checked={!day.closed}
                                                onCheckedChange={(checked) =>
                                                    setForm({
                                                        ...form,
                                                        opening_hours: {
                                                            ...form.opening_hours,
                                                            [key]: {
                                                                ...day,
                                                                closed:
                                                                    checked !==
                                                                    true,
                                                            },
                                                        },
                                                    })
                                                }
                                                aria-label={`${label} open`}
                                            />
                                            <Input
                                                type="time"
                                                className="h-8 w-28"
                                                disabled={day.closed}
                                                value={day.open}
                                                onChange={(event) =>
                                                    setForm({
                                                        ...form,
                                                        opening_hours: {
                                                            ...form.opening_hours,
                                                            [key]: {
                                                                ...day,
                                                                open: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        },
                                                    })
                                                }
                                            />
                                            <Input
                                                type="time"
                                                className="h-8 w-28"
                                                disabled={day.closed}
                                                value={day.close}
                                                onChange={(event) =>
                                                    setForm({
                                                        ...form,
                                                        opening_hours: {
                                                            ...form.opening_hours,
                                                            [key]: {
                                                                ...day,
                                                                close: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        },
                                                    })
                                                }
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" onClick={save}>
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={qrOpen} onOpenChange={setQrOpen}>
                <DialogContent className="queue-qr-print sm:max-w-lg">
                    <style>
                        {
                            '@media print { body * { visibility: hidden !important; } .queue-qr-print, .queue-qr-print * { visibility: visible !important; } .queue-qr-print { position: fixed !important; inset: 0 !important; border: 0 !important; box-shadow: none !important; } }'
                        }
                    </style>
                    <DialogHeader>
                        <DialogTitle>Virtual queue QR codes</DialogTitle>
                        <DialogDescription>
                            Print or share a workspace, location, or service
                            link. Customers can scan it to join from their
                            phone.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-5">
                        <div className="flex justify-center rounded-2xl bg-white p-5">
                            <img
                                src={
                                    'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=8&data=' +
                                    encodeURIComponent(qrUrl)
                                }
                                alt={'QR code for ' + qrLabel}
                                className="size-56"
                            />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">{qrLabel}</p>
                            <Input
                                readOnly
                                value={qrUrl}
                                className="mt-2 font-mono text-xs"
                                onFocus={(event) => event.target.select()}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant={
                                    qrUrl === virtualJoinUrl
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    openQr('All services', virtualJoinUrl)
                                }
                            >
                                Workspace
                            </Button>
                            {locations.map((location) => (
                                <Button
                                    key={location.id}
                                    size="sm"
                                    variant={
                                        qrUrl === location.join_url
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        openQr(location.name, location.join_url)
                                    }
                                >
                                    {location.name}
                                </Button>
                            ))}
                            {services
                                .filter((service) => service.is_active)
                                .map((service) => (
                                    <Button
                                        key={service.id}
                                        size="sm"
                                        variant={
                                            qrUrl === service.join_url
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            openQr(
                                                service.name,
                                                service.join_url,
                                            )
                                        }
                                    >
                                        {service.name}
                                    </Button>
                                ))}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() =>
                                void navigator.clipboard.writeText(qrUrl)
                            }
                        >
                            Copy link
                        </Button>
                        <Button onClick={() => window.print()}>Print QR</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete service"
                description="This queue service will be removed. Tickets are not issued yet, so nothing else is deleted."
                confirmLabel="Delete"
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => {
                    if (!deleting || !slug) {
                        return;
                    }

                    router.delete(destroy.url([slug, deleting.id]), {
                        ...stayOnPage(),
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

QueueServices.layout = queueBreadcrumbs('Services', '/services');
