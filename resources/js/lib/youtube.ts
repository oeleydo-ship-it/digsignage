const YOUTUBE_ID = /^[\w-]{11}$/;
const YOUTUBE_HOSTS = new Set([
    'youtu.be',
    'youtube.com',
    'm.youtube.com',
    'music.youtube.com',
    'youtube-nocookie.com',
]);

function hostWithoutWww(hostname: string): string {
    return hostname.replace(/^www\./i, '').toLowerCase();
}

function validId(value: string | null | undefined): string | null {
    const id = value?.trim() ?? '';

    return YOUTUBE_ID.test(id) ? id : null;
}

/**
 * Extract an 11-character video id from watch, share, embed, Shorts, and live URLs.
 */
export function youtubeId(url: string): string | null {
    const raw = url.trim();

    if (raw === '') {
        return null;
    }

    const bare = validId(raw);

    if (bare) {
        return bare;
    }

    try {
        const parsed = new URL(raw.includes('://') ? raw : `https://${raw}`);
        const host = hostWithoutWww(parsed.hostname);

        if (!YOUTUBE_HOSTS.has(host)) {
            return null;
        }

        const fromQuery = validId(parsed.searchParams.get('v'));

        if (fromQuery) {
            return fromQuery;
        }

        const parts = parsed.pathname.split('/').filter(Boolean);
        const markers = new Set(['embed', 'shorts', 'live', 'v', 'e']);

        for (let index = 0; index < parts.length; index++) {
            if (!markers.has(parts[index].toLowerCase())) {
                continue;
            }

            const candidate = validId(parts[index + 1]?.split(/[?#]/)[0]);

            if (candidate) {
                return candidate;
            }
        }

        if (host === 'youtu.be') {
            return validId(parts[0]?.split(/[?#]/)[0]);
        }
    } catch {
        return null;
    }

    return null;
}

export function youtubeEmbedSrc(id: string, origin?: string): string {
    const params = new URLSearchParams({
        autoplay: '1',
        mute: '1',
        loop: '1',
        playlist: id,
        rel: '0',
        enablejsapi: '1',
    });

    if (origin) {
        params.set('origin', origin);
        params.set('widget_referrer', origin);
    }

    return `https://www.youtube.com/embed/${id}?${params.toString()}`;
}

/** Embedding disabled (101/150) or player configuration / referrer errors (153). */
export function youtubeEmbedBlocked(code: number): boolean {
    return code === 101 || code === 150 || code === 153;
}
