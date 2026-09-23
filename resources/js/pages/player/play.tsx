import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import WidgetSurface from '@/components/widget-surface';
import WebPageEmbed from '@/components/web-page-embed';
import { Button } from '@/components/ui/button';
import CanvasPreview from '@/components/canvas-preview';
import {
    acknowledgeCommand,
    clearPlayerCaches,
    completeCommand,
    executePlayerCommand,
    fetchPendingCommands,
    type PlayerCommand,
} from '@/lib/player-commands';
import { subscribePlayerCommands, type PlayerQueueUpdate, type ReverbConfig } from '@/lib/player-echo';
import { applyQueueCallToManifest, manifestHasQueueWidgets, preserveQueueServingRows, queueSoundsFromManifest } from '@/lib/queue-live-manifest';
import { serializePlayerRefresh } from '@/lib/player-sync';
import {
    BrowserSpeechVoiceProvider,
    manifestWantsQueueSound,
    manifestWantsQueueVoice,
    QueueAnnouncementQueue,
    shouldAnnounceQueueCall,
} from '@/lib/queue-voice';
import {
    activatePendingManifest,
    discardPreEmergencyManifest,
    discardPendingManifest,
    enqueueTelemetry,
    loadCurrentManifest,
    parseTelemetryQueue,
    queueAnnouncementsAllowed,
    rememberPreEmergencyManifest,
    replaceTelemetryQueue,
    restorePreEmergencyManifest,
    type PlayerConnectionState,
    writePendingManifest,
} from '@/lib/player-offline';
import {
    enterPlayerFullscreen,
    isBrowserFullscreen,
    isKioskWindow,
} from '@/lib/player-fullscreen';
import {
    cachedObjectUrl,
    currentPlayerVersion,
    downloadAssets,
    PLAYER_TOKEN_KEY,
    PLAYER_UUID_KEY,
    playerHeaders,
    pruneUnusedAssets,
    verifyCachedAssets,
    type PlayerAsset,
    type PlayerItem,
    type PlayerManifest,
    type PlayerPlaylist,
} from '@/lib/player-runtime';
import AppLogoIcon from '@/components/app-logo-icon';
import type { DesignDocument } from '@/types';

type PairingState =
    | { status: 'boot' }
    | { status: 'idle' }
    | { status: 'pending'; code: string }
    | { status: 'ready'; token: string }
    | { status: 'error'; message: string };

type Props = {
    heartbeatSeconds: number;
    pollSeconds: number;
    commandPollSeconds: number;
    reverb: ReverbConfig;
};

function playerStore(): Storage {
    return window.localStorage;
}

function commandPayloadString(
    payload: Record<string, unknown>,
    key: string,
    fallback: string,
): string {
    return typeof payload[key] === 'string' ? payload[key] : fallback;
}

function useObjectUrl(url: string | null) {
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!url) {
            setObjectUrl(null);

            return;
        }

        let revoked: string | null = null;

        void cachedObjectUrl(url).then((value) => {
            revoked = value;
            setObjectUrl(value);
        });

        return () => {
            if (revoked) {
                URL.revokeObjectURL(revoked);
            }
        };
    }, [url]);

    return objectUrl;
}

function PlayerDesign({
    document,
    assets,
    timezone,
}: {
    document: Record<string, unknown>;
    assets: PlayerManifest['assets'];
    timezone: string;
}) {
    const [sources, setSources] = useState<Record<number, string>>({});

    useEffect(() => {
        const mediaAssets = assets.filter(
            (asset) => asset.kind === 'media' && asset.url,
        );
        const objectUrls: string[] = [];
        let cancelled = false;

        void Promise.all(
            mediaAssets.map(async (asset) => {
                const cached = await cachedObjectUrl(asset.url as string);
                if (cached) objectUrls.push(cached);

                return [asset.id, (cached ?? asset.url) as string] as const;
            }),
        ).then((entries) => {
            if (!cancelled) setSources(Object.fromEntries(entries));
        });

        return () => {
            cancelled = true;
            objectUrls.forEach((url) => URL.revokeObjectURL(url));
        };
    }, [assets]);

    return (
        <div className="absolute inset-0 h-full min-h-0 w-full min-w-0">
            <CanvasPreview
                document={document as unknown as DesignDocument}
                fit
                timezone={timezone}
                resolveMedia={(mediaId, fallback) =>
                    mediaId ? (sources[mediaId] ?? null) : fallback
                }
            />
        </div>
    );
}

function isVideoAsset(asset: PlayerAsset | null, item: PlayerItem): boolean {
    if (asset?.type === 'video' || asset?.type === 'live_stream') {
        return true;
    }

    if (asset?.mime?.startsWith('video/')) {
        return true;
    }

    const url = asset?.url ?? item.url ?? '';

    return /\.(mp4|webm|mov|m4v|ogv|mpeg|mpg|avi|mkv|3gp)(\?|$)/i.test(url);
}

const mediaFillClass = 'h-full w-full object-cover bg-black';

type PlayerFallback = NonNullable<PlayerManifest['fallback']>;

function playlistHasItems(
    playlist: PlayerPlaylist | null | undefined,
): boolean {
    return Boolean(playlist && playlist.items.length > 0);
}

