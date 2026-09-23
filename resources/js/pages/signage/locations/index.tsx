import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Globe,
    Layers,
    MapPin,
    MapPinned,
    Monitor,
    Plus,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
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
import { dashboard } from '@/routes';
import { destroy, index, store, update } from '@/routes/locations';
import type {
    LocationTypeValue,
    SignageLocation,
    SignagePermissions,
} from '@/types';

type Option = { value: string; label: string };

type Props = {
    locations: SignageLocation[];
    types: Option[];
    permissions: SignagePermissions;
};

const emptyForm = {
    name: '',
    type: 'city',
    parent_id: '',
    description: '',
    address: '',
    timezone: '',
};

const NEXT_TYPE: Record<LocationTypeValue, LocationTypeValue> = {
    country: 'city',
    city: 'building',
    building: 'floor',
    floor: 'area',
    area: 'area',
};

function typeIcon(type: LocationTypeValue) {
    switch (type) {
        case 'country':
            return Globe;
        case 'city':
            return MapPinned;
        case 'building':
            return Building2;
        case 'floor':
            return Layers;
        default:
            return MapPin;
    }
}

function screenLabel(count: number): string {
    return `${count} screen${count === 1 ? '' : 's'}`;
}

export default function LocationsIndex({
    locations,
    types,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<SignageLocation | null>(null);
    const [deleting, setDeleting] = useState<SignageLocation | null>(null);
    const [form, setForm] = useState(emptyForm);
    const [query, setQuery] = useState('');

    const parentOptions = useMemo(
        () =>
            locations.filter(
                (location) => !editing || location.id !== editing.id,
            ),
        [locations, editing],
    );

    const visibleLocations = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return locations;
        }

        const byId = new Map(
            locations.map((location) => [location.id, location]),
        );
        const include = new Set<number>();

        for (const location of locations) {
            if (!location.name.toLowerCase().includes(needle)) {
                continue;
            }

            include.add(location.id);

            let current = location;

            while (current.parent_id) {
                include.add(current.parent_id);
                const parent = byId.get(current.parent_id);

                if (!parent) {
                    break;
                }

                current = parent;
            }
        }

        return locations.filter((location) => include.has(location.id));
    }, [locations, query]);

    const openCreate = (parent?: SignageLocation) => {
        setEditing(null);
        setForm({
            ...emptyForm,
            parent_id: parent ? String(parent.id) : '',
            type: parent ? NEXT_TYPE[parent.type] : 'city',
        });
        setOpen(true);
    };

    const openEdit = (location: SignageLocation) => {
        setEditing(location);
        setForm({
            name: location.name,
            type: location.type,
            parent_id: location.parent_id ? String(location.parent_id) : '',
            description: location.description ?? '',
            address: location.address ?? '',
            timezone: location.timezone ?? '',
        });
        setOpen(true);
    };

    return (
        <>
            <Head title="Locations" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase">
                            Place hierarchy
                        </p>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Locations
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed">
                            Group screens by country, city, building, floor, and
                            area so playback and targeting follow the real
                            layout of a place.
                        </p>
                    </div>
                    {permissions.canCreateLocation && (
                        <Button
                            onClick={() => openCreate()}
                            data-test="create-location"
                        >
                            <MapPin className="size-4" />
                            Add location
                        </Button>
                    )}
                </div>

                {locations.length === 0 ? (
                    <section className="dashboard-card">
                        <div className="flex flex-col items-center px-6 py-16 text-center">
                            <div className="mb-5 flex size-16 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900 dark:bg-blue-950">
                                <MapPin className="size-7" strokeWidth={1.5} />
                            </div>
                            <h2 className="text-base font-semibold">
                                No locations yet
                            </h2>
                            <p className="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">
                                Create a location tree so screens can be grouped
                                by place.
                            </p>
                            {permissions.canCreateLocation && (
                                <Button
                                    className="mt-6"
                                    onClick={() => openCreate()}
                                >
                                    <MapPin className="size-4" />
                                    Add location
                                </Button>
                            )}
                        </div>
                    </section>
                ) : (
                    <>
                        <div className="dashboard-card flex flex-wrap items-center gap-3 px-4 py-3">
                            <div className="relative min-w-56 flex-1">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                                <Input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search by name"
                                    aria-label="Search locations"
                                    className="pl-9"
                                />
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {`${locations.length} location${locations.length === 1 ? '' : 's'}`}
                            </p>
                        </div>

                        <section className="dashboard-card overflow-hidden">
                            <div className="border-b px-5 py-4">
                                <h2 className="font-semibold">Location tree</h2>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Indent shows parent and child. Type chips
                                    mark country through area.
                                </p>
                            </div>
                            {visibleLocations.length === 0 ? (
                                <p className="text-muted-foreground px-5 py-12 text-center text-sm">
                                    No locations match that name.
                                </p>
                            ) : (
                                <div className="divide-y">
                                    {visibleLocations.map((location) => {
                                        const Icon = typeIcon(location.type);

                                        return (
                                            <div
                                                key={location.id}
                                                className="flex flex-wrap items-center justify-between gap-3 py-3.5 pr-5"
                                                style={{
                                                    paddingLeft: `${1.25 + location.depth * 1.5}rem`,
                                                }}
                                                data-test={`location-row-${location.id}`}
                                            >
                                                <div className="flex min-w-0 items-start gap-3">
                                                    <div
                                                        className={`mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border ${
                                                            location.depth > 0
                                                                ? 'border-l-2'
                                                                : ''
                                                        }`}
                                                    >
                                                        <Icon className="text-muted-foreground size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h3 className="font-medium">
                                                                {location.name}
                                                            </h3>
                                                            <Badge variant="outline">
                                                                {
                                                                    location.type_label
                                                                }
                                                            </Badge>
                                                            <Badge variant="secondary">
                                                                <Monitor className="size-3" />
                                                                {screenLabel(
                                                                    location.screens_count,
                                                                )}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            {location.timezone ??
                                                                'Inherited timezone'}
                                                            {location.address
                                                                ? ` · ${location.address}`
                                                                : ''}
                                                            {location.children_count >
                                                            0
                                                                ? ` · ${location.children_count} child${location.children_count === 1 ? '' : 'ren'}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {permissions.canCreateLocation && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openCreate(
                                                                    location,
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-3.5" />
                                                            Add child
                                                        </Button>
                                                    )}
                                                    {permissions.canUpdateLocation && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openEdit(
                                                                    location,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </Button>
                                                    )}
                                                    {permissions.canDeleteLocation && (
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                setDeleting(
                                                                    location,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </section>
                    </>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <Form
                        {...(editing
                            ? update.form([
                                  currentTeam?.slug ?? '',
                                  editing.id,
                              ])
                            : store.form(currentTeam?.slug ?? ''))}
                        className="space-y-4"
                        onSuccess={() => setOpen(false)}
                    >
                        {({ errors, processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {editing
                                            ? 'Edit location'
                                            : 'Add location'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Locations form a hierarchy inside this
                                        organization.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
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
                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={form.type}
                                        />
                                        <Select
                                            value={form.type}
                                            onValueChange={(value) =>
                                                setForm({ ...form, type: value })
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((type) => (
                                                    <SelectItem
                                                        key={type.value}
                                                        value={type.value}
                                                    >
                                                        {type.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Parent</Label>
                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value={form.parent_id}
                                        />
                                        <Select
                                            value={form.parent_id || 'none'}
                                            onValueChange={(value) =>
                                                setForm({
                                                    ...form,
                                                    parent_id:
                                                        value === 'none'
                                                            ? ''
                                                            : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="None" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    None
                                                </SelectItem>
                                                {parentOptions.map(
                                                    (location) => (
                                                        <SelectItem
                                                            key={location.id}
                                                            value={String(
                                                                location.id,
                                                            )}
                                                        >
                                                            {`${'— '.repeat(location.depth)}${location.name}`}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.parent_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="timezone">
                                            Timezone
                                        </Label>
                                        <Input
                                            id="timezone"
                                            name="timezone"
                                            placeholder="Asia/Dubai"
                                            value={form.timezone}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    timezone:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={errors.timezone}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="address">Address</Label>
                                        <Input
                                            id="address"
                                            name="address"
                                            value={form.address}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    address:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Input
                                            id="description"
                                            name="description"
                                            value={form.description}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    description:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete location"
                description="This location will be removed. Child locations and assigned screens must be moved first."
                confirmLabel="Delete"
                onOpenChange={(next) => {
                    if (!next) {
                        setDeleting(null);
                    }
                }}
                onConfirm={() => {
                    if (!deleting || !currentTeam) {
                        return;
                    }

                    router.delete(destroy([currentTeam.slug, deleting.id]), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

LocationsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Locations',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
