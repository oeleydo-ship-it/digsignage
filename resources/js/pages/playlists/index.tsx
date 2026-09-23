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
} from '@/routes/playlists';
import type { Paginated, PlaylistPermissions, PlaylistRecord } from '@/types';

type Option = { value: string; label: string };

type Props = {
    playlists: Paginated<PlaylistRecord>;
    filters: { search: string; status: string };
    statuses: Option[];
    permissions: PlaylistPermissions;
};

export default function PlaylistsIndex({
    playlists,
    filters,
    statuses,
    permissions,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<PlaylistRecord | null>(null);

    return (
        <>
            <Head title="Playlists" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Playlists"
                        description="Order media, designs, and widgets into looping sequences."
                    />
                    {permissions.canCreatePlaylist && (
                        <Button
                            onClick={() => setCreateOpen(true)}
                            data-test="create-playlist"
                        >
                            New playlist
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Input
                        value={search}
                        placeholder="Search playlists"
                        className="max-w-xs"
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                router.get(
                                    index(slug),
                                    { search, status: filters.status },
                                    { preserveState: true, replace: true },
                                );
                            }
                        }}
                    />
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={filters.status}
                        onChange={(event) =>
                            router.get(
                                index(slug),
                                { search, status: event.target.value },
                                { preserveState: true, replace: true },
                            )
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

                {playlists.data.length === 0 ? (
                    <EmptyState
                        title="No playlists yet"
                        description="Create a sequence and add timed items from your library."
                    />
                ) : (
                    <div className="divide-y rounded-xl border">
                        {playlists.data.map((playlist) => (
                            <div
                                key={playlist.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-medium">
                                            {playlist.name}
                                        </h3>
                                        <Badge variant="secondary">
                                            {playlist.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        {playlist.items_count} items ·{' '}
                                        {playlist.duration_label}
                                        {playlist.loop ? ' · Loop' : ''} · v
                                        {playlist.version}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={show.url({
                                                current_team: slug,
                                                playlist: playlist.id,
                                            })}
                                        >
                                            Preview
                                        </Link>
                                    </Button>
                                    <Button size="sm" asChild>
                                        <Link
                                            href={edit.url({
                                                current_team: slug,
                                                playlist: playlist.id,
                                            })}
                                        >
                                            Open
                                        </Link>
                                    </Button>
                                    {permissions.canCreatePlaylist && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    duplicate.url({
                                                        current_team: slug,
                                                        playlist: playlist.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Duplicate
                                        </Button>
                                    )}
                                    {permissions.canDeletePlaylist && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => setDeleting(playlist)}
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
                                    <DialogTitle>New playlist</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="playlist-name">Name</Label>
                                    <Input id="playlist-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="loop"
                                        value="1"
                                        defaultChecked
                                    />
                                    Loop playback
                                </label>
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
                title="Delete playlist?"
                description="This removes the sequence from the library. Screens that already downloaded it keep their last copy until the next publish cycle."
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(
                        destroy.url({
                            current_team: slug,
                            playlist: deleting.id,
                        }),
                    );
                    setDeleting(null);
                }}
                onOpenChange={(open) => !open && setDeleting(null)}
            />
        </>
    );
}

PlaylistsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
        {
            title: 'Playlists',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
