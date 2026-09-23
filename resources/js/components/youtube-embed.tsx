import { useEffect, useRef, useState } from 'react';
import {
    youtubeEmbedBlocked,
    youtubeEmbedSrc,
} from '@/lib/youtube';

const YOUTUBE_ORIGINS = new Set([
    'https://www.youtube.com',
    'https://www.youtube-nocookie.com',
]);

function parsePlayerMessage(data: unknown): { event?: string; info?: unknown } | null {
    if (typeof data === 'string') {
        try {
            return JSON.parse(data) as { event?: string; info?: unknown };
        } catch {
            return null;
        }
    }

    if (data && typeof data === 'object') {
        return data as { event?: string; info?: unknown };
    }

    return null;
}

export default function YoutubeEmbed({ videoId }: { videoId: string }) {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const [blocked, setBlocked] = useState(false);
    const origin =
        typeof window === 'undefined' ? undefined : window.location.origin;

    useEffect(() => {
        setBlocked(false);

        const onMessage = (event: MessageEvent) => {
            if (!YOUTUBE_ORIGINS.has(event.origin)) {
                return;
            }

            const payload = parsePlayerMessage(event.data);

            if (payload?.event !== 'onError') {
                return;
            }

            if (youtubeEmbedBlocked(Number(payload.info))) {
                setBlocked(true);
            }
        };

        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, [videoId]);

    const handshake = () => {
        const frame = iframeRef.current?.contentWindow;

        if (!frame) {
            return;
        }

        frame.postMessage(JSON.stringify({ event: 'listening', id: 1 }), '*');
        frame.postMessage(
            JSON.stringify({
                event: 'command',
                func: 'addEventListener',
                args: ['onError'],
            }),
            '*',
        );
    };

    return (
        <div className="relative h-full w-full bg-black">
            <iframe
                ref={iframeRef}
                className="h-full w-full border-0"
                src={youtubeEmbedSrc(videoId, origin)}
                title="YouTube"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullScreen
                referrerPolicy="origin"
                onLoad={handshake}
            />
            {blocked ? (
                <div className="absolute inset-0 flex items-center justify-center bg-black/85 p-4 text-center text-white">
                    <p className="text-lg font-medium">
                        This video cannot be embedded
                    </p>
                </div>
            ) : null}
        </div>
    );
}
