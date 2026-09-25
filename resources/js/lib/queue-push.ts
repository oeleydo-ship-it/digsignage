/**
 * Browser push for queue tickets: registers /queue-sw.js and sends the
 * subscription to the server so ticket updates arrive with the page closed.
 */

export type PushState =
    | 'unsupported'
    | 'denied'
    | 'available'
    | 'subscribed'
    | 'working';

export function pushSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

function applicationServerKey(base64: string): Uint8Array<ArrayBuffer> {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = window.atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let index = 0; index < raw.length; index += 1) {
        bytes[index] = raw.charCodeAt(index);
    }

    return bytes;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function send(
    url: string,
    method: 'POST' | 'DELETE',
    body: unknown,
): Promise<void> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }
}

async function registration(): Promise<ServiceWorkerRegistration> {
    const existing =
        await navigator.serviceWorker.getRegistration('/queue-ticket/');

    return (
        existing ??
        navigator.serviceWorker.register('/queue-sw.js', {
            scope: '/queue-ticket/',
        })
    );
}

export async function currentPushState(): Promise<PushState> {
    if (!pushSupported()) {
        return 'unsupported';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    const existing =
        await navigator.serviceWorker.getRegistration('/queue-ticket/');
    const subscription = await existing?.pushManager.getSubscription();

    return subscription && Notification.permission === 'granted'
        ? 'subscribed'
        : 'available';
}

/** Ask permission, subscribe this browser and register it for the ticket. */
export async function subscribeToTicket(
    token: string,
    publicKey: string,
): Promise<PushState> {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return permission === 'denied' ? 'denied' : 'available';
    }

    const worker = await registration();
    await navigator.serviceWorker.ready;
    const subscription =
        (await worker.pushManager.getSubscription()) ??
        (await worker.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey(publicKey),
        }));
    const json = subscription.toJSON();
    const encodings = (
        PushManager as unknown as { supportedContentEncodings?: string[] }
    ).supportedContentEncodings;

    await send(`/queue-ticket/${token}/push`, 'POST', {
        endpoint: json.endpoint,
        keys: json.keys,
        content_encoding:
            encodings?.includes('aes128gcm') === false ? 'aesgcm' : 'aes128gcm',
    });

    return 'subscribed';
}

export async function unsubscribeFromTicket(token: string): Promise<PushState> {
    const worker =
        await navigator.serviceWorker.getRegistration('/queue-ticket/');
    const subscription = await worker?.pushManager.getSubscription();

    if (subscription) {
        await send(`/queue-ticket/${token}/push`, 'DELETE', {
            endpoint: subscription.endpoint,
        });
        await subscription.unsubscribe();
    }

    return 'available';
}