function FallbackScreen({
    fallback,
    screenName,
    compact = false,
}: {
    fallback?: PlayerFallback | null;
    screenName?: string;
    compact?: boolean;
}) {
    const name = fallback?.screen_name || screenName || '';
    const brand = fallback?.brand || 'DigSignage';
    const message = fallback?.message || 'Waiting for content';
    const background = fallback?.background || '#0b0b0f';
    const image = fallback?.image_url;

    return (
        <div
            className="relative h-full min-h-0 w-full min-w-0 overflow-hidden"
            style={{ background }}
            data-testid="player-fallback"
        >
            {image ? (
                <img
                    src={image}
                    alt=""
                    className="absolute inset-0 h-full w-full object-cover"
                />
            ) : null}
            {image ? <div className="absolute inset-0 bg-black/45" /> : null}
            <div className="relative z-10 flex h-full w-full flex-col items-center justify-center gap-3 px-8 text-center text-white">
                <AppLogoIcon
                    className={
                        compact
                            ? 'size-10 fill-white opacity-90'
                            : 'size-16 fill-white opacity-90'
                    }
                />
                <p
                    className={
                        compact
                            ? 'text-[10px] tracking-[0.3em] text-white/50 uppercase'
                            : 'text-sm tracking-[0.35em] text-white/50 uppercase'
                    }
                >
                    {brand}
                </p>
                {name ? (
                    <p
                        className={
                            compact
                                ? 'text-xl font-semibold'
                                : 'text-4xl font-semibold'
                        }
                    >
                        {name}
                    </p>
                ) : null}
                <p
                    className={
                        compact
                            ? 'text-sm text-white/70'
                            : 'text-lg text-white/70'
                    }
                >
                    {message}
                </p>
            </div>
        </div>
    );
}

function LiveSurface({
    url,
    fallback,
    screenName,
}: {
    url: string;
    fallback?: PlayerFallback | null;
    screenName?: string;
}) {
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        setFailed(false);
    }, [url]);

    if (failed) {
        return <FallbackScreen fallback={fallback} screenName={screenName} />;
    }

    return (
        <div className="flex h-full w-full items-center justify-center overflow-hidden bg-black">
            <video
                className={mediaFillClass}
                src={url}
                autoPlay
                muted
                playsInline
                onError={() => setFailed(true)}
            />
        </div>
    );
}

function ItemView({
    item,
    assets,
    timezone,
    fallback,
    screenName,
}: {
    item: PlayerItem;
    assets: PlayerManifest['assets'];
    timezone: string;
    fallback?: PlayerFallback | null;
    screenName?: string;
}) {
    const asset = assets.find((entry) => entry.key === item.asset_key) ?? null;
    const cached = useObjectUrl(asset?.url ?? null);
    const mediaSrc = cached ?? asset?.url ?? item.url ?? undefined;
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        setFailed(false);
    }, [item.key, mediaSrc]);

    const idle = (
        <FallbackScreen fallback={fallback} screenName={screenName} compact />
    );

    if (failed) {
        return idle;
    }

    if (item.type === 'media' && isVideoAsset(asset, item)) {
        return mediaSrc ? (
            <div className="flex h-full w-full items-center justify-center overflow-hidden bg-black">
                <video
                    className={mediaFillClass}
                    src={mediaSrc}
                    autoPlay
                    muted
                    loop
                    playsInline
                    onError={() => setFailed(true)}
                />
            </div>
        ) : (
            idle
        );
    }

    if (item.type === 'media' && mediaSrc) {
        return (
            <div className="flex h-full w-full items-center justify-center overflow-hidden bg-black">
                <img
                    className={mediaFillClass}
                    src={mediaSrc}
                    alt={item.title}
                    onError={() => setFailed(true)}
                />
            </div>
        );
    }

    if ((item.type === 'design' || item.type === 'template') && item.document) {
        return (
            <PlayerDesign
                document={item.document}
                assets={assets}
                timezone={timezone}
            />
        );
    }

    if (item.type === 'web_page' && item.url) {
        return <WebPageEmbed url={item.url} title={item.title} />;
    }

    if (item.type === 'live_stream' && item.url) {
        return (
            <div className="flex h-full w-full items-center justify-center overflow-hidden bg-black">
                <video
                    className={mediaFillClass}
                    src={item.url}
                    autoPlay
                    muted
                    playsInline
                    onError={() => setFailed(true)}
                />
            </div>
        );
    }

    if (item.type === 'widget') {
        return (
            <div className="h-full w-full overflow-hidden">
                <WidgetSurface
                    widget={
                        item.widget ?? {
                            key: item.widget_key ?? 'clock',
                            settings: {},
                            data: {},
                        }
                    }
                    timezone={timezone}
                />
            </div>
        );
    }

    return idle;
}

