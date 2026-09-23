import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { PlayerCommand } from '@/lib/player-commands';
import type { QueueVoiceSettings } from '@/types';

export type ReverbConfig = {
    enabled: boolean;
    key: string | null;
    host: string;
    port: number;
    scheme: string;
};

export type PlayerQueueUpdate = {
    team_id: number;
    service_id: number;
    ticket_id: number | null;
    ticket_number: string | null;
    status: string | null;
    counter_id: number | null;
    location_id: number | null;
    counter_name: string | null;
    called_at: string | null;
    voice: QueueVoiceSettings;
};

export type PlayerRealtimeState = 'connected' | 'reconnecting';

export async function subscribePlayerCommands(
    token: string,
    deviceUuid: string,
    reverb: ReverbConfig,
    onCommand: (command: PlayerCommand) => void,
    onQueueUpdate?: (update: PlayerQueueUpdate) => void,
    onConnectionState?: (state: PlayerRealtimeState) => void,
): Promise<() => void> {
    if (!reverb.enabled || !reverb.key || deviceUuid === '') {
        return () => undefined;
    }

    try {
        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverb.key,
            wsHost: reverb.host,
            wsPort: reverb.port,
            wssPort: reverb.port,
            forceTLS: reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/api/player/v1/broadcasting/auth',
            bearerToken: token,
            Pusher,
        });

        const channel = echo.private(`player.${deviceUuid}`);
        const connection = echo.connector.pusher.connection;
        const connected = () => onConnectionState?.('connected');
        const reconnecting = () => onConnectionState?.('reconnecting');

        connection.bind('connected', connected);
        connection.bind('connecting', reconnecting);
        connection.bind('unavailable', reconnecting);
        connection.bind('disconnected', reconnecting);
        onConnectionState?.(
            connection.state === 'connected' ? 'connected' : 'reconnecting',
        );
        channel.listen('.command', onCommand);

        if (onQueueUpdate) {
            channel.listen('.updated', onQueueUpdate);
        }

        return () => {
            connection.unbind('connected', connected);
            connection.unbind('connecting', reconnecting);
            connection.unbind('unavailable', reconnecting);
            connection.unbind('disconnected', reconnecting);
            echo.leave(`player.${deviceUuid}`);
            echo.disconnect();
        };
    } catch {
        return () => undefined;
    }
}
