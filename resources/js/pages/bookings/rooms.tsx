import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Cloud, Copy, ExternalLink, Link2, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
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
import { dashboard } from '@/routes';
import type { BookingPermissions, MeetingRoomRecord } from '@/types';

type Props = {
    rooms: MeetingRoomRecord[];
    locations: { id: number; name: string; depth: number }[];
    microsoftConnected: boolean;
    permissions: BookingPermissions;
};

type RoomForm = {
    name: string;
    location_id: string;
    description: string;
    capacity: string;
    amenities: string;
    color: string;
    opens_at: string;
    closes_at: string;
    min_duration_minutes: string;
    max_duration_minutes: string;
    is_active: boolean;
    public_booking_enabled: boolean;
    requires_approval: boolean;
    external_calendar_id: string;
};

const EMPTY: RoomForm = {
    name: '',
    location_id: '',
    description: '',
    capacity: '',
    amenities: 'Display, Video conferencing',
    color: '#2563eb',
    opens_at: '07:00',
    closes_at: '21:00',
    min_duration_minutes: '15',
    max_duration_minutes: '240',
    is_active: true,
    public_booking_enabled: true,
    requires_approval: false,
    external_calendar_id: '',
};

function toForm(room: MeetingRoomRecord): RoomForm {
    return {
        name: room.name,
        location_id: room.location_id ? String(room.location_id) : '',
        description: room.description ?? '',
        capacity: room.capacity ? String(room.capacity) : '',
        amenities: room.amenities.join(', '),
        color: room.color,
        opens_at: room.opens_at,
        closes_at: room.closes_at,
        min_duration_minutes: String(room.min_duration_minutes),
        max_duration_minutes: String(room.max_duration_minutes),
        is_active: room.is_active,
        public_booking_enabled: room.public_booking_enabled,
        requires_approval: room.requires_approval,
        external_calendar_id: room.external_calendar_id ?? '',
    };
}