function PlaylistPlayer({
    playlist,
    assets,
    timezone,
    fallback,
    screenName,
    onItem,
    onDisplay,
}: {
    playlist: PlayerPlaylist;
    assets: PlayerManifest['assets'];
    timezone: string;
    fallback?: PlayerFallback | null;
    screenName?: string;
    onItem: (
        item: PlayerItem,
        report: {
            started_at: string;
            ended_at: string;
            duration_ms: number;
            status: 'completed' | 'interrupted';
        },
    ) => void;
    onDisplay?: (item: PlayerItem) => void;
}) {
    const [index, setIndex] = useState(0);
    const items = playlist.items;
    const current = items[index] ?? null;
    const onItemRef = useRef(onItem);
    const onDisplayRef = useRef(onDisplay);
    onItemRef.current = onItem;
    onDisplayRef.current = onDisplay;

    useEffect(() => {
        setIndex(0);
    }, [playlist.id, playlist.version, items.length]);

    useEffect(() => {
        if (!current) {
            return;
        }

        const startedAt = new Date();
        let completed = false;
        onDisplayRef.current?.(current);
        const durationMs = Math.max(1, current.duration_seconds) * 1000;
        const timer = window.setTimeout(() => {
            completed = true;
            const endedAt = new Date();
            onItemRef.current(current, {
                started_at: startedAt.toISOString(),
                ended_at: endedAt.toISOString(),
                duration_ms: durationMs,
                status: 'completed',
            });
            setIndex((value) => {
                if (items.length === 0) {
                    return 0;
                }

                const next = value + 1;

                if (next >= items.length) {
                    return playlist.loop ? 0 : value;
                }

                return next;
            });
        }, durationMs);

        return () => {
            window.clearTimeout(timer);

            if (!completed && Date.now() - startedAt.getTime() >= 500) {
                const endedAt = new Date();
                onItemRef.current(current, {
                    started_at: startedAt.toISOString(),
                    ended_at: endedAt.toISOString(),
                    duration_ms: endedAt.getTime() - startedAt.getTime(),
                    status: 'interrupted',
                });
            }
        };
    }, [current, items.length, playlist.loop]);

    if (!current) {
        return (
            <FallbackScreen
                fallback={fallback}
                screenName={screenName}
                compact
            />
        );
    }

    return (
        <div className="relative h-full min-h-0 w-full min-w-0 overflow-hidden bg-black">
            <ItemView
                item={current}
                assets={assets}
                timezone={timezone}
                fallback={fallback}
                screenName={screenName}
            />
        </div>
    );
}

function EmergencyOverlay({
    emergency,
}: {
    emergency: {
        title: string;
        message: string;
        instructions?: string;
        background?: string;
        severity?: string;
        imageUrl?: string | null;
        videoUrl?: string | null;
    };
}) {
    const image = useObjectUrl(emergency.imageUrl ?? null);
    const video = useObjectUrl(emergency.videoUrl ?? null);

    return (
        <div
            className="absolute inset-0 z-[60] flex flex-col items-center justify-center gap-4 p-8 text-center text-white"
            style={{ background: emergency.background || '#b91c1c' }}
        >
            <p className="text-sm tracking-[0.3em] uppercase">
                {emergency.severity === 'critical' ? 'Critical' : 'Emergency'}
            </p>
            {video || emergency.videoUrl ? (
                <video
                    className="max-h-[40vh] w-full max-w-5xl object-contain"
                    src={video ?? emergency.videoUrl ?? undefined}
                    autoPlay
                    muted
                    loop
                    playsInline
                />
            ) : null}
            {image || emergency.imageUrl ? (
                <img
                    className="max-h-[30vh] w-full max-w-4xl object-contain"
                    src={image ?? emergency.imageUrl ?? undefined}
                    alt=""
                />
            ) : null}
            <h2 className="text-5xl font-semibold">{emergency.title}</h2>
            {emergency.message !== '' && (
                <p className="max-w-3xl text-2xl text-white/90">
                    {emergency.message}
                </p>
            )}
            {emergency.instructions ? (
                <p className="max-w-3xl text-lg text-white/80">
                    {emergency.instructions}
                </p>
            ) : null}
        </div>
    );
}

