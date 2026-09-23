export const EMBED_BLOCKED_MESSAGE = 'This site does not allow embedding';

export function isSameOriginUrl(url: string, origin = typeof window === 'undefined' ? '' : window.location.origin): boolean {
    try {
        return new URL(url, origin || 'https://example.com').origin === origin;
    } catch {
        return false;
    }
}

/**
 * After iframe `load`, a cross-origin document that allowed framing throws when
 * reading `location`. Chrome's X-Frame-Options / frame-ancestors error page is
 * same-origin and usually `about:blank` or a chrome-error URL.
 */
export function iframeAllowsEmbedding(frame: HTMLIFrameElement): boolean {
    try {
        const href = frame.contentWindow?.location.href ?? '';

        if (
            href === '' ||
            href === 'about:blank' ||
            href.startsWith('chrome-error:') ||
            href.startsWith('chrome://') ||
            href.startsWith('edge://')
        ) {
            return false;
        }

        const doc = frame.contentDocument;
        const root = doc?.documentElement;
        const body = doc?.body;

        if (root?.getAttribute('data-embed-blocked') === 'true') {
            return false;
        }

        if (body && body.childElementCount === 0 && (body.innerText ?? '').trim() === '') {
            return false;
        }

        return true;
    } catch {
        return true;
    }
}

export function webpageSandbox(url: string, origin?: string): string {
    const flags = [
        'allow-scripts',
        'allow-forms',
        'allow-popups',
        'allow-popups-to-escape-sandbox',
        'allow-presentation',
        'allow-downloads',
    ];

    if (!isSameOriginUrl(url, origin)) {
        flags.splice(1, 0, 'allow-same-origin');
    }

    return flags.join(' ');
}
