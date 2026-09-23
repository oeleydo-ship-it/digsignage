import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    announcementText,
    BrowserSpeechVoiceProvider,
    manifestWantsQueueSound,
    manifestWantsQueueVoice,
    QueueAnnouncementQueue,
    shouldAnnounceQueueCall,
    type QueueVoiceProvider,
    type QueueVoiceRequest,
} from './queue-voice';
import type { PlayerManifest } from './player-runtime';
import type { PlayerQueueUpdate } from './player-echo';

const update = {
    team_id: 1, service_id: 7, ticket_id: 12, ticket_number: 'A105', status: 'called',
    counter_id: 4, location_id: 3, counter_name: 'Counter 4', called_at: '2026-09-22T10:00:00Z',
    voice: { enabled: true, languages: ['en-US'], voice: null, speed: 1, volume: 1, repeat_count: 1, chime: true, announcement_delay_seconds: 1 },
} satisfies PlayerQueueUpdate;

function manifest(settings: Record<string, string | number | boolean>): PlayerManifest {
    return {
        playback: { playlist: { items: [{ widget: { key: 'queue_now_serving', settings, data: {} } }] }, zones: [] },
    } as unknown as PlayerManifest;
}

class ControlledVoiceProvider implements QueueVoiceProvider {
    public readonly calls: string[] = [];
    public readonly finishes: Array<() => void> = [];
    public active = 0;
    public maximumActive = 0;
    public cancelled = 0;

    public announce(request: QueueVoiceRequest): Promise<void> {
        this.calls.push(request.ticketNumber);
        this.active += 1;
        this.maximumActive = Math.max(this.maximumActive, this.active);

        return new Promise((resolve) => {
            this.finishes.push(() => {
                this.active -= 1;
                resolve();
            });
        });
    }

    public finishNext(): void {
        this.finishes.shift()?.();
    }

    public cancel(): void {
        this.cancelled += 1;
        while (this.finishes.length > 0) this.finishNext();
    }
}

function request(ticketNumber: string, delay = 1): QueueVoiceRequest {
    return {
        ticketNumber,
        counterName: 'Counter 4',
        settings: { ...update.voice, announcement_delay_seconds: delay },
    };
}

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('queue voice announcements', () => {
    it('spells ticket digits and avoids duplicating the counter label', () => {
        expect(announcementText('A105', 'Counter 4', 'en-US'))
            .toBe('Ticket A one zero five, please proceed to Counter four.');
    });

    it('supports Arabic, Tagalog, and Hindi templates', () => {
        expect(announcementText('A10', '4', 'ar-AE')).toContain('صفر');
        expect(announcementText('A10', '4', 'fil-PH')).toContain('pumunta po');
        expect(announcementText('A10', '4', 'hi-IN')).toContain('कृपया');
    });

    it('requires an opted-in widget whose filters match the call', () => {
        expect(manifestWantsQueueVoice(manifest({ voice: true, service_id: '7', location_id: '3', counter_id: '4' }), update)).toBe(true);
        expect(manifestWantsQueueVoice(manifest({ voice: false, service_id: '7' }), update)).toBe(false);
        expect(manifestWantsQueueVoice(manifest({ voice: true, service_id: '8' }), update)).toBe(false);
    });

    it('respects the call sound setting independently of voice', () => {
        expect(manifestWantsQueueSound(manifest({ sound: true, voice: false, service_id: '7' }), update)).toBe(true);
        expect(manifestWantsQueueSound(manifest({ sound: false, voice: true, service_id: '7' }), update)).toBe(false);
        expect(manifestWantsQueueSound(manifest({ sound: true, service_id: '8' }), update)).toBe(false);
    });

    it('announces a fresh serving call or recall but ignores stale status changes', () => {
        const now = Date.parse(update.called_at);
        expect(shouldAnnounceQueueCall(update, now)).toBe(true);
        expect(shouldAnnounceQueueCall({ ...update, status: 'serving' }, now)).toBe(true);
        expect(shouldAnnounceQueueCall({ ...update, status: 'serving' }, now + 31_000)).toBe(false);
        expect(shouldAnnounceQueueCall({ ...update, status: 'waiting' }, now)).toBe(false);
    });

    it('plays a call chime without requiring speech synthesis', async () => {
        const started = vi.fn();
        const oscillator = {
            frequency: { value: 0 },
            connect: vi.fn(),
            start: started,
            stop: vi.fn(() => queueMicrotask(() => oscillator.onended?.())),
            onended: null as (() => void) | null,
        };
        class FakeAudioContext {
            currentTime = 0;
            destination = {};
            createOscillator = () => oscillator;
            createGain = () => ({ gain: { value: 0 }, connect: vi.fn() });
        }
        vi.stubGlobal('AudioContext', FakeAudioContext);

        await new BrowserSpeechVoiceProvider().announce({ ...request('A105'), soundOnly: true });

        expect(started).toHaveBeenCalledOnce();
    });

    it('unlocks and previews the call chime after a user gesture', async () => {
        const started = vi.fn();
        const oscillator = {
            frequency: { value: 0 }, connect: vi.fn(), start: started,
            stop: vi.fn(() => queueMicrotask(() => oscillator.onended?.())),
            onended: null as (() => void) | null,
        };
        const resume = vi.fn(async () => { context.state = 'running'; });
        const context = {
            state: 'suspended', currentTime: 0, destination: {}, resume,
            createOscillator: () => oscillator,
            createGain: () => ({ gain: { value: 0 }, connect: vi.fn() }),
        };
        vi.stubGlobal('AudioContext', class { constructor() { return context; } });

        expect(await new BrowserSpeechVoiceProvider().enableSound()).toBe(true);
        expect(resume).toHaveBeenCalledOnce();
        expect(started).toHaveBeenCalledOnce();
    });

    it('serializes calls and waits the configured delay without overlap', async () => {
        vi.useFakeTimers();
        const provider = new ControlledVoiceProvider();
        const queue = new QueueAnnouncementQueue(provider);

        queue.enqueue('1', request('A105'));
        queue.enqueue('2', request('A106'));
        queue.enqueue('3', request('B023'));
        expect(provider.calls).toEqual(['A105']);

        provider.finishNext();
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(999);
        expect(provider.calls).toEqual(['A105']);
        await vi.advanceTimersByTimeAsync(1);
        expect(provider.calls).toEqual(['A105', 'A106']);

        provider.finishNext();
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(1000);
        expect(provider.calls).toEqual(['A105', 'A106', 'B023']);
        expect(provider.maximumActive).toBe(1);
        provider.finishNext();
        await Promise.resolve();
        expect(queue.enqueue('1', request('A105'))).toBe(false);
    });

    it('suppresses duplicate calls and clears pending audio', () => {
        const provider = new ControlledVoiceProvider();
        const queue = new QueueAnnouncementQueue(provider);

        expect(queue.enqueue('same-call', request('A105', 0))).toBe(true);
        expect(queue.enqueue('same-call', request('A105', 0))).toBe(false);
        queue.enqueue('next-call', request('A106', 0));
        expect(queue.size).toBe(2);

        queue.clear();
        expect(queue.size).toBe(0);
        expect(provider.cancelled).toBe(1);
    });
});
