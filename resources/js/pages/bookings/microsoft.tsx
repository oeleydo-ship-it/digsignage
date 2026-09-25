import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Cloud,
    RefreshCw,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Connection = {
    tenant_id: string | null;
    client_id: string | null;
    has_secret: boolean;
    is_active: boolean;
    last_synced_at: string | null;
    last_error: string | null;
};

type LocalRoom = { id: number; name: string; email: string | null };

type TenantRoom = {
    id: string;
    name: string;
    email: string;
    capacity: number | null;
    building: string | null;
    floor: string | null;
    linked_to: string | null;
};

type Props = {
    connection: Connection | null;
    linkedRooms: LocalRoom[];
    rooms: LocalRoom[];
    syncMinutes: number;
};

type Selection = Record<string, { selected: boolean; target: string }>;

export default function MicrosoftCalendar({
    connection,
    linkedRooms,
    rooms,
    syncMinutes,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const base = `/${slug}/integrations/microsoft-365`;
    const form = useForm({
        tenant_id: connection?.tenant_id ?? '',
        client_id: connection?.client_id ?? '',
        client_secret: '',
        is_active: connection?.is_active ?? true,
    });
    const [tenantRooms, setTenantRooms] = useState<TenantRoom[] | null>(null);
    const [selection, setSelection] = useState<Selection>({});
    const [loadingRooms, setLoadingRooms] = useState(false);
    const [roomsError, setRoomsError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const configured = connection !== null && connection.has_secret;

    const findRooms = async () => {
        setLoadingRooms(true);
        setRoomsError(null);

        try {
            const response = await fetch(`${base}/rooms`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = (await response.json()) as {
                rooms?: TenantRoom[];
                message?: string;
            };

            if (!response.ok) {
                throw new Error(
                    payload.message ??
                        'Microsoft 365 did not return any rooms.',
                );
            }

            const found = payload.rooms ?? [];
            setTenantRooms(found);
            setSelection(
                Object.fromEntries(
                    found.map((room) => [
                        room.email,
                        {
                            selected: room.linked_to === null,
                            // Pre-match rooms that already share a name.
                            target: String(
                                rooms.find(
                                    (local) =>
                                        local.name.toLowerCase() ===
                                        room.name.toLowerCase(),
                                )?.id ?? 'new',
                            ),
                        },
                    ]),
                ),
            );
        } catch (error) {
            setRoomsError(
                error instanceof Error
                    ? error.message
                    : 'Could not load rooms.',
            );
        } finally {
            setLoadingRooms(false);
        }
    };

    const importRooms = () => {
        const chosen = (tenantRooms ?? []).filter(
            (room) => selection[room.email]?.selected,
        );

        if (chosen.length === 0) {
            return;
        }

        setBusy(true);
        router.post(
            `${base}/import`,
            {
                rooms: chosen.map((room) => ({
                    email: room.email,
                    name: room.name,
                    capacity: room.capacity,
                    meeting_room_id:
                        selection[room.email].target === 'new'
                            ? null
                            : Number(selection[room.email].target),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setTenantRooms(null),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <Head title="Microsoft 365" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Microsoft 365"
                        description="Sync meeting rooms with Outlook room calendars. Meetings booked in Outlook show on your screens and block the room here; bookings made here appear in Outlook."
                    />
                    {connection && (
                        <Badge
                            variant={
                                connection.last_error
                                    ? 'destructive'
                                    : 'secondary'
                            }
                            className="self-start"
                        >
                            {connection.last_error
                                ? 'Needs attention'
                                : connection.is_active
                                  ? 'Connected'
                                  : 'Paused'}
                        </Badge>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                    <div className="space-y-6">
                        <section className="bg-card space-y-4 rounded-xl border p-5 shadow-sm">
                            <h2 className="font-semibold">App registration</h2>
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.put(base, {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            form.setData('client_secret', ''),
                                    });
                                }}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="m365-tenant">
                                            Directory (tenant) ID
                                        </Label>
                                        <Input
                                            id="m365-tenant"
                                            value={form.data.tenant_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'tenant_id',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="contoso.onmicrosoft.com or GUID"
                                            autoComplete="off"
                                        />
                                        <InputError
                                            message={form.errors.tenant_id}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="m365-client">
                                            Application (client) ID
                                        </Label>
                                        <Input
                                            id="m365-client"
                                            value={form.data.client_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'client_id',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="00000000-0000-0000-0000-000000000000"
                                            autoComplete="off"
                                        />
                                        <InputError
                                            message={form.errors.client_id}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="m365-secret">
                                        Client secret value
                                    </Label>
                                    <Input
                                        id="m365-secret"
                                        type="password"
                                        value={form.data.client_secret}
                                        onChange={(event) =>
                                            form.setData(
                                                'client_secret',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            connection?.has_secret
                                                ? 'Saved. Leave blank to keep it.'
                                                : ''
                                        }
                                        autoComplete="new-password"
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Encrypted at rest and never sent back to
                                        the browser.
                                    </p>
                                    <InputError
                                        message={form.errors.client_secret}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="size-4"
                                        checked={form.data.is_active}
                                        onChange={(event) =>
                                            form.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Sync enabled
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                        data-test="save-m365"
                                    >
                                        Save
                                    </Button>
                                    {configured && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        `${base}/test`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Test connection
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        `${base}/sync`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <RefreshCw className="size-4" />
                                                Sync now
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-destructive"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            'Disconnect Microsoft 365? Rooms stop syncing until you reconnect.',
                                                        )
                                                    ) {
                                                        router.delete(base, {
                                                            preserveScroll: true,
                                                        });
                                                    }
                                                }}
                                            >
                                                Disconnect
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </form>
                            {connection && (
                                <div className="text-muted-foreground border-t pt-3 text-sm">
                                    {connection.last_synced_at
                                        ? `Last synced ${new Date(connection.last_synced_at).toLocaleString()}. Syncs automatically every ${syncMinutes} minutes.`
                                        : `Not synced yet. Syncs automatically every ${syncMinutes} minutes once rooms are linked.`}
                                    {connection.last_error && (
                                        <p className="text-destructive mt-2 flex items-start gap-2">
                                            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                            {connection.last_error}
                                        </p>
                                    )}
                                </div>
                            )}
                        </section>

                        {configured && (
                            <section className="bg-card space-y-4 rounded-xl border p-5 shadow-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h2 className="font-semibold">
                                            Room mailboxes
                                        </h2>
                                        <p className="text-muted-foreground text-sm">
                                            Pick the Outlook rooms to show on
                                            screens and in the booking page.
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        onClick={() => void findRooms()}
                                        disabled={loadingRooms}
                                    >
                                        <Search className="size-4" />
                                        {loadingRooms
                                            ? 'Searching…'
                                            : 'Find rooms in Microsoft 365'}
                                    </Button>
                                </div>
                                {roomsError && (
                                    <p className="text-destructive text-sm">
                                        {roomsError}
                                    </p>
                                )}
                                {tenantRooms && tenantRooms.length === 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        No room mailboxes found. Create rooms in
                                        the Exchange admin center, or check the
                                        Place.Read.All permission.
                                    </p>
                                )}
                                {tenantRooms && tenantRooms.length > 0 && (
                                    <>
                                        <div className="overflow-x-auto rounded-lg border">
                                            <table className="w-full text-sm">
                                                <thead className="bg-muted/50 text-left text-xs">
                                                    <tr>
                                                        <th className="w-10 px-3 py-2" />
                                                        <th className="px-3 py-2">
                                                            Room
                                                        </th>
                                                        <th className="px-3 py-2">
                                                            Seats
                                                        </th>
                                                        <th className="px-3 py-2">
                                                            Link to
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {tenantRooms.map((room) => (
                                                        <tr
                                                            key={room.email}
                                                            className="border-t"
                                                        >
                                                            <td className="px-3 py-2">
                                                                <input
                                                                    type="checkbox"
                                                                    className="size-4"
                                                                    aria-label={`Import ${room.name}`}
                                                                    checked={
                                                                        selection[
                                                                            room
                                                                                .email
                                                                        ]
                                                                            ?.selected ??
                                                                        false
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setSelection(
                                                                            (
                                                                                current,
                                                                            ) => ({
                                                                                ...current,
                                                                                [room.email]:
                                                                                    {
                                                                                        ...current[
                                                                                            room
                                                                                                .email
                                                                                        ],
                                                                                        selected:
                                                                                            event
                                                                                                .target
                                                                                                .checked,
                                                                                    },
                                                                            }),
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <p className="font-medium">
                                                                    {room.name}
                                                                </p>
                                                                <p className="text-muted-foreground text-xs">
                                                                    {room.email}
                                                                    {room.building
                                                                        ? ` · ${room.building}`
                                                                        : ''}
                                                                    {room.floor
                                                                        ? ` · floor ${room.floor}`
                                                                        : ''}
                                                                </p>
                                                                {room.linked_to && (
                                                                    <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                                                        Linked
                                                                        to{' '}
                                                                        {
                                                                            room.linked_to
                                                                        }
                                                                    </p>
                                                                )}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                {room.capacity ??
                                                                    '—'}
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <select
                                                                    aria-label={`Link ${room.name} to`}
                                                                    className="border-input bg-background h-8 rounded-md border px-2 text-sm"
                                                                    value={
                                                                        selection[
                                                                            room
                                                                                .email
                                                                        ]
                                                                            ?.target ??
                                                                        'new'
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setSelection(
                                                                            (
                                                                                current,
                                                                            ) => ({
                                                                                ...current,
                                                                                [room.email]:
                                                                                    {
                                                                                        ...current[
                                                                                            room
                                                                                                .email
                                                                                        ],
                                                                                        target: event
                                                                                            .target
                                                                                            .value,
                                                                                    },
                                                                            }),
                                                                        )
                                                                    }
                                                                >
                                                                    <option value="new">
                                                                        Create a
                                                                        new room
                                                                    </option>
                                                                    {rooms.map(
                                                                        (
                                                                            local,
                                                                        ) => (
                                                                            <option
                                                                                key={
                                                                                    local.id
                                                                                }
                                                                                value={
                                                                                    local.id
                                                                                }
                                                                            >
                                                                                {
                                                                                    local.name
                                                                                }
                                                                            </option>
                                                                        ),
                                                                    )}
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <Button
                                            onClick={importRooms}
                                            disabled={
                                                busy ||
                                                !Object.values(selection).some(
                                                    (item) => item.selected,
                                                )
                                            }
                                        >
                                            Link selected rooms and sync
                                        </Button>
                                    </>
                                )}
                                {linkedRooms.length > 0 && (
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">
                                            Linked now
                                        </p>
                                        <ul className="text-sm">
                                            {linkedRooms.map((room) => (
                                                <li
                                                    key={room.id}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Cloud className="text-muted-foreground size-3.5" />
                                                    {room.name}
                                                    <span className="text-muted-foreground">
                                                        {room.email}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="bg-muted/40 h-fit space-y-3 rounded-xl border p-5 text-sm">
                        <h2 className="font-semibold">
                            Set up in Microsoft Entra ID
                        </h2>
                        <ol className="text-muted-foreground list-decimal space-y-2 pl-4">
                            <li>
                                In the{' '}
                                <span className="text-foreground">
                                    Entra admin center
                                </span>
                                , open <em>App registrations</em> and create a
                                new registration (single tenant).
                            </li>
                            <li>
                                Under <em>API permissions</em>, add Microsoft
                                Graph{' '}
                                <strong className="text-foreground">
                                    application
                                </strong>{' '}
                                permissions{' '}
                                <code className="text-foreground">
                                    Calendars.ReadWrite
                                </code>{' '}
                                and{' '}
                                <code className="text-foreground">
                                    Place.Read.All
                                </code>
                                .
                            </li>
                            <li>
                                Select <em>Grant admin consent</em>.
                            </li>
                            <li>
                                Under <em>Certificates &amp; secrets</em>,
                                create a client secret and paste its{' '}
                                <em>value</em> here.
                            </li>
                            <li>
                                Copy the <em>Directory (tenant) ID</em> and{' '}
                                <em>Application (client) ID</em> from the
                                app&apos;s Overview page.
                            </li>
                        </ol>
                        <p className="text-muted-foreground">
                            Tip: an Exchange <em>application access policy</em>{' '}
                            can limit the app to room mailboxes only.
                        </p>
                    </aside>
                </div>
            </div>
        </>
    );
}

MicrosoftCalendar.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Microsoft 365',
            href: props.currentTeam
                ? `/${props.currentTeam.slug}/integrations/microsoft-365`
                : '/',
        },
    ],
});
