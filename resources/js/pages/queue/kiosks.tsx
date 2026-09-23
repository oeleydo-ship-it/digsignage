import { router, usePage } from '@inertiajs/react';
import { MonitorSmartphone, Search } from 'lucide-react';
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
import type { QueueKioskRecord, QueuePermissions } from '@/types';

type LocationOption = { id: number; name: string; depth: number };

type Props = {
    kiosks: QueueKioskRecord[];
    locations: LocationOption[];
    permissions: QueuePermissions;
};

const emptyForm = {
    name: '',
    location_id: '',
    printer_enabled: false,
    is_active: true,
    pin: '',
    clear_pin: false,
    branding: {
        logo_url: '',
        title: '',
        footer: '',
        print_qr_code: true,
        colors: {
            background: '#0f172a',
            primary: '#2563eb',
            text: '#f8fafc',
            button_text: '#ffffff',
        },
    },
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

export default function QueueKiosks({ kiosks, locations, permissions }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<QueueKioskRecord | null>(null);
    const [deleting, setDeleting] = useState<QueueKioskRecord | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm);
    const [search, setSearch] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (q === '') {
            return kiosks;
        }

        return kiosks.filter((kiosk) =>
            [kiosk.name, kiosk.location_name ?? '']
                .join(' ')
                .toLowerCase()
                .includes(q),
        );
    }, [kiosks, search]);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (kiosk: QueueKioskRecord) => {
        setEditing(kiosk);
        setForm({
            name: kiosk.name,
            location_id: kiosk.location_id ? String(kiosk.location_id) : '',
            printer_enabled: kiosk.printer_enabled,
            is_active: kiosk.is_active,
            pin: '',
            clear_pin: false,
            branding: {
                logo_url: kiosk.branding.logo_url ?? '',
                title: kiosk.branding.title ?? '',
                footer: kiosk.branding.footer ?? '',
                print_qr_code: kiosk.branding.print_qr_code,
                colors: { ...kiosk.branding.colors },
            },
        });
        setErrors({});
        setOpen(true);
    };

    const payload = () => ({
        name: form.name,
        location_id: form.location_id === '' ? null : Number(form.location_id),
        printer_enabled: form.printer_enabled,
        is_active: form.is_active,
        pin: form.pin === '' ? null : form.pin,
        clear_pin: form.clear_pin,
        branding: {
            logo_url: form.branding.logo_url || null,
            title: form.branding.title || null,
            footer: form.branding.footer || null,
            print_qr_code: form.branding.print_qr_code,
            colors: form.branding.colors,
        },
    });

    const save = () => {
        const options = {
            ...stayOnPage(),
            onError: (next: Record<string, string>) => setErrors(next),
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            router.patch(
                `/${slug}/queue/kiosks/${editing.id}`,
                payload(),
                options,
            );

            return;
        }

        router.post(`/${slug}/queue/kiosks`, payload(), options);
    };

    return (
        <>
            <QueuePageShell
                title="Kiosks"
                description="Branded self-service tablets that issue tickets without the DigSignage admin chrome."
            >
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        Open the kiosk URL on a tablet. The token in the URL is
                        the session — staff login is not required on that
                        screen.
                    </p>
                    {permissions.canManageKiosks && (
                        <Button
                            onClick={openCreate}
                            data-test="create-queue-kiosk"
                        >
                            <MonitorSmartphone className="size-4" />
                            Add kiosk
                        </Button>
                    )}
                </div>

                {kiosks.length === 0 ? (
                    <EmptyState
                        title="No kiosks yet"
                        description="Create a lobby kiosk, set branding, then open the serve URL on a tablet."
                        action={
                            permissions.canManageKiosks ? (
                                <Button
                                    onClick={openCreate}
                                    data-test="create-queue-kiosk-empty"
                                >
                                    Add kiosk
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
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name or location"
                                    aria-label="Search kiosks"
                                    className="pl-9"
                                />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {`${filtered.length} kiosk${filtered.length === 1 ? '' : 's'}`}
                            </p>
                        </div>

                        {filtered.length === 0 ? (
                            <p className="text-muted-foreground dashboard-card px-5 py-12 text-center text-sm">
                                No kiosks match that search.
                            </p>
                        ) : (
                            <section className="dashboard-card overflow-x-auto">
                                <table className="w-full min-w-[720px] text-left text-sm">
                                    <thead className="border-b text-xs tracking-wide uppercase">
                                        <tr className="text-muted-foreground">
                                            <th className="px-5 py-3 font-medium">
                                                Kiosk
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Location
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Printer
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
                                        {filtered.map((kiosk) => (
                                            <tr
                                                key={kiosk.id}
                                                data-test={`queue-kiosk-row-${kiosk.id}`}
                                            >
                                                <td className="px-5 py-3.5 font-medium">
                                                    {kiosk.name}
                                                </td>
                                                <td className="text-muted-foreground px-3 py-3.5">
                                                    {kiosk.location_name ??
                                                        'All locations'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    {kiosk.printer_enabled
                                                        ? 'Enabled'
                                                        : 'Off'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <Badge variant="secondary">
                                                        {kiosk.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {kiosk.is_active && (
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        kiosk.serve_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    data-test={`open-queue-kiosk-${kiosk.id}`}
                                                                >
                                                                    Open kiosk
                                                                </a>
                                                            </Button>
                                                        )}
                                                        {permissions.canManageKiosks && (
                                                            <>
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            kiosk,
                                                                        )
                                                                    }
                                                                    data-test={`edit-queue-kiosk-${kiosk.id}`}
                                                                >
                                                                    Edit
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setDeleting(
                                                                            kiosk,
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
                            {editing ? 'Edit kiosk' : 'Add kiosk'}
                        </DialogTitle>
                        <DialogDescription>
                            Branding appears on the full-screen tablet. Optional
                            PIN is stored hashed for later session locks.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="kiosk-name">Name</Label>
                            <Input
                                id="kiosk-name"
                                data-test="queue-kiosk-name"
                                value={form.name}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Location</Label>
                            <Select
                                value={form.location_id || 'none'}
                                onValueChange={(value) =>
                                    setForm((current) => ({
                                        ...current,
                                        location_id:
                                            value === 'none' ? '' : value,
                                    }))
                                }
                            >
                                <SelectTrigger data-test="queue-kiosk-location">
                                    <SelectValue placeholder="Location" />
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
                        <div className="grid gap-2">
                            <Label htmlFor="kiosk-title">Welcome title</Label>
                            <Input
                                id="kiosk-title"
                                data-test="queue-kiosk-title"
                                value={form.branding.title}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        branding: {
                                            ...current.branding,
                                            title: event.target.value,
                                        },
                                    }))
                                }
                                placeholder="WELCOME"
                            />
                            <InputError message={errors['branding.title']} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kiosk-footer">Print footer</Label>
                            <Input
                                id="kiosk-footer"
                                data-test="queue-kiosk-footer"
                                value={form.branding.footer}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        branding: {
                                            ...current.branding,
                                            footer: event.target.value,
                                        },
                                    }))
                                }
                                placeholder="Thank you for visiting"
                            />
                            <InputError message={errors['branding.footer']} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kiosk-logo">Logo URL</Label>
                            <Input
                                id="kiosk-logo"
                                value={form.branding.logo_url}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        branding: {
                                            ...current.branding,
                                            logo_url: event.target.value,
                                        },
                                    }))
                                }
                            />
                            <InputError message={errors['branding.logo_url']} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="kiosk-color-bg">
                                    Background
                                </Label>
                                <Input
                                    id="kiosk-color-bg"
                                    data-test="queue-kiosk-color-background"
                                    value={form.branding.colors.background}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            branding: {
                                                ...current.branding,
                                                colors: {
                                                    ...current.branding.colors,
                                                    background:
                                                        event.target.value,
                                                },
                                            },
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kiosk-color-primary">
                                    Button color
                                </Label>
                                <Input
                                    id="kiosk-color-primary"
                                    data-test="queue-kiosk-color-primary"
                                    value={form.branding.colors.primary}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            branding: {
                                                ...current.branding,
                                                colors: {
                                                    ...current.branding.colors,
                                                    primary: event.target.value,
                                                },
                                            },
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kiosk-color-text">Text</Label>
                                <Input
                                    id="kiosk-color-text"
                                    value={form.branding.colors.text}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            branding: {
                                                ...current.branding,
                                                colors: {
                                                    ...current.branding.colors,
                                                    text: event.target.value,
                                                },
                                            },
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kiosk-color-button-text">
                                    Button text
                                </Label>
                                <Input
                                    id="kiosk-color-button-text"
                                    value={form.branding.colors.button_text}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            branding: {
                                                ...current.branding,
                                                colors: {
                                                    ...current.branding.colors,
                                                    button_text:
                                                        event.target.value,
                                                },
                                            },
                                        }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kiosk-pin">
                                PIN{' '}
                                {editing?.has_pin
                                    ? '(leave blank to keep)'
                                    : ''}
                            </Label>
                            <Input
                                id="kiosk-pin"
                                type="password"
                                autoComplete="off"
                                value={form.pin}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        pin: event.target.value,
                                    }))
                                }
                            />
                            {editing?.has_pin && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={form.clear_pin}
                                        onCheckedChange={(checked) =>
                                            setForm((current) => ({
                                                ...current,
                                                clear_pin: checked === true,
                                            }))
                                        }
                                    />
                                    Remove PIN
                                </label>
                            )}
                            <InputError message={errors.pin} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.printer_enabled}
                                onCheckedChange={(checked) =>
                                    setForm((current) => ({
                                        ...current,
                                        printer_enabled: checked === true,
                                    }))
                                }
                                data-test="queue-kiosk-printer"
                            />
                            Enable ticket print (browser print dialog)
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.branding.print_qr_code}
                                onCheckedChange={(checked) =>
                                    setForm((current) => ({
                                        ...current,
                                        branding: {
                                            ...current.branding,
                                            print_qr_code: checked === true,
                                        },
                                    }))
                                }
                                data-test="queue-kiosk-print-qr"
                            />
                            Include a QR code on printed tickets
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.is_active}
                                onCheckedChange={(checked) =>
                                    setForm((current) => ({
                                        ...current,
                                        is_active: checked === true,
                                    }))
                                }
                                data-test="queue-kiosk-active"
                            />
                            Active
                        </label>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={save} data-test="save-queue-kiosk">
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                title="Delete this kiosk?"
                description="The tablet URL will stop working. Existing tickets are kept."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(`/${slug}/queue/kiosks/${deleting.id}`, {
                        ...stayOnPage(),
                        onSuccess: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

QueueKiosks.layout = queueBreadcrumbs('Kiosks', '/kiosks');
