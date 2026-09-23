import { describe, expect, it } from 'vitest';
import { applyQueueCallToManifest, manifestHasQueueWidgets, manifestUsesQueueUpdate, preserveQueueServingRows, queueSoundsFromManifest } from './queue-live-manifest';
import type { PlayerQueueUpdate } from './player-echo';
import type { PlayerManifest } from './player-runtime';

const update: PlayerQueueUpdate = {
    team_id: 1,
    service_id: 2,
    ticket_id: 12,
    ticket_number: 'REG005',
    status: 'called',
    counter_id: 4,
    location_id: null,
    counter_name: 'Counter 2',
    called_at: '2026-09-23T12:00:00Z',
    voice: { enabled: false, languages: ['en-US'], voice: null, speed: 1, volume: 1, repeat_count: 1, chime: false, announcement_delay_seconds: 0 },
};

function manifest(): PlayerManifest {
    return {
        version: 5,
        generated_at: '',
        screen: { id: 1, name: 'Lobby', orientation: 'landscape', width: 1920, height: 1080, timezone: 'UTC' },
        playback: {
            source: '', schedule_id: null, schedule_name: null, type: 'playlist', channel: null, live: null, zones: [],
            playlist: {
                id: 1, name: 'Queue', loop: true, version: 1,
                items: [{
                    id: 1, key: 'queue', type: 'widget', title: 'Queue', duration_seconds: 30,
                    transition: 'none', transition_ms: 0, asset_key: null, document: null, url: null,
                    widget_key: 'queue_now_serving',
                    widget: { key: 'queue_now_serving', settings: { service_id: 2, limit: 5 }, data: { now_serving: [
                        { id: 10, number: 'REG004', counter: 'Counter 2' },
                        { id: 11, number: 'REG003', counter: 'Counter 1' },
                    ] } },
                }],
            },
        },
        assets: [], heartbeat_seconds: 30, poll_seconds: 30,
    };
}

