import { useEffect, useRef, useState } from 'react';
import {
    EMBED_BLOCKED_MESSAGE,
    iframeAllowsEmbedding,
    webpageSandbox,
} from '@/lib/embed';

export default function WebPageEmbed({
    url,
    title = 'Web page',
}: {
    url: string;
    title?: string;
}) {
    const frameRef = useRef<HTMLIFrameElement>(null);
    const [blocked, setBlocked] = useState(false);

    useEffect(() => {
        setBlocked(false);
    }, [url]);

    const onLoad = () => {
        window.setTimeout(() => {
            const frame = frameRef.current;

            if (!frame) {
                return;
            }

            setBlocked(!iframeAllowsEmbedding(frame));
        }, 150);
    };

    return (
        <div className="relative h-full w-full overflow-hidden bg-white">
            <iframe
                ref={frameRef}
                className="h-full w-full border-0 bg-white"
                src={url}
                title={title}
                sandbox={webpageSandbox(url)}
                allow="fullscreen; autoplay; encrypted-media; picture-in-picture"
                referrerPolicy="strict-origin-when-cross-origin"
                onLoad={onLoad}
            />
            {blocked ? (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-200 p-6 text-center text-slate-800">
                    <p className="text-lg font-semibold">{EMBED_BLOCKED_MESSAGE}</p>
                    <p className="max-w-md text-sm text-slate-600">
                        This URL sent X-Frame-Options or Content-Security-Policy
                        frame-ancestors that block iframes. Use a page that
                        allows embedding, such as example.com or your own site.
                    </p>
                </div>
            ) : null}
        </div>
    );
}
