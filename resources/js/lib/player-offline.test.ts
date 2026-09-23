import { describe, expect, it } from 'vitest';

import {
    activatePendingManifest,
    assetCacheUrls,
    CURRENT_MANIFEST_KEY,
    discardPendingManifest,
    enqueueTelemetry,
    loadCurrentManifest,
    memoryStore,
    parseTelemetryQueue,
    PENDING_MANIFEST_KEY,
    PRE_EMERGENCY_MANIFEST_KEY,
    queueAnnouncementsAllowed,
    rememberPreEmergencyManifest,
    replaceTelemetryQueue,
    restorePreEmergencyManifest,
    TELEMETRY_QUEUE_KEY,
    writePendingManifest,
} from './player-offline';
import type { PlayerAsset, PlayerManifest } from './player-runtime';

function manifest(version: number): PlayerManifest {
    return {
        version,
        generated_at: new Date().toISOString(),
        screen: {
            id: 1,
            name: 'Lobby',
            orientation: 'landscape',
            width: 1920,
            height: 1080,
            timezone: 'UTC',
        },
        playback: {
            source: 'channel',
            schedule_id: null,
            schedule_name: null,
            type: 'playlist',
            channel: null,
            live: null,
            zones: [],
            playlist: null,
        },
        assets: [],
        heartbeat_seconds: 30,
        poll_seconds: 60,
    };
}

describe('manifest storage', () => {
    it('returns null when no manifest is stored', () => {
        expect(loadCurrentManifest(memoryStore())).toBeNull();
    });

    it('ignores corrupt JSON in storage', () => {
        const store = memoryStore({ [CURRENT_MANIFEST_KEY]: '{not json' });

        expect(loadCurrentManifest(store)).toBeNull();
    });

    it('ignores payloads that are not manifests', () => {
        const store = memoryStore({
            [CURRENT_MANIFEST_KEY]: JSON.stringify({ version: 'one' }),
        });

        expect(loadCurrentManifest(store)).toBeNull();
    });

    it('activates a pending manifest only at the expected version', () => {
        const store = memoryStore();

        writePendingManifest(manifest(2), store);

        expect(() => activatePendingManifest(3, store)).toThrow(
            'Pending manifest is not ready to activate.',
        );

        const activated = activatePendingManifest(2, store);

        expect(activated.version).toBe(2);
        expect(loadCurrentManifest(store)?.version).toBe(2);
        expect(store.getItem(PENDING_MANIFEST_KEY)).toBeNull();
    });

    it('discards a pending manifest without activating it', () => {
        const store = memoryStore();

        writePendingManifest(manifest(2), store);
        discardPendingManifest(store);

        expect(store.getItem(PENDING_MANIFEST_KEY)).toBeNull();
        expect(loadCurrentManifest(store)).toBeNull();
    });

    it('clears a stale pending manifest once current catches up', () => {
        const store = memoryStore({
            [CURRENT_MANIFEST_KEY]: JSON.stringify(manifest(2)),
            [PENDING_MANIFEST_KEY]: JSON.stringify(manifest(2)),
        });

        expect(loadCurrentManifest(store)?.version).toBe(2);
        expect(store.getItem(PENDING_MANIFEST_KEY)).toBeNull();
    });

    it('persists the last queue widget snapshot for offline display', () => {
        const store = memoryStore();
        const queueManifest = manifest(3);
        queueManifest.playback.playlist = {
            id: 9,
            name: 'Lobby queue',
            loop: true,
            version: 1,
            items: [
                {
                    id: 17,
                    key: 'widget:queue-now-serving',
                    type: 'widget',
                    title: 'Now serving',
                    duration_seconds: 30,
                    transition: 'none',
                    transition_ms: 0,
                    asset_key: null,
                    document: null,
                    url: null,
                    widget_key: 'queue_now_serving',
                    widget: {
                        key: 'queue_now_serving',
                        settings: { voice: true },
                        data: {
                            now_serving: [
                                { number: 'A105', counter_name: 'Counter 4' },
                            ],
                        },
                    },
                },
            ],
        };

        writePendingManifest(queueManifest, store);
        activatePendingManifest(queueManifest.version, store);

        const restored = loadCurrentManifest(store);
        expect(
            restored?.playback.playlist?.items[0].widget?.data.now_serving,
        ).toEqual([{ number: 'A105', counter_name: 'Counter 4' }]);
    });
});

