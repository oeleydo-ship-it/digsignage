import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ReverbConfig } from './player-echo';
import {
    subscribeQueueDashboardUpdates,
    subscribeQueueUpdates,
} from './queue-echo';

const echo = vi.hoisted(() => {
    let connected: (() => void) | null = null;

    return {
        bind: vi.fn((event: string, callback: () => void) => {
            if (event === 'connected') connected = callback;
        }),
        channel: vi.fn(() => ({ listen: vi.fn() })),
        disconnect: vi.fn(),
        leave: vi.fn(),
        unbind: vi.fn(),
        connect: () => connected?.(),
        reset: () => {
            connected = null;
        },
    };
});

vi.mock('laravel-echo', () => ({
    default: class {
        connector = { pusher: { connection: echo } };
        channel = echo.channel;
        disconnect = echo.disconnect;
        leave = echo.leave;
    },
}));
vi.mock('pusher-js', () => ({ default: class {} }));

const reverb: ReverbConfig = {
    enabled: true,
    key: 'test-key',
    host: 'localhost',
    port: 8080,
    scheme: 'http',
};

beforeEach(() => {
    vi.clearAllMocks();
    echo.reset();
});

describe('queue websocket subscriptions', () => {
    it('refreshes the service snapshot after a reconnect and cleans up listeners', async () => {
        const onUpdate = vi.fn();
        const unsubscribe = await subscribeQueueUpdates(7, reverb, onUpdate);

        echo.connect();
        expect(onUpdate).not.toHaveBeenCalled();
        echo.connect();
        expect(onUpdate).toHaveBeenCalledOnce();

        unsubscribe();
        expect(echo.unbind).toHaveBeenCalledWith('connected', expect.any(Function));
        expect(echo.leave).toHaveBeenCalledWith('queue.service.7');
        expect(echo.disconnect).toHaveBeenCalledOnce();
    });

    it('deduplicates dashboard channels and refreshes after reconnect', async () => {
        const onUpdate = vi.fn();
        const unsubscribe = await subscribeQueueDashboardUpdates(
            [7, 7, 8],
            reverb,
            onUpdate,
        );

        expect(echo.channel).toHaveBeenCalledTimes(2);
        echo.connect();
        echo.connect();
        expect(onUpdate).toHaveBeenCalledOnce();

        unsubscribe();
        expect(echo.leave).toHaveBeenCalledWith('queue.service.7');
        expect(echo.leave).toHaveBeenCalledWith('queue.service.8');
    });
});
