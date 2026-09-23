import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ReverbConfig } from './player-echo';
import { subscribePlayerCommands } from './player-echo';

const echo = vi.hoisted(() => {
    const listeners = new Map<string, () => void>();

    return {
        connection: {
            state: 'connecting',
            bind: vi.fn((event: string, callback: () => void) => {
                listeners.set(event, callback);
            }),
            unbind: vi.fn((event: string) => listeners.delete(event)),
        },
        channel: { listen: vi.fn() },
        disconnect: vi.fn(),
        leave: vi.fn(),
        private: vi.fn(() => ({ listen: echo.channel.listen })),
        emit: (event: string) => listeners.get(event)?.(),
        reset: () => {
            listeners.clear();
            echo.connection.state = 'connecting';
        },
    };
});

vi.mock('laravel-echo', () => ({
    default: class {
        connector = { pusher: { connection: echo.connection } };
        disconnect = echo.disconnect;
        leave = echo.leave;
        private = echo.private;
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

describe('player realtime connection', () => {
    it('reports reconnecting and connected states and removes all listeners', async () => {
        const onState = vi.fn();
        const unsubscribe = await subscribePlayerCommands(
            'token',
            'device-uuid',
            reverb,
            vi.fn(),
            vi.fn(),
            onState,
        );

        expect(onState).toHaveBeenLastCalledWith('reconnecting');
        echo.emit('connected');
        expect(onState).toHaveBeenLastCalledWith('connected');
        echo.emit('unavailable');
        expect(onState).toHaveBeenLastCalledWith('reconnecting');

        unsubscribe();
        expect(echo.connection.unbind).toHaveBeenCalledTimes(4);
        expect(echo.leave).toHaveBeenCalledWith('player.device-uuid');
        expect(echo.disconnect).toHaveBeenCalledOnce();
    });

    it('subscribes to screen manifest and queue updates on the device channel', async () => {
        const onQueue = vi.fn();
        const onManifest = vi.fn();
        const unsubscribe = await subscribePlayerCommands(
            'token',
            'device-uuid',
            reverb,
            vi.fn(),
            onQueue,
            undefined,
            onManifest,
        );

        expect(echo.private).toHaveBeenCalledWith('player.device-uuid');
        expect(echo.channel.listen).toHaveBeenCalledWith('.updated', onQueue);
        expect(echo.channel.listen).toHaveBeenCalledWith('.manifest.updated', onManifest);

        unsubscribe();
    });
});