describe('queue announcement safety', () => {
    it('allows calls only after the player is online and no emergency is active', () => {
        expect(queueAnnouncementsAllowed('online', false)).toBe(true);
        expect(queueAnnouncementsAllowed('online', true)).toBe(false);
        expect(queueAnnouncementsAllowed('reconnecting', false)).toBe(false);
        expect(queueAnnouncementsAllowed('offline', false)).toBe(false);
    });
});

describe('emergency layout recovery', () => {
    it('restores the last non-emergency manifest and clears the checkpoint', () => {
        const store = memoryStore();
        const previous = manifest(4);
        const emergency = {
            ...manifest(5),
            emergency: {
                id: 9,
                severity: 'emergency',
                title: 'Evacuate',
                message: '',
                instructions: '',
                background: '#b91c1c',
                image_asset_key: null,
                video_asset_key: null,
                starts_at: null,
                expires_at: null,
            },
        };

        rememberPreEmergencyManifest(previous, store);
        store.setItem(CURRENT_MANIFEST_KEY, JSON.stringify(emergency));

        expect(restorePreEmergencyManifest(store)?.version).toBe(4);
        expect(loadCurrentManifest(store)?.version).toBe(4);
        expect(store.getItem(PRE_EMERGENCY_MANIFEST_KEY)).toBeNull();
    });

    it('never checkpoints an emergency manifest as the normal layout', () => {
        const store = memoryStore();
        const emergency = manifest(5);
        emergency.emergency = {
            id: 9,
            severity: 'emergency',
            title: 'Evacuate',
            message: '',
            instructions: '',
            background: '#b91c1c',
            image_asset_key: null,
            video_asset_key: null,
            starts_at: null,
            expires_at: null,
        };

        rememberPreEmergencyManifest(emergency, store);

        expect(store.getItem(PRE_EMERGENCY_MANIFEST_KEY)).toBeNull();
    });
});

describe('telemetry queue', () => {
    it('returns an empty queue for missing or corrupt data', () => {
        expect(parseTelemetryQueue(memoryStore())).toEqual([]);
        expect(
            parseTelemetryQueue(
                memoryStore({ [TELEMETRY_QUEUE_KEY]: '{broken' }),
            ),
        ).toEqual([]);
        expect(
            parseTelemetryQueue(
                memoryStore({ [TELEMETRY_QUEUE_KEY]: '{"not":"array"}' }),
            ),
        ).toEqual([]);
    });

    it('enqueues items with a queued timestamp', () => {
        const store = memoryStore();

        enqueueTelemetry(
            { type: 'playback', payload: { item_key: 'item:1' } },
            store,
        );

        const queue = parseTelemetryQueue(store);

        expect(queue).toHaveLength(1);
        expect(queue[0].type).toBe('playback');
        expect(queue[0].payload).toEqual({ item_key: 'item:1' });
        expect(typeof queue[0].queued_at).toBe('string');
    });

    it('trims the queue to the newest items when full', () => {
        const store = memoryStore();

        for (let index = 0; index < 10; index++) {
            enqueueTelemetry(
                { type: 'heartbeat', payload: { index } },
                store,
                5,
            );
        }

        const queue = parseTelemetryQueue(store);

        expect(queue).toHaveLength(5);
        expect(queue[0].payload).toEqual({ index: 5 });
        expect(queue[4].payload).toEqual({ index: 9 });
    });

    it('replaces the queue and clears it when empty', () => {
        const store = memoryStore();

        enqueueTelemetry({ type: 'heartbeat', payload: {} }, store);
        replaceTelemetryQueue(
            [
                {
                    type: 'playback',
                    payload: { item_key: 'item:9' },
                    queued_at: new Date().toISOString(),
                },
            ],
            store,
        );

        expect(parseTelemetryQueue(store)).toHaveLength(1);

        replaceTelemetryQueue([], store);

        expect(store.getItem(TELEMETRY_QUEUE_KEY)).toBeNull();
    });
});

describe('assetCacheUrls', () => {
    it('keeps only assets that have a url', () => {
        const assets = [
            { url: '/api/player/v1/assets/media/1' },
            { url: null },
            { url: 'https://cdn.example.com/clip.mp4' },
        ] as PlayerAsset[];

        expect(assetCacheUrls(assets)).toEqual([
            '/api/player/v1/assets/media/1',
            'https://cdn.example.com/clip.mp4',
        ]);
    });
});
