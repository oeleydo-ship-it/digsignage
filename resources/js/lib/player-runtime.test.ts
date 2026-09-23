import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    downloadAssets,
    isCacheableAsset,
    pruneUnusedAssets,
    verifyCachedAssets,
    type PlayerAsset,
} from './player-runtime';

class MemoryCache {
    private store = new Map<string, Response>();

    async match(url: string): Promise<Response | undefined> {
        return this.store.get(url);
    }

    async put(url: string, response: Response): Promise<void> {
        this.store.set(url, response);
    }

    async keys(): Promise<Request[]> {
        return [...this.store.keys()].map(
            (url) => new Request(new URL(url, 'http://player.local')),
        );
    }

    async delete(request: Request): Promise<boolean> {
        const path = new URL(request.url).pathname;
        const key = [...this.store.keys()].find((candidate) => {
            try {
                return (
                    new URL(candidate, 'http://player.local').pathname === path
                );
            } catch {
                return candidate === request.url;
            }
        });

        return key !== undefined ? this.store.delete(key) : false;
    }
}

async function sha256Hex(content: string): Promise<string> {
    const digest = await crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(content),
    );

    return Array.from(new Uint8Array(digest))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

function asset(overrides: Partial<PlayerAsset> = {}): PlayerAsset {
    return {
        key: 'media:1',
        kind: 'media',
        id: 1,
        name: 'Clip',
        mime: 'video/mp4',
        bytes: null,
        checksum: null,
        type: 'video',
        url: '/api/player/v1/assets/media/1',
        ...overrides,
    };
}

describe('player asset cache', () => {
    let cache: MemoryCache;
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        cache = new MemoryCache();
        fetchMock = vi.fn();

        vi.stubGlobal('caches', { open: async () => cache });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function respondWith(content: string, ok = true): void {
        fetchMock.mockResolvedValue(
            new Response(new TextEncoder().encode(content), {
                status: ok ? 200 : 500,
            }),
        );
    }

    it('skips assets without a url and external urls', async () => {
        await downloadAssets(
            [
                asset({ url: null }),
                asset({ key: 'ext', url: 'https://cdn.example.com/clip.mp4' }),
            ],
            'token',
        );

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('does not download or checksum widgets, designs, or live urls', async () => {
        respondWith('not-a-file');

        await downloadAssets(
            [
                asset({
                    key: 'widget:13',
                    kind: 'widget',
                    id: 13,
                    type: 'widget',
                    checksum: await sha256Hex('widget-json'),
                    url: '/api/player/v1/assets/widget/13',
                }),
                asset({
                    key: 'design:13',
                    kind: 'design',
                    id: 13,
                    type: 'design',
                    checksum: await sha256Hex('design-json'),
                    url: '/api/player/v1/assets/design/13',
                }),
                asset({
                    key: 'live:1',
                    kind: 'live',
                    type: 'live_stream',
                    url: '/api/player/v1/assets/live/1',
                }),
            ],
            'token',
        );

        expect(fetchMock).not.toHaveBeenCalled();

        await expect(
            verifyCachedAssets([
                asset({
                    key: 'widget:13',
                    kind: 'widget',
                    id: 13,
                    type: 'widget',
                    checksum: await sha256Hex('widget-json'),
                    url: '/api/player/v1/assets/widget/13',
                }),
            ]),
        ).resolves.toBeUndefined();
    });

    it('treats only player media files as cacheable', () => {
        expect(
            isCacheableAsset(
                asset({ url: '/api/player/v1/assets/media/1' }),
            ),
        ).toBe(true);
        expect(
            isCacheableAsset(
                asset({
                    key: 'widget:13',
                    kind: 'widget',
                    url: '/api/player/v1/assets/widget/13',
                }),
            ),
        ).toBe(false);
        expect(
            isCacheableAsset(
                asset({
                    kind: 'design',
                    url: '/api/player/v1/assets/design/13',
                }),
            ),
        ).toBe(false);
    });

    it('downloads and caches assets whose checksum matches', async () => {
        respondWith('clip-bytes');

        await downloadAssets(
            [asset({ checksum: await sha256Hex('clip-bytes') })],
            'token',
        );

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][1]?.headers).toEqual({
            Authorization: 'Bearer token',
        });

        const cached = await cache.match('/api/player/v1/assets/media/1');
        expect(cached).toBeDefined();
    });

    it('throws when the downloaded bytes fail the checksum', async () => {
        respondWith('corrupted-bytes');

        await expect(
            downloadAssets(
                [asset({ checksum: await sha256Hex('clip-bytes') })],
                'token',
            ),
        ).rejects.toThrow('Checksum mismatch for media:1');

        expect(
            await cache.match('/api/player/v1/assets/media/1'),
        ).toBeUndefined();
    });

    it('throws when the download fails', async () => {
        respondWith('nope', false);

        await expect(downloadAssets([asset()], 'token')).rejects.toThrow(
            'Failed to download media:1',
        );
    });

    it('re-downloads when the cached copy is corrupt', async () => {
        await cache.put(
            '/api/player/v1/assets/media/1',
            new Response(new TextEncoder().encode('stale-bytes')),
        );
        respondWith('clip-bytes');

        await downloadAssets(
            [asset({ checksum: await sha256Hex('clip-bytes') })],
            'token',
        );

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const cached = await cache.match('/api/player/v1/assets/media/1');
        const text = await cached?.text();
        expect(text).toBe('clip-bytes');
    });

    it('skips the download when the cached copy matches the checksum', async () => {
        await cache.put(
            '/api/player/v1/assets/media/1',
            new Response(new TextEncoder().encode('clip-bytes')),
        );

        await downloadAssets(
            [asset({ checksum: await sha256Hex('clip-bytes') })],
            'token',
        );

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('verifies cached assets before offline playback', async () => {
        await cache.put(
            '/api/player/v1/assets/media/1',
            new Response(new TextEncoder().encode('clip-bytes')),
        );

        await expect(
            verifyCachedAssets([
                asset({ checksum: await sha256Hex('clip-bytes') }),
            ]),
        ).resolves.toBeUndefined();

        await expect(
            verifyCachedAssets([
                asset({ key: 'media:2', url: '/api/player/v1/assets/media/2' }),
            ]),
        ).rejects.toThrow('Missing cached asset media:2');

        await cache.put(
            '/api/player/v1/assets/media/2',
            new Response(new TextEncoder().encode('tampered')),
        );

        await expect(
            verifyCachedAssets([
                asset({
                    key: 'media:2',
                    url: '/api/player/v1/assets/media/2',
                    checksum: await sha256Hex('clip-bytes'),
                }),
            ]),
        ).rejects.toThrow('Checksum mismatch for media:2');
    });

    it('prunes cached assets that left the manifest', async () => {
        await cache.put('/api/player/v1/assets/media/1', new Response('keep'));
        await cache.put('/api/player/v1/assets/media/2', new Response('drop'));

        await pruneUnusedAssets([asset()]);

        expect(
            await cache.match('/api/player/v1/assets/media/1'),
        ).toBeDefined();
        expect(
            await cache.match('/api/player/v1/assets/media/2'),
        ).toBeUndefined();
    });
});
