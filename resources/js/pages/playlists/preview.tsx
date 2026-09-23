import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PlaylistThumb } from '@/components/playlist-thumb';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/playlists';
import type { PlaylistItemRecord, PlaylistRecord } from '@/types';

type Props = {
    playlist: PlaylistRecord & { items: PlaylistItemRecord[] };
};

export default function PlaylistPreview({ playlist }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`${playlist.name} preview`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={playlist.name}
                        description={`${playlist.duration_label} · ${playlist.items.length} items${playlist.loop ? ' · Loop' : ''}`}
                    />
                    <Button asChild>
                        <Link
                            href={edit.url({
                                current_team: slug,
                                playlist: playlist.id,
                            })}
                        >
                            Edit
                        </Link>
                    </Button>
                </div>
                <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {playlist.items.map((item, index) => (
                        <li
                            key={`${item.type}-${index}`}
                            className={`bg-card overflow-hidden rounded-xl border ${item.enabled ? '' : 'opacity-50'}`}
                        >
                            <div className="relative">
                                <PlaylistThumb
                                    src={item.preview_url}
                                    alt={item.title}
                                    type={item.type}
                                    mediaType={item.media_type}
                                    widgetKey={item.widget_key}
                                    label={item.type_label ?? item.type}
                                    className="aspect-video"
                                />
                                <div className="absolute inset-x-0 top-0 flex items-start justify-between p-2">
                                    <span className="bg-background/90 rounded-md px-1.5 py-0.5 text-xs font-semibold">
                                        {index + 1}
                                    </span>
                                    <Badge variant="secondary">
                                        {item.duration_seconds}s
                                    </Badge>
                                </div>
                            </div>
                            <div className="space-y-1 p-3">
                                <p
                                    className="truncate font-medium"
                                    title={item.title}
                                >
                                    {item.title}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {item.type_label ?? item.type} ·{' '}
                                    {item.transition}
                                    {!item.enabled ? ' · Disabled' : ''}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}

PlaylistPreview.layout = (props: {
    currentTeam?: { slug: string } | null;
    playlist?: { id: number; name: string };
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
        {
            title: props.playlist?.name ?? 'Preview',
            href:
                props.currentTeam && props.playlist
                    ? show.url({
                          current_team: props.currentTeam.slug,
                          playlist: props.playlist.id,
                      })
                    : '/',
        },
    ],
});
