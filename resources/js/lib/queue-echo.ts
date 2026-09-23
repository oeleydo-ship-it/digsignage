import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { ReverbConfig } from '@/lib/player-echo';

export async function subscribeQueueUpdates(
    serviceId: number,
    reverb: ReverbConfig,
    onUpdate: () => void,
): Promise<() => void> {
    if (!reverb.enabled || !reverb.key) {
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
            Pusher,
        });

        const channel = 'queue.service.' + serviceId;
        let connectedOnce = false;
        const refreshAfterReconnect = () => {
            if (connectedOnce) onUpdate();
            connectedOnce = true;
        };
        echo.connector.pusher.connection.bind(
            'connected',
            refreshAfterReconnect,
        );
        echo.channel(channel).listen('.updated', onUpdate);

        return () => {
            echo.connector.pusher.connection.unbind(
                'connected',
                refreshAfterReconnect,
            );
            echo.leave(channel);
            echo.disconnect();
        };
    } catch {
        return () => undefined;
    }
}

export async function subscribeQueueDashboardUpdates(
    serviceIds: number[],
    reverb: ReverbConfig,
    onUpdate: () => void,
): Promise<() => void> {
    if (!reverb.enabled || !reverb.key || serviceIds.length === 0) {
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
            Pusher,
        });
        const channels = [...new Set(serviceIds)].map(
            (serviceId) => 'queue.service.' + serviceId,
        );
        let connectedOnce = false;
        const refreshAfterReconnect = () => {
            if (connectedOnce) onUpdate();
            connectedOnce = true;
        };
        echo.connector.pusher.connection.bind(
            'connected',
            refreshAfterReconnect,
        );

        channels.forEach((channel) => {
            echo.channel(channel).listen('.updated', onUpdate);
        });

        return () => {
            echo.connector.pusher.connection.unbind(
                'connected',
                refreshAfterReconnect,
            );
            channels.forEach((channel) => echo.leave(channel));
            echo.disconnect();
        };
    } catch {
        return () => undefined;
    }
}
