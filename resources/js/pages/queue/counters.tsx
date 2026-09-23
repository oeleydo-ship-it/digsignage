import { Link, router, usePage } from '@inertiajs/react';
import { Monitor, Search } from 'lucide-react';
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
import { QueuePageShell, queueBreadcrumbs } from '@/pages/queue/queue-page-shell';
import type {
    QueueCounterMemberOption,
    QueueCounterRecord,
    QueueCounterServiceOption,
    QueueCounterStatusValue,
    QueuePermissions,
} from '@/types';

type LocationOption = { id: number; name: string; depth: number };
type Option = { value: string; label: string };

type Props = {
    counters: QueueCounterRecord[];
    locations: LocationOption[];
    services: QueueCounterServiceOption[];
    members: QueueCounterMemberOption[];
    statuses: Option[];
    permissions: QueuePermissions;
};

const emptyForm = {
    name: '',
    code: '',
    location_id: '',
    assigned_user_id: '',
    status: 'open' as QueueCounterStatusValue,
    service_ids: [] as number[],
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

export default function QueueCounters({
    counters,
    locations,
    services,
    members,
    statuses,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<QueueCounterRecord | null>(null);
    const [deleting, setDeleting] = useState<QueueCounterRecord | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [search, setSearch] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const compatibleServices = services.filter(
        (service) =>
            form.location_id === '' ||
            service.location_id == null ||
            service.location_id === Number(form.location_id),
    );

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (q === '') {
            return counters;
        }

        return counters.filter((counter) =>
            [counter.name, counter.code, counter.location_name ?? '']
                .join(' ')
                .toLowerCase()
                .includes(q),
        );
    }, [counters, search]);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (counter: QueueCounterRecord) => {
        setEditing(counter);
        setForm({
            name: counter.name,
            code: counter.code,
            location_id: counter.location_id ? String(counter.location_id) : '',
            assigned_user_id: counter.assigned_user_id
                ? String(counter.assigned_user_id)
                : '',
            status: counter.status,
            service_ids: [...counter.service_ids],
        });
        setErrors({});
        setOpen(true);
    };

    const payload = () => ({
        name: form.name,
        code: form.code,
        location_id: form.location_id === '' ? null : Number(form.location_id),
        assigned_user_id:
            form.assigned_user_id === '' ? null : Number(form.assigned_user_id),
        status: form.status,
        service_ids: form.service_ids,
    });

    const save = () => {
        if (processing) {
            return;
        }

        setErrors({});
        const options = {
            ...stayOnPage(),
            onStart: () => setProcessing(true),
            onError: (next: Record<string, string>) => setErrors(next),
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(`/${slug}/queue/counters/${editing.id}`, payload(), options);

            return;
        }

        router.post(`/${slug}/queue/counters`, payload(), options);
    };

    const toggleService = (id: number) => {
        setForm((current) => ({
            ...current,
            service_ids: current.service_ids.includes(id)
                ? current.service_ids.filter((value) => value !== id)
                : [...current.service_ids, id],
        }));
    };

    return (
        <>
            <QueuePageShell
                title="Counters"
                description="Open desks, assign staff, and choose which services each counter can take."
            >
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        Staff can open a focused desk view to call the next ticket
                        without the full admin sidebar.
                    </p>
                    {permissions.canManageCounters && (
                        <Button onClick={openCreate} data-test="create-queue-counter">
                            <Monitor className="size-4" />
                            Add counter
                        </Button>
                    )}
                </div>

                {counters.length === 0 ? (
                    <EmptyState
                        title="No counters yet"
                        description="Create Counter 01 and attach it to Registration or any other service. Assigned staff can then open the desk."
                        action={
                            permissions.canManageCounters ? (
                                <Button
                                    onClick={openCreate}
                                    data-test="create-queue-counter-empty"
                                >
                                    Add counter
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <>
                        <div className="dashboard-card flex flex-wrap items-center gap-3 px-4 py-3">
                            <div className="relative min-w-56 flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search name or code"
                                    aria-label="Search counters"
                                    className="pl-9"
                                />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {`${filtered.length} counter${filtered.length === 1 ? '' : 's'}`}
                            </p>
                        </div>

                        {filtered.length === 0 ? (
                            <p className="text-muted-foreground dashboard-card px-5 py-12 text-center text-sm">
                                No counters match that search.
                            </p>
                        ) : (
                            <section className="dashboard-card overflow-x-auto">
                                <table className="w-full min-w-[720px] text-left text-sm">
                                    <thead className="border-b text-xs tracking-wide uppercase">
                                        <tr className="text-muted-foreground">
                                            <th className="px-5 py-3 font-medium">
                                                Counter
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Code
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Location
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Services
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Staff
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-5 py-3 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {filtered.map((counter) => (
                                            <tr
                                                key={counter.id}
                                                data-test={`queue-counter-row-${counter.id}`}
                                            >
                                                <td className="px-5 py-3.5 font-medium">
                                                    {counter.name}
                                                </td>
                                                <td className="px-3 py-3.5 font-mono text-xs">
                                                    {counter.code}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-3.5">
                                                    {counter.location_name ??
                                                        'All locations'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {counter.services.length === 0
                                                        ? '—'
                                                        : counter.services
                                                              .map((service) => service.name)
                                                              .join(', ')}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {counter.assigned_user_name ?? 'Unassigned'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <Badge variant="secondary">
                                                        {counter.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {counter.can_operate && (
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/${slug}/queue/counters/${counter.id}/desk`}
                                                                    data-test={`open-queue-desk-${counter.id}`}
                                                                >
                                                                    Open desk
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        {permissions.canManageCounters && (
                                                            <>
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        openEdit(counter)
                                                                    }
                                                                    data-test={`edit-queue-counter-${counter.id}`}
                                                                >
                                                                    Edit
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setDeleting(counter)
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
                            {editing ? 'Edit counter' : 'Add counter'}
                        </DialogTitle>
                        <DialogDescription>
                            One counter can support several services. Assigned staff
                            can operate the desk even without full queue admin.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="counter-name">Name</Label>
                            <Input
                                id="counter-name"
                                value={form.name}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                                data-test="queue-counter-name"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="counter-code">Code</Label>
                            <Input
                                id="counter-code"
                                value={form.code}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        code: event.target.value,
                                    }))
                                }
                                data-test="queue-counter-code"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Location</Label>
                            <Select
                                value={form.location_id || 'none'}
                                onValueChange={(value) =>
                                    setForm((current) => {
                                        const locationId = value === 'none' ? '' : value;

                                        return {
                                            ...current,
                                            location_id: locationId,
                                            service_ids: current.service_ids.filter(
                                                (id) => {
                                                    const service = services.find(
                                                        (item) => item.id === id,
                                                    );

                                                    return (
                                                        locationId === '' ||
                                                        service?.location_id == null ||
                                                        service.location_id === Number(locationId)
                                                    );
                                                },
                                            ),
                                        };
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Location" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">All locations</SelectItem>
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
                        <div className="grid gap-2">
                            <Label>Assigned staff</Label>
                            <Select
                                value={form.assigned_user_id || 'none'}
                                onValueChange={(value) =>
                                    setForm((current) => ({
                                        ...current,
                                        assigned_user_id:
                                            value === 'none' ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Staff" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Unassigned</SelectItem>
                                    {members.map((member) => (
                                        <SelectItem
                                            key={member.id}
                                            value={String(member.id)}
                                        >
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.assigned_user_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <Select
                                value={form.status}
                                onValueChange={(value) =>
                                    setForm((current) => ({
                                        ...current,
                                        status: value as QueueCounterStatusValue,
                                    }))
                                }
                            >
                                <SelectTrigger data-test="queue-counter-status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                            <InputError message={errors.status} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Services</Label>
                            <div className="grid gap-2 rounded-lg border p-3">
                                {compatibleServices.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Create a queue service first, then attach it
                                        here.
                                    </p>
                                ) : (
                                    compatibleServices.map((service) => (
                                        <label
                                            key={service.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.service_ids.includes(
                                                    service.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleService(service.id)
                                                }
                                                data-test={`queue-counter-service-${service.id}`}
                                            />
                                            {service.name}
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {service.code}
                                            </span>
                                        </label>
                                    ))
                                )}
                            </div>
                            <InputError message={errors.service_ids} />
                        </div>
                    </div>
                    {Object.keys(errors).length > 0 && (
                        <p role="alert" className="text-destructive text-sm">
                            Could not save the counter. Check the highlighted fields and try again.
                        </p>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={save} disabled={processing} data-test="save-queue-counter">
                            {processing ? 'Saving…' : 'Save'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete this counter?"
                description="Tickets keep their history. The desk will no longer be available."
                confirmLabel="Delete"
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(`/${slug}/queue/counters/${deleting.id}`, {
                        ...stayOnPage(),
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

QueueCounters.layout = queueBreadcrumbs('Counters', '/counters');
