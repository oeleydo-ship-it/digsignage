import type { PlayerAsset, PlayerManifest } from '@/lib/player-runtime';

export const CURRENT_MANIFEST_KEY = 'digsignage.manifest.current';
export const PENDING_MANIFEST_KEY = 'digsignage.manifest.pending';
export const PRE_EMERGENCY_MANIFEST_KEY = 'digsignage.manifest.pre-emergency';
export const TELEMETRY_QUEUE_KEY = 'digsignage.telemetry.queue';

export type KeyValueStore = {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
};

export type TelemetryType = 'heartbeat' | 'playback';
export type PlayerConnectionState = 'online' | 'reconnecting' | 'offline';

export type QueuedTelemetry = {
    type: TelemetryType;
    payload: Record<string, unknown>;
    queued_at: string;
};

export function queueAnnouncementsAllowed(
    connectionState: PlayerConnectionState,
    emergencyActive: boolean,
): boolean {
    return connectionState === 'online' && !emergencyActive;
}

export function memoryStore(
    initial: Record<string, string> = {},
): KeyValueStore {
    const data = { ...initial };

    return {
        getItem(key) {
            return Object.prototype.hasOwnProperty.call(data, key)
                ? data[key]
                : null;
        },
        setItem(key, value) {
            data[key] = value;
        },
        removeItem(key) {
            delete data[key];
        },
    };
}

function parseManifest(raw: string | null): PlayerManifest | null {
    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as PlayerManifest;

        if (!parsed || typeof parsed.version !== 'number' || !parsed.playback) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function loadCurrentManifest(
    store: KeyValueStore,
): PlayerManifest | null {
    const current = parseManifest(store.getItem(CURRENT_MANIFEST_KEY));
    const pending = parseManifest(store.getItem(PENDING_MANIFEST_KEY));

    if (current && pending && pending.version === current.version) {
        store.removeItem(PENDING_MANIFEST_KEY);
    }

    return current;
}

export function writePendingManifest(
    manifest: PlayerManifest,
    store: KeyValueStore,
): void {
    store.setItem(PENDING_MANIFEST_KEY, JSON.stringify(manifest));
}

export function discardPendingManifest(store: KeyValueStore): void {
    store.removeItem(PENDING_MANIFEST_KEY);
}

export function rememberPreEmergencyManifest(
    manifest: PlayerManifest,
    store: KeyValueStore,
): void {
    if (!manifest.emergency) {
        store.setItem(PRE_EMERGENCY_MANIFEST_KEY, JSON.stringify(manifest));
    }
}

export function restorePreEmergencyManifest(
    store: KeyValueStore,
): PlayerManifest | null {
    const previous = parseManifest(store.getItem(PRE_EMERGENCY_MANIFEST_KEY));

    if (previous === null || previous.emergency) {
        return null;
    }

    store.setItem(CURRENT_MANIFEST_KEY, JSON.stringify(previous));
    store.removeItem(PENDING_MANIFEST_KEY);
    store.removeItem(PRE_EMERGENCY_MANIFEST_KEY);

    return previous;
}

export function discardPreEmergencyManifest(store: KeyValueStore): void {
    store.removeItem(PRE_EMERGENCY_MANIFEST_KEY);
}

export function activatePendingManifest(
    expectedVersion: number,
    store: KeyValueStore,
): PlayerManifest {
    const pending = parseManifest(store.getItem(PENDING_MANIFEST_KEY));

    if (!pending || pending.version !== expectedVersion) {
        throw new Error('Pending manifest is not ready to activate.');
    }

    store.setItem(CURRENT_MANIFEST_KEY, JSON.stringify(pending));
    store.removeItem(PENDING_MANIFEST_KEY);

    return pending;
}

export function parseTelemetryQueue(store: KeyValueStore): QueuedTelemetry[] {
    const raw = store.getItem(TELEMETRY_QUEUE_KEY);

    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw) as QueuedTelemetry[];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function enqueueTelemetry(
    item: Omit<QueuedTelemetry, 'queued_at'> & { queued_at?: string },
    store: KeyValueStore,
    max = 500,
): void {
    const queue = parseTelemetryQueue(store);
    queue.push({
        type: item.type,
        payload: item.payload,
        queued_at: item.queued_at ?? new Date().toISOString(),
    });

    const trimmed =
        queue.length > max ? queue.slice(queue.length - max) : queue;
    store.setItem(TELEMETRY_QUEUE_KEY, JSON.stringify(trimmed));
}

export function replaceTelemetryQueue(
    items: QueuedTelemetry[],
    store: KeyValueStore,
): void {
    if (items.length === 0) {
        store.removeItem(TELEMETRY_QUEUE_KEY);

        return;
    }

    store.setItem(TELEMETRY_QUEUE_KEY, JSON.stringify(items));
}

export function assetCacheUrls(assets: PlayerAsset[]): string[] {
    return assets
        .map((asset) => asset.url)
        .filter((url): url is string => Boolean(url));
}
