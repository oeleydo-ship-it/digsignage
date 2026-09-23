import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
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
import { dashboard } from '@/routes';
import {
    bulk,
    destroy,
    index,
    pair,
    update,
} from '@/routes/screens';
import type {
    Paginated,
    ScreenRecord,
    SignagePermissions,
} from '@/types';

type Option = { value: string; label: string };
type LocationOption = { id: number; name: string; depth: number };
type GroupOption = { id: number; name: string };

type Props = {
    screens: Paginated<ScreenRecord>;
    filters: {
        search: string;
        status: string;
        location_id: number | null;
    };
    locations: LocationOption[];
    groups: GroupOption[];
    statuses: Option[];
    orientations: Option[];
    permissions: SignagePermissions;
    plain_device_token?: string;
};

const emptyScreen = {
    name: '',
    description: '',
    location_id: '',
    orientation: 'landscape',
    resolution_width: '1920',
    resolution_height: '1080',
    timezone: '',
    code: '',
};

function stayOnPageHeaders(): Record<string, string> {
    if (typeof window === 'undefined') {
        return {};
    }

    return {
        'X-Stay-On-Page': window.location.href,
    };
}

function stayOnPage() {
    return {
        preserveScroll: true,
        headers: stayOnPageHeaders(),
    };
}

