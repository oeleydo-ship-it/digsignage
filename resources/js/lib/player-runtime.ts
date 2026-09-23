import type { WidgetPayload } from '@/types';

export const PLAYER_TOKEN_KEY = 'digsignage.device_token';
export const PLAYER_UUID_KEY = 'digsignage.device_uuid';
export const WEB_PLAYER_VERSION = 'web-1.1';

export function currentPlayerVersion(): string {
    const shell = window.digsignagePlayer;

    if (shell?.platform && shell.version) {
        return `${shell.platform}-${shell.version}`;
    }

    return WEB_PLAYER_VERSION;
}

export type PlayerAsset = {
    key: string;
    kind: string;
    id: number;
    name: string;
    mime: string | null;
    bytes: number | null;
    checksum: string | null;
    type: string;
    url: string | null;
};

export type PlayerItem = {
    id: number;
    key: string;
    type: string;
    title: string;
    duration_seconds: number;
    transition: string;
    transition_ms: number;
    asset_key: string | null;
    document: Record<string, unknown> | null;
    url: string | null;
    widget_key: string | null;
    widget?: WidgetPayload | null;
};

export type PlayerPlaylist = {
    id: number;
    name: string;
    loop: boolean;
    version: number;
    items: PlayerItem[];
};

export type PlayerManifest = {
    version: number;
    generated_at: string;
    screen: {
        id: number;
        name: string;
        orientation: string;
        width: number;
        height: number;
        timezone: string;
    };
    playback: {
        source: string;
        schedule_id: number | null;
        schedule_name: string | null;
        type: string;
        channel: {
            id: number;
            name: string;
            type: string;
            status: string;
            width: number;
            height: number;
        } | null;
        live: { protocol: string | null; url: string | null } | null;
        zones: {
            name: string;
            x: number;
            y: number;
            width: number;
            height: number;
            z_index: number;
            playlist: PlayerPlaylist | null;
        }[];
        playlist: PlayerPlaylist | null;
    };
    fallback?: {
        brand: string;
        screen_name: string;
        message: string;
        background: string;
        image_url: string | null;
    };
    assets: PlayerAsset[];
    heartbeat_seconds: number;
    poll_seconds: number;
    emergency?: {
        id: number;
        severity: string;
        title: string;
        message: string | null;
        instructions: string | null;
        background: string;
        image_asset_key: string | null;
        video_asset_key: string | null;
        starts_at: string | null;
        expires_at: string | null;
    } | null;
};

export function playerHeaders(token: string): Record<string, string> {
    return {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
    };
}

async function sha256Hex(buffer: ArrayBuffer): Promise<string> {
    const digest = await crypto.subtle.digest('SHA-256', buffer);
    const bytes = Array.from(new Uint8Array(digest));

    return bytes.map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

export const ASSET_CACHE_NAME = 'digsignage-assets-v1';

function assetPath(url: string): string {
    try {
        return new URL(url, 'http://player.local').pathname;
    } catch {
        return url;
    }
}

/**
 * Binary media files are cached and checksummed. Widgets, designs, live URLs,
 * web pages, and other non-file items ride in the manifest and must not be
 * downloaded as files.
 */
export function isCacheableAsset(asset: PlayerAsset): boolean {
    if (asset.kind && asset.kind !== 'media') {
        return false;
    }

    const url = asset.url;

    if (!url) {
        return false;
    }

    try {
        return new URL(url, 'http://player.local').pathname.startsWith(
            '/api/player/v1/assets/media/',
        );
    } catch {
        return url.includes('/api/player/v1/assets/media/');
    }
}

export async function downloadAssets(
    assets: PlayerAsset[],
    token: string,
): Promise<void> {
    const cache = await caches.open(ASSET_CACHE_NAME);

    for (const asset of assets) {
        if (!isCacheableAsset(asset) || !asset.url) {
            continue;
        }

        const cached = await cache.match(asset.url);

        if (cached && asset.checksum) {
            const clone = await cached.clone().arrayBuffer();
            const hash = await sha256Hex(clone);

            if (hash === asset.checksum) {
                continue;
            }
        }

        const response = await fetch(asset.url, {
            headers: { Authorization: `Bearer ${token}` },
        });

        if (!response.ok) {
            throw new Error(`Failed to download ${asset.key}`);
        }

        const buffer = await response.clone().arrayBuffer();

        if (asset.checksum) {
            const hash = await sha256Hex(buffer);

            if (hash !== asset.checksum) {
                throw new Error(`Checksum mismatch for ${asset.key}`);
            }
        }

        await cache.put(asset.url, response);
    }
}

export async function verifyCachedAssets(assets: PlayerAsset[]): Promise<void> {
    const cache = await caches.open(ASSET_CACHE_NAME);

    for (const asset of assets) {
        if (!isCacheableAsset(asset) || !asset.url) {
            continue;
        }

        const cached = await cache.match(asset.url);

        if (!cached) {
            throw new Error(`Missing cached asset ${asset.key}`);
        }

        if (!asset.checksum) {
            continue;
        }

        const hash = await sha256Hex(await cached.clone().arrayBuffer());

        if (hash !== asset.checksum) {
            throw new Error(`Checksum mismatch for ${asset.key}`);
        }
    }
}

export async function pruneUnusedAssets(assets: PlayerAsset[]): Promise<void> {
    const cache = await caches.open(ASSET_CACHE_NAME);
    const keep = new Set(
        assets
            .map((asset) => asset.url)
            .filter((url): url is string => Boolean(url))
            .map((url) => assetPath(url)),
    );
    const keys = await cache.keys();

    await Promise.all(
        keys.map((request) => {
            if (keep.has(assetPath(request.url))) {
                return Promise.resolve(false);
            }

            return cache.delete(request);
        }),
    );
}

export async function cachedObjectUrl(url: string): Promise<string | null> {
    const cache = await caches.open(ASSET_CACHE_NAME);
    const response = await cache.match(url);

    if (!response) {
        return null;
    }

    const blob = await response.blob();

    return URL.createObjectURL(blob);
}
