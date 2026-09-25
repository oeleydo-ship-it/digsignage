/*
 * Shows queue ticket push notifications ("Ticket A102 has been called") and
 * opens the ticket page when one is tapped. Registered by the virtual ticket
 * page with scope /queue-ticket/.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Queue update', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Queue update', {
            body: data.body || '',
            tag: data.tag || undefined,
            renotify: Boolean(data.tag),
            requireInteraction: /called|next/i.test(data.body || ''),
            data: { url: data.url || '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (url && client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }

            return url ? self.clients.openWindow(url) : undefined;
        }),
    );
});
