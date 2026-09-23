import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    destroy,
    duplicate,
    edit,
    index,
    show,
    store,
} from '@/routes/channels';
import type { ChannelPermissions, ChannelRecord, Paginated } from '@/types';

type Option = { value: string; label: string };

type Props = {
    channels: Paginated<ChannelRecord>;
    filters: { search: string; status: string; type: string };
    statuses: Option[];
    types: Option[];
    permissions: ChannelPermissions;
};

export default function ChannelsIndex({
    channels,
    filters,
    statuses,
    types,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<ChannelRecord | null>(null);

    const applyFilters = (next: Partial<typeof filters>) => {
        router.get(
            index(slug),
            {
                search: next.search ?? search,
                status: next.status ?? filters.status,
                type: next.type ?? filters.type,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Channels" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Channels"
                        description="Decide what screens play: a looping playlist, a multi-zone layout, or a live stream."
                    />
                    {permissions.canCreateChannel && (
                        <Button
                            onClick={() => setCreateOpen(true)}
                            data-test="create-channel"
                        >
                            New channel
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Input
                        value={search}
                        placeholder="Search channels"
                        className="max-w-xs"
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters({ search });
                            }
                        }}
                    />
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.type}
                        onChange={(event) =>
                            applyFilters({ type: event.target.value })
                        }
                    >
                        <option value="">All types</option>
                        {types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.status}
                        onChange={(event) =>
                            applyFilters({ status: event.target.value })
                        }
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>

                {channels.data.length === 0 ? (
                    <EmptyState
                        title="No channels yet"
                        description="Create a playlist channel, a multi-zone layout, or a live stream."
                    />
                ) : (
                    <div className="divide-y rounded-xl border">
                        {channels.data.map((channel) => (
                            <div
                                key={channel.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-medium">
                                            {channel.name}
                                        </h3>
                                        <Badge variant="secondary">
                                            {channel.type_label}
                                        </Badge>
                                        <Badge variant="outline">
                                            {channel.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        {channel.playlist_name ??
                                            (channel.type === 'advanced'
                                                ? `${channel.zones_count} zones`
                                                : channel.type === 'live'
                                                  ? 'Live stream'
                                                  : 'No playlist yet')}{' '}
                                        · {channel.screens_count} screens · v
                                        {channel.version}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={show.url({
                                                current_team: slug,
                                                channel: channel.id,
                                            })}
                                        >
                                            Preview
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link
                                            href={edit.url({
                                                current_team: slug,
                                                channel: channel.id,
                                            })}
                                        >
                                            Open
                                        </Link>
                                    </Button>
                                    {permissions.canCreateChannel && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    duplicate.url({
                                                        current_team: slug,
                                                        channel: channel.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Duplicate
                                        </Button>
                                    )}
                                    {permissions.canDeleteChannel && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => setDeleting(channel)}
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <Form
                        {...store.form(slug)}
                        className="space-y-4"
                        onSuccess={() => setCreateOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>New channel</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="channel-name">Name</Label>
                                    <Input id="channel-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="channel-type">Type</Label>
                                    <select
                                        id="channel-type"
                                        name="type"
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                        defaultValue="playlist"
                                    >
                                        {types.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                title="Delete channel?"
                description="Screens assigned to this channel will stop using it."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            channel: deleting.id,
                        }),
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />
        </>
    );
}

ChannelsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Channels',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
