import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ChannelZoneCanvas, {
    zonePixelSize,
} from '@/components/channel-zone-canvas';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ContentWorkflowPanel from '@/components/content-workflow-panel';
import { dashboard } from '@/routes';
import {
    edit as editRoute,
    index,
    show,
    update,
} from '@/routes/channels';
import type {
    ChannelPermissions,
    ChannelRecord,
    ChannelZoneRecord,
    ContentApprovalPayload,
} from '@/types';

type Option = { value: string; label: string };
type PlaylistOption = { id: number; name: string; duration_seconds: number };
type ScreenOption = {
    id: number;
    name: string;
    current_channel_id: number | null;
};

type Props = {
    channel: ChannelRecord & {
        zones: ChannelZoneRecord[];
        screen_ids: number[];
    };
    playlists: PlaylistOption[];
    screens: ScreenOption[];
    types: Option[];
    protocols: Option[];
    permissions: ChannelPermissions;
    approval: ContentApprovalPayload;
};

function defaultZones(): ChannelZoneRecord[] {
    return [
        {
            name: 'Main',
            playlist_id: null,
            x: 0,
            y: 0,
            width: 100,
            height: 70,
            z_index: 1,
        },
        {
            name: 'Ticker',
            playlist_id: null,
            x: 0,
            y: 70,
            width: 70,
            height: 30,
            z_index: 2,
        },
        {
            name: 'Sidebar',
            playlist_id: null,
            x: 70,
            y: 70,
            width: 30,
            height: 30,
            z_index: 3,
        },
    ];
}