export default function MeetingRooms({
    rooms,
    locations,
    microsoftConnected,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [editing, setEditing] = useState<MeetingRoomRecord | 'new' | null>(
        null,
    );
    const [copied, setCopied] = useState<number | null>(null);

    const copy = async (room: MeetingRoomRecord) => {
        if (!room.booking_url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(room.booking_url);
            setCopied(room.id);
            window.setTimeout(() => setCopied(null), 1500);
        } catch {
            window.prompt('Copy this booking link', room.booking_url);
        }
    };

    return (
        <>
            <Head title="Meeting rooms" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Meeting rooms"
                        description="Rooms people can book from the booking page, from a shared link, or by scanning the QR code on a room screen."
                    />
                    {permissions.canManageRooms && (
                        <Button
                            onClick={() => setEditing('new')}
                            data-test="add-room"
                        >
                            <Plus className="size-4" />
                            Add room
                        </Button>
                    )}
                </div>

                {rooms.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">No rooms yet</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Add rooms here, or import your room mailboxes from
                            Microsoft 365.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {rooms.map((room) => (
                            <article
                                key={room.id}
                                className="bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="size-3 shrink-0 rounded-full"
                                                style={{
                                                    background: room.color,
                                                }}
                                            />
                                            <h2 className="truncate font-semibold">
                                                {room.name}
                                            </h2>
                                        </div>
                                        <p className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2 text-sm">
                                            {room.capacity ? (
                                                <span className="inline-flex items-center gap-1">
                                                    <Users className="size-3.5" />{' '}
                                                    {room.capacity}
                                                </span>
                                            ) : null}
                                            {room.location && (
                                                <span>{room.location}</span>
                                            )}
                                            <span>
                                                {room.opens_at}–{room.closes_at}
                                            </span>
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap justify-end gap-1">
                                        {!room.is_active && (
                                            <Badge variant="outline">
                                                Inactive
                                            </Badge>
                                        )}
                                        {room.microsoft && (
                                            <Badge variant="secondary">
                                                <Cloud className="size-3" />{' '}
                                                Microsoft 365
                                            </Badge>
                                        )}
                                        {room.requires_approval && (
                                            <Badge variant="outline">
                                                Approval
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                {room.description && (
                                    <p className="text-muted-foreground line-clamp-2 text-sm">
                                        {room.description}
                                    </p>
                                )}

                                {room.amenities.length > 0 && (
                                    <div className="flex flex-wrap gap-1">
                                        {room.amenities.map((amenity) => (
                                            <span
                                                key={amenity}
                                                className="bg-muted rounded px-2 py-0.5 text-xs"
                                            >
                                                {amenity}
                                            </span>
                                        ))}
                                    </div>
                                )}

                                {room.external_calendar_id &&
                                    !room.microsoft && (
                                        <p className="text-xs text-amber-700 dark:text-amber-400">
                                            Mailbox {room.external_calendar_id}{' '}
                                            is set but Microsoft 365 is not
                                            connected.
                                        </p>
                                    )}

                                <div className="mt-auto flex items-center gap-3 rounded-lg border p-2">
                                    {room.booking_url ? (
                                        <>
                                            <img
                                                alt={`QR code to book ${room.name}`}
                                                className="size-16 shrink-0 rounded bg-white p-1"
                                                src={`https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&data=${encodeURIComponent(room.booking_url)}`}
                                            />
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="text-xs font-medium">
                                                    Public booking link
                                                </p>
                                                <div className="flex flex-wrap gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            void copy(room)
                                                        }
                                                    >
                                                        <Copy className="size-3.5" />
                                                        {copied === room.id
                                                            ? 'Copied'
                                                            : 'Copy'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <a
                                                            href={
                                                                room.booking_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <ExternalLink className="size-3.5" />
                                                            Open
                                                        </a>
                                                    </Button>
                                                </div>
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-muted-foreground p-1 text-xs">
                                            Public booking is off. Staff can
                                            still book this room.
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-muted-foreground text-xs">
                                        {room.upcoming_count} upcoming booking
                                        {room.upcoming_count === 1 ? '' : 's'}
                                    </p>
                                    {permissions.canManageRooms && (
                                        <div className="flex gap-1">
                                            {room.booking_url && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    title="Replace the link. Old links and printed QR codes stop working."
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Create a new link? The current link and QR codes will stop working.',
                                                            )
                                                        ) {
                                                            router.post(
                                                                `/${slug}/rooms/${room.id}/rotate-link`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Link2 className="size-3.5" />
                                                    New link
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setEditing(room)}
                                            >
                                                Edit
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>

            {editing && (
                <RoomDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    room={editing === 'new' ? null : editing}
                    slug={slug}
                    locations={locations}
                    microsoftConnected={microsoftConnected}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function RoomDialog({
    room,
    slug,
    locations,
    microsoftConnected,
    onClose,
}: {
    room: MeetingRoomRecord | null;
    slug: string;
    locations: Props['locations'];
    microsoftConnected: boolean;
    onClose: () => void;
}) {
    const form = useForm<RoomForm>(room ? toForm(room) : EMPTY);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (room) {
            form.patch(`/${slug}/rooms/${room.id}`, options);
        } else {
            form.post(`/${slug}/rooms`, options);
        }
    };

    const toggle = (
        field: 'is_active' | 'public_booking_enabled' | 'requires_approval',
        label: string,
        help: string,
    ) => (
        <label className="hover:bg-muted/50 flex items-start gap-3 rounded-md border p-2.5 text-sm">
            <input
                type="checkbox"
                className="mt-0.5 size-4"
                checked={form.data[field]}
                onChange={(event) => form.setData(field, event.target.checked)}
            />
            <span>
                <span className="block font-medium">{label}</span>
                <span className="text-muted-foreground block text-xs">
                    {help}
                </span>
            </span>
        </label>
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {room ? `Edit ${room.name}` : 'Add meeting room'}
                        </DialogTitle>
                        <DialogDescription>
                            Opening hours, duration limits and capacity apply to
                            self-service bookings. Booking managers can override
                            them.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div className="grid gap-2">
                            <Label htmlFor="room-name">Name</Label>
                            <Input
                                id="room-name"
                                data-test="room-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-color">Colour</Label>
                            <Input
                                id="room-color"
                                type="color"
                                className="h-9 w-16 p-1"
                                value={form.data.color}
                                onChange={(event) =>
                                    form.setData('color', event.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="room-location">Location</Label>
                            <select
                                id="room-location"
                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                value={form.data.location_id}
                                onChange={(event) =>
                                    form.setData(
                                        'location_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">No location</option>
                                {locations.map((location) => (
                                    <option
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {'  '.repeat(location.depth)}
                                        {location.name}
                                    </option>
                                ))}
                            </select>
                            <p className="text-muted-foreground text-xs">
                                Sets the room&apos;s timezone.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-capacity">Seats</Label>
                            <Input
                                id="room-capacity"
                                type="number"
                                min={1}
                                value={form.data.capacity}
                                onChange={(event) =>
                                    form.setData('capacity', event.target.value)
                                }
                            />
                            <InputError message={form.errors.capacity} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="room-description">Description</Label>
                        <Input
                            id="room-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Second floor, next to the kitchen"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="room-amenities">Amenities</Label>
                        <Input
                            id="room-amenities"
                            value={form.data.amenities}
                            onChange={(event) =>
                                form.setData('amenities', event.target.value)
                            }
                            placeholder="Display, Whiteboard, Video conferencing"
                        />
                        <p className="text-muted-foreground text-xs">
                            Separate with commas.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="room-opens">Opens</Label>
                            <Input
                                id="room-opens"
                                type="time"
                                value={form.data.opens_at}
                                onChange={(event) =>
                                    form.setData('opens_at', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-closes">Closes</Label>
                            <Input
                                id="room-closes"
                                type="time"
                                value={form.data.closes_at}
                                onChange={(event) =>
                                    form.setData(
                                        'closes_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-min">Min minutes</Label>
                            <Input
                                id="room-min"
                                type="number"
                                min={5}
                                value={form.data.min_duration_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'min_duration_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-max">Max minutes</Label>
                            <Input
                                id="room-max"
                                type="number"
                                min={5}
                                value={form.data.max_duration_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'max_duration_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <InputError
                        message={form.errors.closes_at ?? form.errors.opens_at}
                    />
                    <InputError
                        message={
                            form.errors.max_duration_minutes ??
                            form.errors.min_duration_minutes
                        }
                    />

                    <div className="grid gap-2">
                        {toggle(
                            'is_active',
                            'Bookable',
                            'Inactive rooms stay on screens but cannot be booked.',
                        )}
                        {toggle(
                            'public_booking_enabled',
                            'Public booking link and QR code',
                            'Anyone with the link can request this room.',
                        )}
                        {toggle(
                            'requires_approval',
                            'Approve public requests',
                            'Requests from the link wait for a booking manager before they hold the room.',
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="room-mailbox">
                            Microsoft 365 room mailbox
                        </Label>
                        <Input
                            id="room-mailbox"
                            type="email"
                            value={form.data.external_calendar_id}
                            onChange={(event) =>
                                form.setData(
                                    'external_calendar_id',
                                    event.target.value,
                                )
                            }
                            placeholder="boardroom@contoso.com"
                        />
                        <p className="text-muted-foreground text-xs">
                            {microsoftConnected
                                ? 'Bookings sync both ways with this room calendar in Outlook.'
                                : 'Connect Microsoft 365 under Room Booking → Microsoft 365 to sync this room.'}
                        </p>
                        <InputError
                            message={form.errors.external_calendar_id}
                        />
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {room ? (
                            confirmDelete ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            `/${slug}/rooms/${room.id}`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: onClose,
                                            },
                                        )
                                    }
                                >
                                    Delete room and its bookings
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    Delete
                                </Button>
                            )
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="save-room"
                            >
                                {room ? 'Save room' : 'Add room'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

MeetingRooms.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Meeting rooms',
            href: props.currentTeam ? `/${props.currentTeam.slug}/rooms` : '/',
        },
    ],
});
