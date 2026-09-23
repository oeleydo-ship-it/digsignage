import { useRef, useState } from 'react';
import type { ChannelZoneRecord } from '@/types';

type PlaylistOption = { id: number; name: string };

type DragMode = 'move' | 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

type Props = {
    zones: ChannelZoneRecord[];
    playlists: PlaylistOption[];
    selected: number;
    editable: boolean;
    stageWidth?: number;
    stageHeight?: number;
    onSelect: (index: number) => void;
    onUpdate: (index: number, patch: Partial<ChannelZoneRecord>) => void;
};

export function zonePixelSize(
    zone: Pick<ChannelZoneRecord, 'width' | 'height'>,
    stageWidth: number,
    stageHeight: number,
): { width: number; height: number } {
    return {
        width: Math.round((zone.width / 100) * stageWidth),
        height: Math.round((zone.height / 100) * stageHeight),
    };
}

const MIN_SIZE = 8;

const ZONE_INDEX_TYPE = 'application/x-zone-index';

/**
 * An absent payload reads back as '', which Number() turns into 0 — a valid
 * zone index. Require a non-empty integer payload instead.
 */
export function readZoneIndex(dataTransfer: DataTransfer): number | null {
    if (!Array.from(dataTransfer.types).includes(ZONE_INDEX_TYPE)) {
        return null;
    }

    const raw = dataTransfer.getData(ZONE_INDEX_TYPE).trim();

    if (raw === '') {
        return null;
    }

    const index = Number(raw);

    return Number.isInteger(index) && index >= 0 ? index : null;
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function round(value: number): number {
    return Math.round(value * 10) / 10;
}

export function applyDrag(
    zone: ChannelZoneRecord,
    mode: DragMode,
    dx: number,
    dy: number,
): Pick<ChannelZoneRecord, 'x' | 'y' | 'width' | 'height'> {
    let { x, y, width, height } = zone;

    if (mode === 'move') {
        return {
            x: round(clamp(zone.x + dx, 0, 100 - zone.width)),
            y: round(clamp(zone.y + dy, 0, 100 - zone.height)),
            width: round(zone.width),
            height: round(zone.height),
        };
    }

    if (mode.includes('e')) {
        width = clamp(zone.width + dx, MIN_SIZE, 100 - zone.x);
    }

    if (mode.includes('s')) {
        height = clamp(zone.height + dy, MIN_SIZE, 100 - zone.y);
    }

    if (mode.includes('w')) {
        const nextWidth = clamp(zone.width - dx, MIN_SIZE, zone.x + zone.width);
        x = zone.x + zone.width - nextWidth;
        width = nextWidth;
    }

    if (mode.includes('n')) {
        const nextHeight = clamp(
            zone.height - dy,
            MIN_SIZE,
            zone.y + zone.height,
        );
        y = zone.y + zone.height - nextHeight;
        height = nextHeight;
    }

    return {
        x: round(x),
        y: round(y),
        width: round(width),
        height: round(height),
    };
}

const handles: Array<{ mode: Exclude<DragMode, 'move'>; className: string }> = [
    {
        mode: 'n',
        className:
            'left-1/2 top-0 h-2 w-3 -translate-x-1/2 -translate-y-1/2 cursor-n-resize',
    },
    {
        mode: 's',
        className:
            'bottom-0 left-1/2 h-2 w-3 -translate-x-1/2 translate-y-1/2 cursor-s-resize',
    },
    {
        mode: 'e',
        className:
            'top-1/2 right-0 h-3 w-2 -translate-y-1/2 translate-x-1/2 cursor-e-resize',
    },
    {
        mode: 'w',
        className:
            'top-1/2 left-0 h-3 w-2 -translate-x-1/2 -translate-y-1/2 cursor-w-resize',
    },
    {
        mode: 'ne',
        className:
            'top-0 right-0 h-2.5 w-2.5 -translate-y-1/2 translate-x-1/2 cursor-ne-resize',
    },
    {
        mode: 'nw',
        className:
            'top-0 left-0 h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 cursor-nw-resize',
    },
    {
        mode: 'se',
        className:
            'bottom-0 right-0 h-2.5 w-2.5 translate-x-1/2 translate-y-1/2 cursor-se-resize',
    },
    {
        mode: 'sw',
        className:
            'bottom-0 left-0 h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 cursor-sw-resize',
    },
];

export default function ChannelZoneCanvas({
    zones,
    playlists,
    selected,
    editable,
    stageWidth = 1920,
    stageHeight = 1080,
    onSelect,
    onUpdate,
}: Props) {
    const canvasRef = useRef<HTMLDivElement>(null);
    const zonesRef = useRef(zones);
    zonesRef.current = zones;
    const [dropTarget, setDropTarget] = useState<number | null>(null);

    const playlistName = (playlistId: number | null) =>
        playlists.find((playlist) => playlist.id === playlistId)?.name ??
        'Empty';

    const startPointerDrag = (
        event: React.PointerEvent<HTMLElement>,
        index: number,
        mode: DragMode,
    ) => {
        if (!editable) {
            return;
        }

        event.stopPropagation();

        if (mode !== 'move') {
            event.preventDefault();
        }

        onSelect(index);

        const canvas = canvasRef.current;
        const startZone = zones[index];

        if (!canvas || !startZone) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const originX = event.clientX;
        const originY = event.clientY;
        const target = event.currentTarget;

        try {
            target.setPointerCapture(event.pointerId);
        } catch {
            // Pointer capture is unavailable in some automated browsers.
        }

        const onMove = (moveEvent: PointerEvent) => {
            const dx = ((moveEvent.clientX - originX) / rect.width) * 100;
            const dy = ((moveEvent.clientY - originY) / rect.height) * 100;
            onUpdate(index, applyDrag(startZone, mode, dx, dy));
        };

        const onUp = () => {
            target.removeEventListener('pointermove', onMove);
            target.removeEventListener('pointerup', onUp);
            target.removeEventListener('pointercancel', onUp);
        };

        target.addEventListener('pointermove', onMove);
        target.addEventListener('pointerup', onUp);
        target.addEventListener('pointercancel', onUp);
    };

    const placeZoneAt = (index: number, clientX: number, clientY: number) => {
        const canvas = canvasRef.current;
        const zone = zonesRef.current[index];

        if (!canvas || !zone) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const centerX = ((clientX - rect.left) / rect.width) * 100;
        const centerY = ((clientY - rect.top) / rect.height) * 100;

        onSelect(index);
        onUpdate(index, {
            x: round(clamp(centerX - zone.width / 2, 0, 100 - zone.width)),
            y: round(clamp(centerY - zone.height / 2, 0, 100 - zone.height)),
        });
    };

    const assignDroppedPlaylist = (index: number, event: React.DragEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setDropTarget(null);

        if (!editable) {
            return;
        }

        const movedZone = readZoneIndex(event.dataTransfer);

        if (movedZone !== null && zonesRef.current[movedZone]) {
            placeZoneAt(movedZone, event.clientX, event.clientY);

            return;
        }

        const raw =
            event.dataTransfer.getData('application/x-playlist-id') ||
            event.dataTransfer.getData('text/plain');
        const playlistId = Number(raw);

        if (!Number.isFinite(playlistId) || playlistId <= 0) {
            return;
        }

        if (!playlists.some((playlist) => playlist.id === playlistId)) {
            return;
        }

        onSelect(index);
        onUpdate(index, { playlist_id: playlistId });
    };

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-xs">
                Drag a zone to move it, drag a handle to resize, or drop a
                playlist onto a zone. Design for{' '}
                <span className="text-foreground font-medium">
                    {stageWidth} × {stageHeight}
                </span>{' '}
                (16:9).
            </p>
            <div
                ref={canvasRef}
                className="bg-muted relative aspect-video w-full overflow-hidden rounded-xl border"
                data-test="zone-canvas"
                onDragOver={(event) => {
                    if (!editable) {
                        return;
                    }

                    event.preventDefault();
                    event.dataTransfer.dropEffect = Array.from(
                        event.dataTransfer.types,
                    ).includes('application/x-zone-index')
                        ? 'move'
                        : 'copy';
                }}
                onDrop={(event) => {
                    const movedZone = readZoneIndex(event.dataTransfer);

                    if (
                        editable &&
                        movedZone !== null &&
                        zonesRef.current[movedZone]
                    ) {
                        event.preventDefault();
                        placeZoneAt(movedZone, event.clientX, event.clientY);
                    }
                }}
            >
                {zones.map((zone, index) => {
                    const isSelected = selected === index;

                    return (
                        <div
                            key={`${zone.name}-${index}`}
                            role="button"
                            tabIndex={0}
                            data-test={`zone-${index}`}
                            className={`absolute overflow-visible border text-left text-xs text-white ${
                                isSelected
                                    ? 'border-sky-400 ring-2 ring-sky-400'
                                    : 'border-white/40'
                            } ${dropTarget === index ? 'bg-sky-900/70' : 'bg-black/55'} ${
                                editable ? 'cursor-move' : 'cursor-default'
                            }`}
                            style={{
                                left: `${zone.x}%`,
                                top: `${zone.y}%`,
                                width: `${zone.width}%`,
                                height: `${zone.height}%`,
                                zIndex: isSelected
                                    ? 40 + zone.z_index
                                    : zone.z_index,
                            }}
                            draggable={editable}
                            onClick={() => onSelect(index)}
                            onPointerDown={(event) =>
                                startPointerDrag(event, index, 'move')
                            }
                            onDragStart={(event) => {
                                event.dataTransfer.setData(
                                    'application/x-zone-index',
                                    String(index),
                                );
                                event.dataTransfer.effectAllowed = 'move';
                            }}
                            onDragOver={(event) => {
                                if (!editable) {
                                    return;
                                }

                                event.preventDefault();
                                event.dataTransfer.dropEffect = 'copy';
                                setDropTarget(index);
                            }}
                            onDragLeave={() => {
                                setDropTarget((current) =>
                                    current === index ? null : current,
                                );
                            }}
                            onDrop={(event) =>
                                assignDroppedPlaylist(index, event)
                            }
                        >
                            <div className="pointer-events-none p-2">
                                <span className="font-medium">{zone.name}</span>
                                <span className="mt-1 block opacity-80">
                                    {playlistName(zone.playlist_id)}
                                </span>
                                <span className="mt-1 block text-[10px] opacity-70">
                                    {(() => {
                                        const size = zonePixelSize(
                                            zone,
                                            stageWidth,
                                            stageHeight,
                                        );

                                        return `${size.width} × ${size.height} px`;
                                    })()}
                                </span>
                            </div>
                            {editable &&
                                isSelected &&
                                handles.map((handle) => (
                                    <span
                                        key={handle.mode}
                                        data-test={`zone-handle-${handle.mode}`}
                                        className={`absolute z-10 rounded-sm bg-sky-400 ${handle.className}`}
                                        onPointerDown={(event) =>
                                            startPointerDrag(
                                                event,
                                                index,
                                                handle.mode,
                                            )
                                        }
                                    />
                                ))}
                        </div>
                    );
                })}
            </div>
            {playlists.length > 0 && (
                <div>
                    <p className="mb-2 text-xs font-medium">
                        Playlists — drag onto a zone
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {playlists.map((playlist) => (
                            <button
                                key={playlist.id}
                                type="button"
                                draggable={editable}
                                data-test={`playlist-chip-${playlist.id}`}
                                className="border-input bg-background hover:bg-muted rounded-md border px-2 py-1 text-xs"
                                onDragStart={(event) => {
                                    event.dataTransfer.setData(
                                        'application/x-playlist-id',
                                        String(playlist.id),
                                    );
                                    event.dataTransfer.setData(
                                        'text/plain',
                                        String(playlist.id),
                                    );
                                    event.dataTransfer.effectAllowed = 'copy';
                                }}
                                onClick={() => {
                                    if (editable && selected >= 0) {
                                        onUpdate(selected, {
                                            playlist_id: playlist.id,
                                        });
                                    }
                                }}
                            >
                                {playlist.name}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