export default function ChannelEditor({
    channel,
    playlists,
    screens,
    types,
    protocols,
    permissions,
    approval,
}: Props) {
    const { currentTeam } = usePage().props;
    const slug = currentTeam?.slug ?? '';
    const canEdit = permissions.canUpdateChannel && !approval.locked;
    const [name, setName] = useState(channel.name);
    const [type, setType] = useState(channel.type);
    const [playlistId, setPlaylistId] = useState(
        channel.playlist_id ? String(channel.playlist_id) : '',
    );
    const [liveProtocol, setLiveProtocol] = useState(
        channel.live_protocol ?? 'hls',
    );
    const [liveUrl, setLiveUrl] = useState(channel.live_url ?? '');
    const [zones, setZones] = useState<ChannelZoneRecord[]>(
        channel.zones.length > 0 ? channel.zones : defaultZones(),
    );
    const [screenIds, setScreenIds] = useState<number[]>(
        channel.screen_ids ?? [],
    );
    const [selected, setSelected] = useState(0);
    const current = zones[selected] ?? null;
    const stageWidth = channel.width || 1920;
    const stageHeight = channel.height || 1080;
    const currentPixels = current
        ? zonePixelSize(current, stageWidth, stageHeight)
        : null;

    const save = (after?: () => void) => {
        if (!canEdit) {
            after?.();

            return;
        }

        router.patch(
            update.url({ current_team: slug, channel: channel.id }),
            {
                name,
                type,
                status: channel.status,
                playlist_id: playlistId === '' ? null : Number(playlistId),
                live_protocol: liveProtocol,
                live_url: liveUrl,
                zones,
                screen_ids: screenIds,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: after,
            },
        );
    };

    const publish = () => {
        const release = () => {
            router.post(
                `/${slug}/approvals/channel/${channel.id}/publish`,
                {},
                { preserveScroll: true, preserveState: true },
            );
        };

        if (canEdit) {
            save(release);

            return;
        }

        release();
    };

    const updateZone = (index: number, patch: Partial<ChannelZoneRecord>) => {
        setZones((currentZones) =>
            currentZones.map((zone, zoneIndex) =>
                zoneIndex === index ? { ...zone, ...patch } : zone,
            ),
        );
    };

    const toggleScreen = (id: number) => {
        setScreenIds((currentIds) =>
            currentIds.includes(id)
                ? currentIds.filter((screenId) => screenId !== id)
                : [...currentIds, id],
        );
    };

    return (
        <>
            <Head title={`${channel.name} channel`} />
            <div className="flex h-[calc(100vh-4rem)] min-h-[640px] flex-col">
                <div className="flex flex-wrap items-center gap-2 border-b p-3">
                    <Input
                        value={name}
                        className="max-w-xs"
                        disabled={!canEdit}
                        onChange={(event) => setName(event.target.value)}
                    />
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={type}
                        disabled={!canEdit}
                        onChange={(event) => setType(event.target.value)}
                    >
                        {types.map((item) => (
                            <option key={item.value} value={item.value}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                    {canEdit && (
                        <Button
                            size="sm"
                            onClick={() => save()}
                            data-test="save-channel"
                        >
                            Save
                        </Button>
                    )}
                    {approval.can_publish && (
                        <Button
                            size="sm"
                            onClick={publish}
                            data-test="publish-channel"
                        >
                            Publish
                        </Button>
                    )}
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
                </div>
                <ContentWorkflowPanel approval={approval} hidePublish />
                <div className="grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)_280px]">
                    <div className="overflow-auto p-4">
                        {type === 'playlist' && (
                            <div className="space-y-2">
                                <Label>Playlist</Label>
                                <select
                                    className="border-input bg-background h-9 w-full max-w-md rounded-md border px-3 text-sm"
                                    value={playlistId}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        setPlaylistId(event.target.value)
                                    }
                                >
                                    <option value="">Select a playlist</option>
                                    {playlists.map((playlist) => (
                                        <option
                                            key={playlist.id}
                                            value={playlist.id}
                                        >
                                            {playlist.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        {type === 'live' && (
                            <div className="max-w-xl space-y-3">
                                <Label>Protocol</Label>
                                <select
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={liveProtocol}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        setLiveProtocol(event.target.value)
                                    }
                                >
                                    {protocols.map((protocol) => (
                                        <option
                                            key={protocol.value}
                                            value={protocol.value}
                                        >
                                            {protocol.label}
                                        </option>
                                    ))}
                                </select>
                                <Label>Stream URL</Label>
                                <Input
                                    value={liveUrl}
                                    disabled={!canEdit}
                                    placeholder="https://example.com/live/stream.m3u8"
                                    onChange={(event) =>
                                        setLiveUrl(event.target.value)
                                    }
                                />
                                <p className="text-muted-foreground text-xs">
                                    HLS and DASH play directly. RTSP and IPTV
                                    streams are accepted here and transcoded by
                                    the player gateway later.
                                </p>
                            </div>
                        )}
                        {type === 'advanced' && (
                            <ChannelZoneCanvas
                                zones={zones}
                                playlists={playlists}
                                selected={selected}
                                editable={canEdit}
                                stageWidth={stageWidth}
                                stageHeight={stageHeight}
                                onSelect={setSelected}
                                onUpdate={updateZone}
                            />
                        )}
                    </div>
                    <aside className="space-y-3 overflow-auto border-l p-3 text-sm">
                        {type === 'advanced' && current && (
                            <>
                                <div
                                    className="bg-muted/60 space-y-2 rounded-md border p-2 text-xs"
                                    data-test="zone-size-guide"
                                >
                                    <p className="font-medium">
                                        Recommended display
                                    </p>
                                    <p className="text-muted-foreground">
                                        Design the layout for a{' '}
                                        <span className="text-foreground font-medium">
                                            {stageWidth} × {stageHeight}
                                        </span>{' '}
                                        16:9 landscape screen. Export photos and
                                        videos at the zone pixel size (or
                                        larger) so they fill the display.
                                    </p>
                                    <ul className="text-muted-foreground space-y-1">
                                        <li>
                                            Main 100×70% ·{' '}
                                            <span className="text-foreground">
                                                {Math.round(stageWidth)} ×{' '}
                                                {Math.round(stageHeight * 0.7)}{' '}
                                                px
                                            </span>
                                        </li>
                                        <li>
                                            Ticker 70×30% ·{' '}
                                            <span className="text-foreground">
                                                {Math.round(stageWidth * 0.7)} ×{' '}
                                                {Math.round(stageHeight * 0.3)}{' '}
                                                px
                                            </span>
                                        </li>
                                        <li>
                                            Sidebar 30×30% ·{' '}
                                            <span className="text-foreground">
                                                {Math.round(stageWidth * 0.3)} ×{' '}
                                                {Math.round(stageHeight * 0.3)}{' '}
                                                px
                                            </span>
                                        </li>
                                    </ul>
                                    {currentPixels && (
                                        <p>
                                            This zone:{' '}
                                            <span className="font-medium">
                                                {currentPixels.width} ×{' '}
                                                {currentPixels.height} px
                                            </span>
                                        </p>
                                    )}
                                </div>
                                <Label>Zone name</Label>
                                <Input
                                    value={current.name}
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateZone(selected, {
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <Label>Playlist</Label>
                                <select
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    value={
                                        current.playlist_id != null
                                            ? String(current.playlist_id)
                                            : ''
                                    }
                                    disabled={!canEdit}
                                    onChange={(event) =>
                                        updateZone(selected, {
                                            playlist_id: event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        })
                                    }
                                >
                                    <option value="">None</option>
                                    {playlists.map((playlist) => (
                                        <option
                                            key={playlist.id}
                                            value={String(playlist.id)}
                                        >
                                            {playlist.name}
                                        </option>
                                    ))}
                                </select>
                                {(['x', 'y', 'width', 'height'] as const).map(
                                    (field) => (
                                        <div key={field}>
                                            <Label>
                                                {field}
                                                {(field === 'width' ||
                                                    field === 'height') &&
                                                    currentPixels && (
                                                        <span className="text-muted-foreground font-normal">
                                                            {' '}
                                                            (
                                                            {field === 'width'
                                                                ? currentPixels.width
                                                                : currentPixels.height}
                                                            px)
                                                        </span>
                                                    )}
                                            </Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={100}
                                                value={current[field]}
                                                disabled={!canEdit}
                                                onChange={(event) =>
                                                    updateZone(selected, {
                                                        [field]: Number(
                                                            event.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                        </div>
                                    ),
                                )}
                                {canEdit && (
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setZones((currentZones) => [
                                                    ...currentZones,
                                                    {
                                                        name: `Zone ${currentZones.length + 1}`,
                                                        playlist_id: null,
                                                        x: 10,
                                                        y: 10,
                                                        width: 40,
                                                        height: 40,
                                                        z_index:
                                                            currentZones.length +
                                                            1,
                                                    },
                                                ]);
                                                setSelected(zones.length);
                                            }}
                                        >
                                            Add zone
                                        </Button>
                                        {zones.length > 1 && (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() => {
                                                    setZones((currentZones) =>
                                                        currentZones.filter(
                                                            (_, index) =>
                                                                index !==
                                                                selected,
                                                        ),
                                                    );
                                                    setSelected(0);
                                                }}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                        <div className="space-y-1 border-t pt-3">
                            <p className="font-medium">Screens</p>
                            {screens.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No screens yet. Pair a player in Signage →
                                    Screens.
                                </p>
                            ) : (
                                screens.map((screen) => (
                                    <label
                                        key={screen.id}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={screenIds.includes(
                                                screen.id,
                                            )}
                                            disabled={!canEdit}
                                            onChange={() =>
                                                toggleScreen(screen.id)
                                            }
                                        />
                                        {screen.name}
                                    </label>
                                ))
                            )}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

ChannelEditor.layout = (props: {
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
            title: props.channel?.name ?? 'Edit',
            href:
                props.currentTeam && props.channel
                    ? editRoute.url({
                          current_team: props.currentTeam.slug,
                          channel: props.channel.id,
                      })
                    : '/',
        },
    ],
});
