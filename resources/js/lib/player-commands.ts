import {
    ASSET_CACHE_NAME,
    PLAYER_TOKEN_KEY,
    playerHeaders,
} from './player-runtime';

export type PlayerCommand = {
    command_id: number;
    screen_id: number;
    command: string;
    payload: Record<string, unknown>;
    status: string;
};

export type CommandHandlers = {
    sync: () => Promise<void>;
    reload: () => void;
    clearCache: () => Promise<void>;
    emergencyStart: (payload: Record<string, unknown>) => void;
    emergencyStop: (payload: Record<string, unknown>) => void;
};

export async function fetchPendingCommands(
    token: string,
): Promise<PlayerCommand[]> {
    const response = await fetch('/api/player/v1/commands', {
        headers: playerHeaders(token),
    });

    if (!response.ok) {
        throw new Error('Command poll failed');
    }

    const payload = (await response.json()) as { commands?: PlayerCommand[] };

    return payload.commands ?? [];
}

export async function acknowledgeCommand(
    token: string,
    commandId: number,
): Promise<void> {
    await fetch(`/api/player/v1/commands/${commandId}/ack`, {
        method: 'POST',
        headers: playerHeaders(token),
    });
}

export async function completeCommand(
    token: string,
    commandId: number,
    status: 'completed' | 'failed',
    result: Record<string, unknown> = {},
): Promise<void> {
    await fetch(`/api/player/v1/commands/${commandId}/complete`, {
        method: 'POST',
        headers: {
            ...playerHeaders(token),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ status, result }),
    });
}

export async function executePlayerCommand(
    command: PlayerCommand,
    handlers: CommandHandlers,
): Promise<Record<string, unknown>> {
    switch (command.command) {
        case 'restart-player':
            if (window.digsignagePlayer?.restart) {
                window.setTimeout(() => {
                    void window.digsignagePlayer?.restart?.();
                }, 250);

                return { action: 'restart' };
            }

            window.setTimeout(() => handlers.reload(), 250);

            return { action: 'reload' };
        case 'refresh':
        case 'reload':
            window.setTimeout(() => handlers.reload(), 250);

            return { action: 'reload' };
        case 'sync':
        case 'change-channel':
            await handlers.sync();

            return { action: 'sync' };
        case 'clear-cache':
            await handlers.clearCache();
            await handlers.sync();

            return { action: 'clear-cache' };
        case 'take-screenshot': {
            if (window.digsignagePlayer?.captureScreenshot) {
                const image = await window.digsignagePlayer.captureScreenshot();

                return {
                    action: 'take-screenshot',
                    captured: Boolean(image),
                    image,
                };
            }

            return {
                action: 'take-screenshot',
                captured: false,
                reason: 'Web player cannot capture the display without a user gesture.',
            };
        }
        case 'emergency-start':
            handlers.emergencyStart(command.payload);
            await handlers.sync();

            return {
                action: 'emergency-start',
                emergency_id: command.payload.emergency_id ?? null,
            };
        case 'emergency-stop':
            handlers.emergencyStop(command.payload);
            await handlers.sync();

            return { action: 'emergency-stop' };
        case 'update-settings':
            return { action: 'update-settings', applied: command.payload };
        default:
            throw new Error(`Unsupported command ${command.command}`);
    }
}

export async function clearPlayerCaches(): Promise<void> {
    if (!('caches' in window)) {
        return;
    }

    const keys = await caches.keys();

    await Promise.all(
        keys
            .filter(
                (key) =>
                    key === ASSET_CACHE_NAME ||
                    key.startsWith('digsignage-player-shell-'),
            )
            .map((key) => caches.delete(key)),
    );
}

export function forgetPlayerToken(): void {
    window.localStorage.removeItem(PLAYER_TOKEN_KEY);
}
