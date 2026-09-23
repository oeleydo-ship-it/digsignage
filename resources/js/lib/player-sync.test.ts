import { describe, expect, it } from 'vitest';
import { serializePlayerRefresh } from './player-sync';

describe('serializePlayerRefresh', () => {
    it('runs one follow-up refresh instead of overlapping manifest activations', async () => {
        const releases: Array<() => void> = [];
        let calls = 0;
        const refresh = serializePlayerRefresh(async () => {
            calls += 1;
            await new Promise<void>((resolve) => releases.push(resolve));
        });

        const first = refresh();
        await Promise.resolve();
        const second = refresh();
        const third = refresh();

        expect(calls).toBe(1);
        releases[0]();
        await second;
        await third;
        await Promise.resolve();

        expect(calls).toBe(2);
        releases[1]();
        await first;
        expect(calls).toBe(2);
    });
});
