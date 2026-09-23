import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/channels';
import type { ChannelRecord, ChannelZoneRecord } from '@/types';

type Props = {
    channel: ChannelRecord & { zones: ChannelZoneRecord[] };
};

export default function ChannelPreview({ channel }: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`${channel.name} preview`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={channel.name}
                        description={`${channel.type_label} · ${channel.status_label}${channel.playlist_name ? ` · ${channel.playlist_name}` : ''}`}
                    />
                    <Button asChild>
                        <Link
                            href={edit.url({
                                current_team: slug,
                                channel: channel.id,
                            })}
                        >
                            Edit
                        </Link>
                    </Button>
                </div>

                {channel.type === 'live' ? (
                    <div className="rounded-xl border p-4">
                        <Badge variant="secondary">
                            {channel.live_protocol_label ?? 'Live'}
                        </Badge>
                        <p className="mt-2 break-all text-sm">{channel.live_url}</p>
                    </div>
                ) : (
                    <div className="bg-muted relative aspect-video w-full overflow-hidden rounded-xl border">
                        {channel.type === 'playlist' ? (
                            <div className="flex h-full items-center justify-center text-sm">
                                {channel.playlist_name ?? 'No playlist assigned'}
                            </div>
                        ) : (
                            channel.zones.map((zone) => (
                                <div
                                    key={`${zone.name}-${zone.x}-${zone.y}`}
                                    className="absolute overflow-hidden border border-white/40 bg-black/40 p-2 text-xs text-white"
                                    style={{
                                        left: `${zone.x}%`,
                                        top: `${zone.y}%`,
                                        width: `${zone.width}%`,
                                        height: `${zone.height}%`,
                                        zIndex: zone.z_index,
                                    }}
                                >
                                    <p className="font-medium">{zone.name}</p>
                                    <p className="opacity-80">
                                        {zone.playlist_name ?? 'Empty'}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

ChannelPreview.layout = (props: {
    currentTeam?: { slug: string } | null;
    channel?: { id: number; name: string };
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
        {
            title: props.channel?.name ?? 'Preview',
            href:
                props.currentTeam && props.channel
                    ? show.url({
                          current_team: props.currentTeam.slug,
                          channel: props.channel.id,
                      })
                    : '/',
        },
    ],
});