export default function ScreensIndex({
    screens,
    filters,
    locations,
    groups,
    statuses,
    orientations,
    permissions,
    plain_device_token,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [selected, setSelected] = useState<number[]>([]);
    const [pairOpen, setPairOpen] = useState(false);
    const [editing, setEditing] = useState<ScreenRecord | null>(null);
    const [deleting, setDeleting] = useState<ScreenRecord | null>(null);
    const [form, setForm] = useState(emptyScreen);
    const [search, setSearch] = useState(filters.search);

    const allIds = useMemo(
        () => screens.data.map((screen) => screen.id),
        [screens.data],
    );

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            index(slug),
            {
                search: next.search ?? search,
                status: next.status ?? filters.status,
                location_id: next.location_id ?? filters.location_id ?? '',
            },
            { preserveState: true, replace: true },
        );
    };

    const toggleAll = (checked: boolean) => {
        setSelected(checked ? allIds : []);
    };

    const runBulk = (action: string, extra: Record<string, unknown> = {}) => {
        router.post(
            bulk(slug),
            { action, screen_ids: selected, ...extra },
            { ...stayOnPage(), onSuccess: () => setSelected([]) },
        );
    };

    return (
        <>
            <Head title="Screens" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Screens"
                        description="Register displays, pair players, and assign them to locations."
                    />
                    {permissions.canPairScreen && (
                        <Button
                            onClick={() => {
                                setForm(emptyScreen);
                                setPairOpen(true);
                            }}
                            data-test="pair-screen"
                        >
                            Pair player
                        </Button>
                    )}
                </div>

                {plain_device_token && (
                    <div className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                        Copy this device token now. The player must use the new
                        credential; the previous token stops working.
                        <code className="mt-2 block break-all rounded bg-background p-2">
                            {plain_device_token}
                        </code>
                    </div>
                )}

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters({ search });
                    }}
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search screens"
                        className="max-w-xs"
                    />
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
                            <SelectItem value="all">All statuses</SelectItem>
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
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                </form>

                {permissions.canUpdateScreen && selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 rounded-lg border p-3">
                        <span className="text-sm">
                            {selected.length} selected
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => runBulk('disable')}
                        >
                            Disable
                        </Button>
                        {groups[0] && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    runBulk('assign_group', {
                                        screen_group_id: groups[0].id,
                                    })
                                }
                            >
                                Add to {groups[0].name}
                            </Button>
                        )}
                    </div>
                )}

                {screens.data.length === 0 ? (
                    <EmptyState
                        title="No screens yet"
                        description="Open the player on your display, then pair it here using the code shown on screen."
                        action={
                            permissions.canPairScreen ? (
                                <Button
                                    onClick={() => {
                                        setForm(emptyScreen);
                                        setPairOpen(true);
                                    }}
                                    data-test="pair-screen-empty"
                                >
                                    Pair player
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3">
                                        <Checkbox
                                            checked={
                                                selected.length ===
                                                    allIds.length &&
                                                allIds.length > 0
                                            }
                                            onCheckedChange={(checked) =>
                                                toggleAll(Boolean(checked))
                                            }
                                        />
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Location
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Player
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Last seen
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Content
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {screens.data.map((screen) => (
                                    <tr key={screen.id} className="border-t">
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={selected.includes(
                                                    screen.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  screen.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      screen.id,
                                                              ),
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">
                                                {screen.status_label}
                                            </Badge>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {screen.location_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.paired
                                                ? 'Paired'
                                                : 'Unpaired'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {screen.last_seen_at
                                                ? new Date(
                                                      screen.last_seen_at,
                                                  ).toLocaleString()
                                                : 'Never'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {screen.current_content ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link
                                                    href={`/${slug}/screens/${screen.id}/commands`}
                                                    data-test={`screen-commands-${screen.id}`}
                                                >
                                                    Commands
                                                </Link>
                                            </Button>
                                            {permissions.canPairScreen &&
                                                screen.paired && (
                                                    <Form
                                                        action={`/${slug}/screens/${screen.id}/rotate-credentials`}
                                                        method="post"
                                                        headers={stayOnPageHeaders()}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="ghost"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`rotate-credentials-${screen.id}`}
                                                            >
                                                                Rotate token
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            {permissions.canUpdateScreen && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setEditing(screen);
                                                        setForm({
                                                            ...emptyScreen,
                                                            name: screen.name,
                                                            description:
                                                                screen.description ??
                                                                '',
                                                            location_id:
                                                                screen.location_id
                                                                    ? String(
                                                                          screen.location_id,
                                                                      )
                                                                    : '',
                                                            orientation:
                                                                screen.orientation,
                                                            resolution_width:
                                                                String(
                                                                    screen.resolution_width ??
                                                                        '',
                                                                ),
                                                            resolution_height:
                                                                String(
                                                                    screen.resolution_height ??
                                                                        '',
                                                                ),
                                                            timezone:
                                                                screen.timezone ??
                                                                '',
                                                        });
                                                    }}
                                                >
                                                    Edit
                                                </Button>
                                            )}
                                            {permissions.canDeleteScreen && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDeleting(screen)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {screens.last_page > 1 && (
                    <div className="flex gap-2">
                        {screens.links.map((link) => (
                            <Button
                                key={link.label}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() =>
                                    link.url && router.get(link.url)
                                }
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>

            <ScreenFormDialog
                open={pairOpen}
                title="Pair player"
                description="Enter the code shown on the player, then name this screen."
                action={pair.form(slug)}
                form={form}
                setForm={setForm}
                locations={locations}
                orientations={orientations}
                includeCode
                onOpenChange={setPairOpen}
            />

            {editing && (
                <ScreenFormDialog
                    open
                    title="Edit screen"
                    action={update.form([slug, editing.id])}
                    form={form}
                    setForm={setForm}
                    locations={locations}
                    orientations={orientations}
                    includeStatus
                    screen={
                        screens.data.find((entry) => entry.id === editing.id) ??
                        editing
                    }
                    onOpenChange={(next) => {
                        if (!next) {
                            setEditing(null);
                        }
                    }}
                />
            )}

            <ConfirmDialog
                open={deleting !== null}
                title="Delete screen"
                description="The screen will be removed from this organization."
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

                    router.delete(destroy([slug, deleting.id]), {
                        ...stayOnPage(),
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

function ScreenFormDialog({
    open,
    title,
    description,
    action,
    form,
    setForm,
    locations,
    orientations,
    includeCode = false,
    includeStatus = false,
    screen = null,
    onOpenChange,
}: {
    open: boolean;
    title: string;
    description?: string;
    action:
        | ReturnType<typeof update.form>
        | ReturnType<typeof pair.form>;
    form: typeof emptyScreen;
    setForm: (form: typeof emptyScreen) => void;
    locations: LocationOption[];
    orientations: Option[];
    includeCode?: boolean;
    includeStatus?: boolean;
    screen?: ScreenRecord | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...action}
                    className="space-y-4"
                    headers={stayOnPageHeaders()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>{title}</DialogTitle>
                                {description && (
                                    <DialogDescription>
                                        {description}
                                    </DialogDescription>
                                )}
                            </DialogHeader>
                            <div className="grid gap-4">
                                {includeCode && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="code">
                                            Pairing code
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            value={form.code}
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    code: event.target.value.toUpperCase(),
                                                })
                                            }
                                            placeholder="ABCD-1234"
                                            required
                                            data-test="pair-code"
                                        />
                                        <InputError message={errors.code} />
                                    </div>
                                )}
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
                                <input
                                    type="hidden"
                                    name="orientation"
                                    value={form.orientation}
                                />
                                <input
                                    type="hidden"
                                    name="location_id"
                                    value={form.location_id}
                                />
                                <input
                                    type="hidden"
                                    name="resolution_width"
                                    value={form.resolution_width}
                                />
                                <input
                                    type="hidden"
                                    name="resolution_height"
                                    value={form.resolution_height}
                                />
                                <div className="grid gap-2">
                                    <Label>Location</Label>
                                    <Select
                                        value={form.location_id || 'none'}
                                        onValueChange={(value) =>
                                            setForm({
                                                ...form,
                                                location_id:
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
                                            {locations.map((location) => (
                                                <SelectItem
                                                    key={location.id}
                                                    value={String(location.id)}
                                                >
                                                    {location.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Orientation</Label>
                                    <Select
                                        value={form.orientation}
                                        onValueChange={(value) =>
                                            setForm({
                                                ...form,
                                                orientation: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {orientations.map((item) => (
                                                <SelectItem
                                                    key={item.value}
                                                    value={item.value}
                                                >
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {includeStatus && (
                                    <input
                                        type="hidden"
                                        name="status"
                                        value={undefined}
                                    />
                                )}
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
                {screen && (
                    <ScreenFallbackSection key={screen.id} screen={screen} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ScreenFallbackSection({ screen }: { screen: ScreenRecord }) {
    const slug = usePage().props.currentTeam?.slug ?? '';
    const fileInput = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const upload = () => {
        if (!file) {
            return;
        }

        const data = new FormData();
        data.append('image', file);
        setError(null);

        router.post(`/${slug}/screens/${screen.id}/fallback-image`, data, {
            forceFormData: true,
            ...stayOnPage(),
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
            onError: (errors) =>
                setError(Object.values(errors).join(' ') || null),
            onSuccess: () => {
                setFile(null);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const remove = () => {
        router.delete(
            `/${slug}/screens/${screen.id}/fallback-image`,
            stayOnPage(),
        );
    };

    return (
        <div className="grid gap-3 border-t pt-4">
            <div className="grid gap-1">
                <Label>Fallback screen image</Label>
                <p className="text-muted-foreground text-xs">
                    Optional. Overrides the workspace fallback image for this
                    screen only when it is idle.
                </p>
            </div>
            {screen.fallback_image_url ? (
                <div className="relative aspect-video w-full max-w-xs overflow-hidden rounded-md border bg-[#0b0b0f]">
                    <img
                        src={screen.fallback_image_url}
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 bg-black/45" />
                    <p className="absolute inset-x-0 bottom-2 z-10 text-center text-[10px] tracking-[0.3em] text-white/70 uppercase">
                        Waiting for content
                    </p>
                </div>
            ) : (
                <p className="text-muted-foreground text-xs">
                    No override — this screen uses the workspace fallback.
                </p>
            )}
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    ref={fileInput}
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    className="max-w-xs flex-1"
                    onChange={(event) =>
                        setFile(event.target.files?.[0] ?? null)
                    }
                    data-test={`screen-fallback-input-${screen.id}`}
                />
                <Button
                    type="button"
                    size="sm"
                    onClick={upload}
                    disabled={!file || uploading}
                    data-test={`screen-fallback-upload-${screen.id}`}
                >
                    {uploading ? 'Uploading…' : 'Upload'}
                </Button>
                {screen.fallback_image_url && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={remove}
                        data-test={`screen-fallback-remove-${screen.id}`}
                    >
                        Remove override
                    </Button>
                )}
            </div>
            <InputError message={error ?? undefined} />
        </div>
    );
}

ScreensIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Screens',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