describe('live queue call on player manifest', () => {
    it('replaces only the called counter and keeps other counters visible', () => {
        const before = manifest();
        const after = applyQueueCallToManifest(before, update);
        const rows = after.playback.playlist?.items[0].widget?.data.now_serving;

        expect(rows).toMatchObject([
            { id: 12, number: 'REG005', counter: 'Counter 2' },
            { id: 11, number: 'REG003', counter: 'Counter 1' },
        ]);
        expect(before.playback.playlist?.items[0].widget?.data.now_serving).toHaveLength(2);
    });

    it('fills an empty board immediately and ignores an unrelated service', () => {
        const before = manifest();
        before.playback.playlist!.items[0].widget!.data.now_serving = [];

        expect(applyQueueCallToManifest(before, update).playback.playlist?.items[0].widget?.data.now_serving)
            .toMatchObject([{ number: 'REG005' }]);
        expect(applyQueueCallToManifest(before, { ...update, service_id: 99 })).toBe(before);
    });

    it('updates the combined board without replacing a different counter with the same name', () => {
        const before = manifest();
        const widget = before.playback.playlist!.items[0].widget!;
        widget.key = 'queue_board';
        widget.data.now_serving = [
            { id: 10, number: 'REG004', counter: 'Desk', counter_id: 4 },
            { id: 11, number: 'REG003', counter: 'Desk', counter_id: 5 },
        ];

        const after = applyQueueCallToManifest(before, { ...update, counter_name: 'Desk' });

        expect(after.playback.playlist!.items[0].widget!.data.now_serving).toMatchObject([
            { id: 12, number: 'REG005', counter_id: 4 },
            { id: 11, number: 'REG003', counter_id: 5 },
        ]);
        expect(after.playback.playlist!.items[0].widget!.data.highlight_ticket_id).toBe(12);
    });

    it('keeps serving counters visible through a transient kiosk snapshot', () => {
        const before = manifest();
        before.playback.playlist!.items[0].widget!.data.now_serving = [
            { id: 10, number: 'REG004', counter: 'Counter 2', counter_id: 4 },
            { id: 11, number: 'REG003', counter: 'Counter 1', counter_id: 5 },
        ];
        const stale = structuredClone(before);
        stale.version = 6;
        stale.playback.playlist!.items[0].widget!.data.now_serving = [];
        stale.playback.playlist!.items[0].widget!.data.waiting = [{ id: 15, number: 'REG006', position: 1 }];

        const stable = preserveQueueServingRows(before, stale);
        expect(stable.playback.playlist!.items[0].widget!.data.now_serving).toMatchObject([
            { id: 10, number: 'REG004' },
            { id: 11, number: 'REG003' },
        ]);
        expect(stable.playback.playlist!.items[0].widget!.data.waiting).toMatchObject([{ number: 'REG006' }]);
        expect(preserveQueueServingRows(before, stale, new Set([4])).playback.playlist!.items[0].widget!.data.now_serving)
            .toMatchObject([{ id: 11, number: 'REG003' }]);
    });

    it('keeps another counter when a call is overlaid onto a transient snapshot', () => {
        const before = manifest();
        before.playback.playlist!.items[0].widget!.data.now_serving = [
            { id: 10, number: 'REG004', counter: 'Counter 2', counter_id: 4 },
            { id: 11, number: 'REG003', counter: 'Counter 1', counter_id: 5 },
        ];
        const stale = structuredClone(before);
        stale.playback.playlist!.items[0].widget!.data.now_serving = [];

        const next = applyQueueCallToManifest(preserveQueueServingRows(before, stale, new Set([4])), update);
        expect(next.playback.playlist!.items[0].widget!.data.now_serving).toMatchObject([
            { id: 12, number: 'REG005', counter_id: 4 },
            { id: 11, number: 'REG003', counter_id: 5 },
        ]);
    });

    it('recognizes queue boards for fast polling when Reverb disconnects', () => {
        const board = manifest();
        expect(manifestHasQueueWidgets(board)).toBe(true);
        board.playback.playlist!.items[0].widget!.key = 'clock';
        expect(manifestHasQueueWidgets(board)).toBe(false);
    });

    it('ignores a team-wide call for a service not shown on this player', () => {
        const board = manifest();
        expect(manifestUsesQueueUpdate(board, update)).toBe(true);
        expect(manifestUsesQueueUpdate(board, { ...update, service_id: 99 })).toBe(false);
    });

    it('updates queue widgets embedded in a design document', () => {
        const board = manifest();
        const item = board.playback.playlist!.items[0];
        item.document = { elements: [{ type: 'queue_now_serving', widget: item.widget }] };
        item.widget = null;

        const next = applyQueueCallToManifest(board, update);
        const element = (next.playback.playlist!.items[0].document!.elements as Array<{ widget: { data: Record<string, unknown> } }>)[0];
        expect(element.widget.data.now_serving).toMatchObject([{ number: 'REG005' }, { number: 'REG003' }]);
    });

    it('plays one chime for a newly called ticket found by polling', () => {
        const before = manifest();
        const after = manifest();
        after.playback.playlist!.items[0].widget!.settings.sound = true;
        after.playback.playlist!.items[0].widget!.data.now_serving = [{
            id: 12, number: 'REG005', counter: 'Counter 2', called_at: update.called_at,
        }];
        const now = Date.parse(update.called_at!);

        expect(queueSoundsFromManifest(before, after, now)).toMatchObject([{
            key: `12:${update.called_at}`,
            request: { ticketNumber: 'REG005', soundOnly: true, settings: { chime: true } },
        }]);
        expect(queueSoundsFromManifest(after, after, now)).toEqual([]);
        expect(queueSoundsFromManifest(before, after, now + 31_000)).toEqual([]);

        after.generated_at = '2026-09-23T12:00:01Z';
        expect(queueSoundsFromManifest(before, after, now + 3_600_000)).toHaveLength(1);
    });
});