export default function PlayerPlay({
    heartbeatSeconds,
    pollSeconds,
    commandPollSeconds,
    reverb,
}: Props) {
    const [pairing, setPairing] = useState<PairingState>({ status: 'boot' });
    const [manifest, setManifest] = useState<PlayerManifest | null>(null);
    const [reverbConnected, setReverbConnected] = useState(false);
    const [soundEnabled, setSoundEnabled] = useState(false);
    const [soundUnavailable, setSoundUnavailable] = useState(false);
    const [pending, setPending] = useState<PlayerManifest | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [online, setOnline] = useState(
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const [connectionState, setConnectionState] =
        useState<PlayerConnectionState>(
            typeof navigator === 'undefined' || navigator.onLine
                ? 'reconnecting'
                : 'offline',
        );
    const [fullscreen, setFullscreen] = useState(
        () =>
            typeof window !== 'undefined' &&
            (Boolean(window.digsignagePlayer) || isKioskWindow()),
    );
    const [currentTitle, setCurrentTitle] = useState('Waiting for content');
    const [emergency, setEmergency] = useState<{
        id?: number;
        title: string;
        message: string;
        instructions?: string;
        background?: string;
        severity?: string;
        imageUrl?: string | null;
        videoUrl?: string | null;
    } | null>(null);
    const tokenRef = useRef<string | null>(null);
    const manifestRef = useRef<PlayerManifest | null>(null);
    const recentQueueCallsRef = useRef(new Map<number, { update: PlayerQueueUpdate; receivedAt: number }>());
    const displayManifestRef = useRef<PlayerManifest | null>(null);
    const lastQueueUpdateAtRef = useRef(0);
    const recentDeparturesRef = useRef(new Map<number, number>());
    const connectionStateRef = useRef<PlayerConnectionState>(
        typeof navigator === 'undefined' || navigator.onLine
            ? 'reconnecting'
            : 'offline',
    );
    const realtimeReconnectingRef = useRef(false);
    const emergencyActiveRef = useRef(false);
    const emergencyIdRef = useRef<number | null>(null);
    const voiceProviderRef = useRef(new BrowserSpeechVoiceProvider());
    const announcementQueueRef = useRef(
        new QueueAnnouncementQueue(voiceProviderRef.current),
    );
    const handledCommands = useRef(new Set<number>());

    const updateConnectionState = useCallback(
        (state: PlayerConnectionState) => {
            connectionStateRef.current = state;
            setConnectionState(state);
        },
        [],
    );

    const enableCallSound = useCallback(() => {
        void voiceProviderRef.current.enableSound().then((enabled) => {
            setSoundEnabled(enabled);
            setSoundUnavailable(!enabled);
        });
    }, []);

    useEffect(
        () => () => {
            announcementQueueRef.current.clear();
        },
        [],
    );

    const adoptManifest = useCallback((next: PlayerManifest) => {
        manifestRef.current = next;
        const now = Date.now();
        const departed = new Set<number>();
        for (const [counterId, time] of recentDeparturesRef.current) {
            if (now - time < 5_000) departed.add(counterId);
            else recentDeparturesRef.current.delete(counterId);
        }
        let display = displayManifestRef.current && now - lastQueueUpdateAtRef.current < 5_000
            ? preserveQueueServingRows(displayManifestRef.current, next, departed)
            : next;
        for (const [counterId, call] of recentQueueCallsRef.current) {
            if (now - call.receivedAt > 5_000) {
                recentQueueCallsRef.current.delete(counterId);
                continue;
            }
            display = applyQueueCallToManifest(display, call.update);
        }
        displayManifestRef.current = display;
        setManifest(display);

        if (next.emergency) {
            if (!emergencyActiveRef.current) {
                announcementQueueRef.current.clear();
            }
            emergencyActiveRef.current = true;
            emergencyIdRef.current = next.emergency.id;
            const image = next.assets.find(
                (asset) => asset.key === next.emergency?.image_asset_key,
            );
            const video = next.assets.find(
                (asset) => asset.key === next.emergency?.video_asset_key,
            );

            setEmergency({
                id: next.emergency.id,
                title: next.emergency.title,
                message: next.emergency.message ?? '',
                instructions: next.emergency.instructions ?? '',
                background: next.emergency.background,
                severity: next.emergency.severity,
                imageUrl: image?.url ?? null,
                videoUrl: video?.url ?? null,
            });
        } else {
            if (emergencyActiveRef.current) {
                announcementQueueRef.current.clear();
            }
            emergencyActiveRef.current = false;
            emergencyIdRef.current = null;
            setEmergency(null);
        }
    }, []);

    useEffect(() => {
        const token = window.localStorage.getItem(PLAYER_TOKEN_KEY);
        const stored = loadCurrentManifest(playerStore());

        if (stored) {
            adoptManifest(stored);
        }

        if (token) {
            tokenRef.current = token;
            setPairing({ status: 'ready', token });
        } else {
            setPairing({ status: 'idle' });
        }

        if ('serviceWorker' in navigator) {
            void navigator.serviceWorker.register('/player-sw.js');
        }

        const goOnline = () => {
            setOnline(true);
            realtimeReconnectingRef.current = false;
            updateConnectionState('reconnecting');
        };
        const goOffline = () => {
            setOnline(false);
            realtimeReconnectingRef.current = true;
            announcementQueueRef.current.clear();
            updateConnectionState('offline');
        };

        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        setOnline(navigator.onLine);

        const syncFullscreen = () => {
            setFullscreen(
                isBrowserFullscreen() ||
                    isKioskWindow() ||
                    Boolean(window.digsignagePlayer),
            );
        };

        document.documentElement.classList.add(
            'h-full',
            'w-full',
            'overflow-hidden',
        );
        document.body.classList.add(
            'h-full',
            'w-full',
            'overflow-hidden',
            'm-0',
            'p-0',
        );
        document
            .getElementById('app')
            ?.classList.add('h-full', 'w-full', 'overflow-hidden');

        syncFullscreen();
        document.addEventListener('fullscreenchange', syncFullscreen);
        document.addEventListener('webkitfullscreenchange', syncFullscreen);
        window.addEventListener('resize', syncFullscreen);

        return () => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
            document.removeEventListener('fullscreenchange', syncFullscreen);
            document.removeEventListener(
                'webkitfullscreenchange',
                syncFullscreen,
            );
            window.removeEventListener('resize', syncFullscreen);
            document.documentElement.classList.remove(
                'h-full',
                'w-full',
                'overflow-hidden',
            );
            document.body.classList.remove(
                'h-full',
                'w-full',
                'overflow-hidden',
                'm-0',
                'p-0',
            );
            document
                .getElementById('app')
                ?.classList.remove('h-full', 'w-full', 'overflow-hidden');
        };
    }, [adoptManifest, updateConnectionState]);

    const startPairing = async () => {
        setError(null);
        enableCallSound();
        void enterPlayerFullscreen().then((ok) => setFullscreen(ok));
        const response = await fetch('/api/player/v1/registrations', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            setPairing({
                status: 'error',
                message: 'Unable to start pairing.',
            });

            return;
        }

        const payload = (await response.json()) as { code: string };
        setPairing({ status: 'pending', code: payload.code });
    };

    useEffect(() => {
        if (pairing.status !== 'pending') {
            return;
        }

        const timer = window.setInterval(async () => {
            const response = await fetch(
                `/api/player/v1/registrations/${encodeURIComponent(pairing.code)}`,
                { headers: { Accept: 'application/json' } },
            );
            const payload = (await response.json()) as {
                status: string;
                device_uuid?: string;
                device_token?: string;
            };

            if (payload.status === 'paired' && payload.device_token) {
                window.localStorage.setItem(
                    PLAYER_TOKEN_KEY,
                    payload.device_token,
                );

                if (payload.device_uuid) {
                    window.localStorage.setItem(
                        PLAYER_UUID_KEY,
                        payload.device_uuid,
                    );
                }

                tokenRef.current = payload.device_token;
                setPairing({ status: 'ready', token: payload.device_token });
            }
        }, 2500);

        return () => window.clearInterval(timer);
    }, [pairing]);

    const queueOrSend = useCallback(
        async (
            type: 'heartbeat' | 'playback',
            payload: Record<string, unknown>,
        ) => {
            const token = tokenRef.current;

            if (!token) {
                return;
            }

            if (!navigator.onLine) {
                enqueueTelemetry({ type, payload }, playerStore());

                return;
            }

            const path =
                type === 'heartbeat'
                    ? '/api/player/v1/heartbeat'
                    : '/api/player/v1/playback';

            try {
                const response = await fetch(path, {
                    method: 'POST',
                    headers: {
                        ...playerHeaders(token),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    enqueueTelemetry({ type, payload }, playerStore());
                }
            } catch {
                enqueueTelemetry({ type, payload }, playerStore());
            }
        },
        [],
    );

    const flushTelemetry = useCallback(async () => {
        const token = tokenRef.current;
        const queue = parseTelemetryQueue(playerStore());

        if (!token || queue.length === 0 || !navigator.onLine) {
            return;
        }

        try {
            const response = await fetch('/api/player/v1/telemetry', {
                method: 'POST',
                headers: {
                    ...playerHeaders(token),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    items: queue.map((item) => ({
                        type: item.type,
                        payload: item.payload,
                    })),
                }),
            });

            if (response.ok) {
                replaceTelemetryQueue([], playerStore());
            }
        } catch {
            // Keep the queue until the next reconnect.
        }
    }, []);

    const sync = useMemo(() => serializePlayerRefresh(async () => {
        const token = tokenRef.current;

        if (!token) {
            return;
        }

        if (!navigator.onLine) {
            return;
        }

        const response = await fetch('/api/player/v1/manifest', {
            headers: playerHeaders(token),
            cache: 'no-store',
        });

        if (response.status === 401) {
            window.localStorage.removeItem(PLAYER_TOKEN_KEY);
            tokenRef.current = null;
            setPairing({ status: 'idle' });

            return;
        }

        if (!response.ok) {
            throw new Error('Manifest sync failed');
        }

        const next = (await response.json()) as PlayerManifest;
        const current = manifestRef.current;

        if (current && next.version === current.version) {
            setError(null);
            return;
        }

        if (current && navigator.onLine && !emergencyActiveRef.current) {
            for (const call of queueSoundsFromManifest(current, next)) {
                announcementQueueRef.current.enqueue(call.key, call.request);
            }
        }

        const store = playerStore();
        if (next.emergency && current && !current.emergency) {
            rememberPreEmergencyManifest(current, store);
        }
        writePendingManifest(next, store);
        setPending(next);

        try {
            await downloadAssets(next.assets, token);
            await verifyCachedAssets(next.assets);
            const activated = activatePendingManifest(next.version, store);
            await pruneUnusedAssets(activated.assets);
            adoptManifest(activated);
            if (!activated.emergency) {
                discardPreEmergencyManifest(store);
            }
            setPending(null);
            setError(null);
        } catch (caught) {
            discardPendingManifest(store);
            setPending(null);

            if (manifestRef.current) {
                setError(
                    caught instanceof Error
                        ? caught.message
                        : 'Keeping last verified playlist',
                );

                return;
            }

            throw caught;
        }
    }), [adoptManifest]);

    const reconnect = useCallback(async () => {
        const token = tokenRef.current;

        if (!token || !navigator.onLine) {
            return;
        }

        updateConnectionState('reconnecting');

        const session = await fetch('/api/player/v1/session', {
            headers: playerHeaders(token),
        });

        if (session.status === 401) {
            window.localStorage.removeItem(PLAYER_TOKEN_KEY);
            tokenRef.current = null;
            setPairing({ status: 'idle' });

            return;
        }

        if (session.ok) {
            const payload = (await session.json()) as {
                screen?: { uuid?: string };
            };

            if (payload.screen?.uuid) {
                window.localStorage.setItem(
                    PLAYER_UUID_KEY,
                    payload.screen.uuid,
                );
            }
        }

        await flushTelemetry();
        await sync();

        if (!realtimeReconnectingRef.current) {
            updateConnectionState('online');
        }
    }, [flushTelemetry, sync, updateConnectionState]);

    useEffect(() => {
        if (pairing.status !== 'ready') {
            return;
        }

        void reconnect().catch((caught: unknown) => {
            updateConnectionState(
                navigator.onLine ? 'reconnecting' : 'offline',
            );
            if (!manifestRef.current) {
                setError(
                    caught instanceof Error ? caught.message : 'Sync failed',
                );
            }
        });

        const timer = window.setInterval(() => {
            void reconnect().catch((caught: unknown) => {
                updateConnectionState(
                    navigator.onLine ? 'reconnecting' : 'offline',
                );
                if (!manifestRef.current) {
                    setError(
                        caught instanceof Error
                            ? caught.message
                            : 'Sync failed',
                    );
                }
            });
        }, pollSeconds * 1000);

        return () => window.clearInterval(timer);
    }, [pairing, pollSeconds, reconnect, updateConnectionState]);

    const queueBoardActive = manifestHasQueueWidgets(manifest);

    useEffect(() => {
        if (pairing.status !== 'ready' || !online || !queueBoardActive) return;

        const timer = window.setInterval(() => {
            void sync().catch(() => undefined);
        }, 2_000);

        return () => window.clearInterval(timer);
    }, [pairing.status, online, queueBoardActive, sync]);

    useEffect(() => {
        if (pairing.status !== 'ready' || !online) {
            return;
        }

        void reconnect().catch(() => {
            updateConnectionState('reconnecting');
            // Last-good playback continues while reconnect is retried on the poll timer.
        });
    }, [online, pairing.status, reconnect, updateConnectionState]);

    useEffect(() => {
        if (pairing.status !== 'ready' || !tokenRef.current) {
            return;
        }

        const beat = () => {
            void (async () => {
                let storage_free: number | null = null;
                let storage_total: number | null = null;
                let memory_usage: number | null = null;

                try {
                    if (navigator.storage?.estimate) {
                        const estimate = await navigator.storage.estimate();
                        storage_total = estimate.quota ?? null;
                        storage_free =
                            estimate.quota == null
                                ? null
                                : Math.max(
                                      0,
                                      estimate.quota - (estimate.usage ?? 0),
                                  );
                    }
                } catch {
                    // Storage estimates are optional on locked-down browsers.
                }

                const memory = (
                    performance as Performance & {
                        memory?: { usedJSHeapSize?: number };
                    }
                ).memory?.usedJSHeapSize;

                if (typeof memory === 'number') {
                    memory_usage = memory;
                }

                void queueOrSend('heartbeat', {
                    player_version: currentPlayerVersion(),
                    current_content: currentTitle,
                    last_error: error,
                    network_status: navigator.onLine ? 'online' : 'offline',
                    manifest_version: manifestRef.current?.version ?? null,
                    playing_offline:
                        !navigator.onLine && manifestRef.current !== null,
                    storage_free,
                    storage_total,
                    memory_usage,
                });
            })();
        };

        beat();
        const timer = window.setInterval(beat, heartbeatSeconds * 1000);

        return () => window.clearInterval(timer);
    }, [pairing, heartbeatSeconds, currentTitle, error, queueOrSend]);

    const applyCommand = useCallback(
        async (command: PlayerCommand) => {
            const token = tokenRef.current;

            if (!token || handledCommands.current.has(command.command_id)) {
                return;
            }

            handledCommands.current.add(command.command_id);

            try {
                await acknowledgeCommand(token, command.command_id);
                const result = await executePlayerCommand(command, {
                    sync,
                    reload: () => window.location.reload(),
                    clearCache: clearPlayerCaches,
                    emergencyStart: (payload) => {
                        emergencyActiveRef.current = true;
                        emergencyIdRef.current =
                            typeof payload.emergency_id === 'number'
                                ? payload.emergency_id
                                : null;
                        announcementQueueRef.current.clear();
                        setEmergency({
                            id: emergencyIdRef.current ?? undefined,
                            title: commandPayloadString(
                                payload,
                                'title',
                                'Emergency',
                            ),
                            message: commandPayloadString(
                                payload,
                                'message',
                                '',
                            ),
                            instructions: commandPayloadString(
                                payload,
                                'instructions',
                                '',
                            ),
                            background: commandPayloadString(
                                payload,
                                'background',
                                '#b91c1c',
                            ),
                            severity: commandPayloadString(
                                payload,
                                'severity',
                                'emergency',
                            ),
                        });
                    },
                    emergencyStop: (payload) => {
                        const stoppingId =
                            typeof payload.emergency_id === 'number'
                                ? payload.emergency_id
                                : null;

                        if (
                            stoppingId !== null &&
                            emergencyIdRef.current !== null &&
                            stoppingId !== emergencyIdRef.current
                        ) {
                            return;
                        }

                        emergencyIdRef.current = null;
                        const restored =
                            restorePreEmergencyManifest(playerStore());

                        if (restored) {
                            adoptManifest(restored);
                        } else {
                            emergencyActiveRef.current = false;
                            announcementQueueRef.current.clear();
                            setEmergency(null);
                        }
                    },
                });
                await completeCommand(
                    token,
                    command.command_id,
                    'completed',
                    result,
                );
            } catch (caught) {
                await completeCommand(token, command.command_id, 'failed', {
                    error:
                        caught instanceof Error
                            ? caught.message
                            : 'Command failed',
                }).catch(() => undefined);
            }
        },
        [adoptManifest, sync],
    );

    useEffect(() => {
        if (pairing.status !== 'ready' || !online) {
            return;
        }

        const poll = () => {
            const token = tokenRef.current;

            if (!token) {
                return;
            }

            void fetchPendingCommands(token)
                .then(async (commands) => {
                    for (const command of commands) {
                        await applyCommand(command);
                    }
                })
                .catch(() => undefined);
        };

        poll();
        const timer = window.setInterval(poll, commandPollSeconds * 1000);

        return () => window.clearInterval(timer);
    }, [pairing.status, online, commandPollSeconds, applyCommand]);

    useEffect(() => {
        if (pairing.status !== 'ready' || !online) {
            return;
        }

        const token = tokenRef.current;
        const uuid = window.localStorage.getItem(PLAYER_UUID_KEY) ?? '';

        if (!token) {
            return;
        }

        let leave: () => void = () => {};
        let queueRefresh: number | null = null;

        void subscribePlayerCommands(
            token,
            uuid,
            reverb,
            (command) => {
                void applyCommand(command);
            },
            (update) => {
                const announcementKey = `${update.ticket_id ?? ''}:${update.called_at ?? ''}`;
                const currentManifest = manifestRef.current;
                lastQueueUpdateAtRef.current = Date.now();
                if (update.counter_id) {
                    if (update.status === 'called' || update.status === 'serving') {
                        recentDeparturesRef.current.delete(update.counter_id);
                    } else if (['completed', 'no_show', 'on_hold', 'transferred', 'cancelled'].includes(update.status ?? '')) {
                        recentDeparturesRef.current.set(update.counter_id, Date.now());
                    }
                }

                if ((update.status === 'called' || update.status === 'serving') && update.counter_id && update.ticket_id) {
                    recentQueueCallsRef.current.set(update.counter_id, { update, receivedAt: Date.now() });
                    const base = displayManifestRef.current ?? currentManifest;
                    if (base) {
                        const updated = applyQueueCallToManifest(base, update);
                        displayManifestRef.current = updated;
                        setManifest(updated);
                    }
                }

                if (
                    queueAnnouncementsAllowed(
                        connectionStateRef.current,
                        emergencyActiveRef.current,
                    ) &&
                    shouldAnnounceQueueCall(update) &&
                    currentManifest
                ) {
                    const wantsVoice = update.voice.enabled && manifestWantsQueueVoice(currentManifest, update);
                    const wantsSound = manifestWantsQueueSound(currentManifest, update);

                    if (wantsVoice || wantsSound) {
                        announcementQueueRef.current.enqueue(announcementKey, {
                            ticketNumber: update.ticket_number,
                            counterName: update.counter_name,
                            settings: { ...update.voice, chime: wantsSound || update.voice.chime },
                            soundOnly: !wantsVoice,
                        });
                    }
                }

                if (queueRefresh !== null) {
                    window.clearTimeout(queueRefresh);
                }

                queueRefresh = window.setTimeout(() => {
                    queueRefresh = null;
                    void sync().catch(() => {
                        // The regular manifest poll remains the fallback.
                    });
                }, 150);
            },
            (state) => {
                if (state === 'reconnecting') {
                    setReverbConnected(false);
                    realtimeReconnectingRef.current = true;
                    announcementQueueRef.current.clear();
                    updateConnectionState(
                        navigator.onLine ? 'reconnecting' : 'offline',
                    );

                    return;
                }

                setReverbConnected(true);
                realtimeReconnectingRef.current = false;
                announcementQueueRef.current.clear();
                updateConnectionState('reconnecting');
                void reconnect().catch(() => {
                    updateConnectionState(
                        navigator.onLine ? 'reconnecting' : 'offline',
                    );
                });
            },
            () => {
                void sync().catch(() => {
                    // The regular manifest poll remains the fallback.
                });
            },
        ).then((unsubscribe) => {
            leave = unsubscribe;
        });

        return () => {
            if (queueRefresh !== null) {
                window.clearTimeout(queueRefresh);
            }

            announcementQueueRef.current.clear();
            leave();
        };
    }, [
        pairing.status,
        online,
        reverb,
        applyCommand,
        sync,
        reconnect,
        updateConnectionState,
    ]);

    const reportItem = useCallback(
        (
            item: PlayerItem,
            report: {
                started_at: string;
                ended_at: string;
                duration_ms: number;
                status: 'completed' | 'interrupted';
            },
        ) => {
            setCurrentTitle(item.title);

            const current = manifestRef.current;

            if (!tokenRef.current || !current) {
                return;
            }

            void queueOrSend('playback', {
                item_key: item.key,
                asset_key: item.asset_key,
                content_id: item.key,
                title: item.title,
                channel_id: current.playback.channel?.id,
                playlist_id: current.playback.playlist?.id,
                schedule_id: current.playback.schedule_id,
                duration_ms: report.duration_ms,
                played_at: report.started_at,
                started_at: report.started_at,
                ended_at: report.ended_at,
                status: report.status,
            });
        },
        [queueOrSend],
    );

    const waiting = pending !== null && manifest !== null;
    const usingLastGood = Boolean(manifest) && (!online || Boolean(error));

    const body = useMemo(() => {
        if (!manifest) {
            return <FallbackScreen fallback={null} />;
        }

        const playback = manifest.playback;
        const fallback = manifest.fallback;
        const screenName = manifest.screen.name;

        if (playback.type === 'live' && playback.live?.url) {
            if (!online) {
                return (
                    <FallbackScreen
                        fallback={fallback}
                        screenName={screenName}
                    />
                );
            }

            return (
                <LiveSurface
                    url={playback.live.url}
                    fallback={fallback}
                    screenName={screenName}
                />
            );
        }

        if (playback.type === 'advanced') {
            return (
                <div className="absolute inset-0 h-full w-full bg-black">
                    {playback.zones.map((zone) => (
                        <div
                            key={zone.name}
                            className="absolute overflow-hidden"
                            style={{
                                left: `${zone.x}%`,
                                top: `${zone.y}%`,
                                width: `${zone.width}%`,
                                height: `${zone.height}%`,
                                zIndex: zone.z_index,
                            }}
                        >
                            {playlistHasItems(zone.playlist) ? (
                                <PlaylistPlayer
                                    playlist={zone.playlist as PlayerPlaylist}
                                    assets={manifest.assets}
                                    timezone={manifest.screen.timezone}
                                    fallback={fallback}
                                    screenName={zone.name || screenName}
                                    onItem={reportItem}
                                    onDisplay={(item) =>
                                        setCurrentTitle(item.title)
                                    }
                                />
                            ) : (
                                <FallbackScreen
                                    fallback={fallback}
                                    screenName={zone.name || screenName}
                                    compact
                                />
                            )}
                        </div>
                    ))}
                </div>
            );
        }

        if (playlistHasItems(playback.playlist)) {
            return (
                <PlaylistPlayer
                    playlist={playback.playlist as PlayerPlaylist}
                    assets={manifest.assets}
                    timezone={manifest.screen.timezone}
                    fallback={fallback}
                    screenName={screenName}
                    onItem={reportItem}
                    onDisplay={(item) => setCurrentTitle(item.title)}
                />
            );
        }

        return <FallbackScreen fallback={fallback} screenName={screenName} />;
    }, [manifest, online, reportItem]);

    return (
        <>
            <Head title="Player">
                <meta head-key="referrer" name="referrer" content="origin" />
                <link rel="manifest" href="/player.webmanifest" />
                <style>{`html,body,#app{height:100%;height:100dvh;width:100%;max-width:100%;margin:0;padding:0;overflow:hidden;background:#000}`}</style>
            </Head>
            {pairing.status === 'ready' ? (
                <div
                    className="fixed inset-0 h-full w-full overflow-hidden bg-black"
                    onDoubleClick={() => {
                        if (!soundEnabled) enableCallSound();
                        void enterPlayerFullscreen().then((ok) =>
                            setFullscreen(ok),
                        );
                    }}
                >
                    <div className="absolute inset-0 h-full min-h-0 w-full min-w-0">
                        {body}
                    </div>
                    {emergency && <EmergencyOverlay emergency={emergency} />}
                    {!fullscreen &&
                        typeof window !== 'undefined' &&
                        !window.digsignagePlayer && (
                            <button
                                type="button"
                                className="absolute inset-0 z-40 flex cursor-pointer flex-col items-center justify-center bg-black/70 text-white"
                                onClick={() => {
                                    if (!soundEnabled) enableCallSound();
                                    void enterPlayerFullscreen().then((ok) =>
                                        setFullscreen(ok),
                                    );
                                }}
                            >
                                <span className="text-2xl font-semibold">
                                    Tap for fullscreen
                                </span>
                                <span className="mt-2 text-sm text-white/70">
                                    Hides the address bar, or press F11
                                </span>
                            </button>
                        )}
                    <div className="absolute right-4 bottom-4 z-50 flex flex-col items-end gap-2 text-xs text-white">
                        {queueBoardActive && !soundEnabled && (
                            <button
                                type="button"
                                className="rounded bg-blue-600 px-3 py-2 font-semibold hover:bg-blue-500"
                                onClick={enableCallSound}
                                data-test="enable-queue-sound"
                            >
                                {soundUnavailable ? 'Sound blocked — tap to retry' : 'Enable call sound'}
                            </button>
                        )}
                        {(waiting || usingLastGood) && (
                            <div className="rounded bg-black/70 px-3 py-2">
                                {waiting
                                    ? 'Downloading new content…'
                                    : online
                                      ? (error ??
                                        'Using last verified playlist')
                                      : 'Offline — last verified playlist'}
                            </div>
                        )}
                        <div
                            className="flex items-center gap-2 rounded bg-black/70 px-3 py-2 font-semibold tracking-[0.12em]"
                            data-test="player-connection-state"
                        >
                            <span
                                className={`size-2 rounded-full ${
                                    connectionState === 'online'
                                        ? 'bg-emerald-400'
                                        : connectionState === 'reconnecting'
                                          ? 'animate-pulse bg-amber-400'
                                          : 'bg-red-400'
                                }`}
                            />
                            {connectionState.toUpperCase()}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="bg-background flex min-h-screen flex-col items-center justify-center p-6 text-center">
                    <p className="text-muted-foreground text-sm tracking-[0.3em] uppercase">
                        Digital signage player
                    </p>
                    {pairing.status === 'idle' && (
                        <>
                            <h1 className="mt-4 text-3xl font-semibold">
                                Register this display
                            </h1>
                            <Button
                                className="mt-6"
                                onClick={() => void startPairing()}
                            >
                                Show pairing code
                            </Button>
                        </>
                    )}
                    {pairing.status === 'pending' && (
                        <>
                            <h1 className="mt-4 text-3xl font-semibold">
                                Enter this code in the dashboard
                            </h1>
                            <div className="mt-6 font-mono text-5xl tracking-[0.25em]">
                                {pairing.code}
                            </div>
                        </>
                    )}
                    {pairing.status === 'error' && (
                        <>
                            <h1 className="mt-4 text-3xl font-semibold">
                                Pairing failed
                            </h1>
                            <p className="text-muted-foreground mt-2">
                                {pairing.message}
                            </p>
                            <Button
                                className="mt-6"
                                onClick={() => void startPairing()}
                            >
                                Try again
                            </Button>
                        </>
                    )}
                </div>
            )}
        </>
    );
}
