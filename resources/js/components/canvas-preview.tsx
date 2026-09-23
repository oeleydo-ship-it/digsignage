import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type ComponentProps,
} from 'react';
import {
    DesignElementContent,
    type MediaResolver,
} from '@/components/design-element';
import { Button } from '@/components/ui/button';
import { PLAYER_DESIGN_FIT, fitCanvasScale } from '@/lib/canvas-fit';
import { canonicalWidgetKey } from '@/lib/widget-keys';
import type { DesignDocument } from '@/types';

export default function CanvasPreview({
    document,
    maxWidth = 1280,
    fit = false,
    cover = false,
    timezone,
    resolveMedia,
}: {
    document: DesignDocument;
    maxWidth?: number;
    fit?: boolean;
    cover?: boolean;
    timezone?: string;
    resolveMedia?: MediaResolver;
}) {
    const host = useRef<HTMLDivElement>(null);
    const [available, setAvailable] = useState<{
        width: number;
        height: number;
    } | null>(null);

    const measureHost = () => {
        const node = host.current;

        if (!node) {
            return null;
        }

        const { width, height } = node.getBoundingClientRect();

        if (width <= 0 || height <= 0) {
            return null;
        }

        return { width, height };
    };

    useLayoutEffect(() => {
        if (!fit) {
            return;
        }

        setAvailable(measureHost());
    }, [fit, document.width, document.height]);

    useEffect(() => {
        if (!fit || !host.current) {
            return;
        }

        const observer = new ResizeObserver(() => {
            setAvailable(measureHost());
        });
        observer.observe(host.current);
        const onViewport = () => setAvailable(measureHost());
        window.addEventListener('resize', onViewport);
        window.visualViewport?.addEventListener('resize', onViewport);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', onViewport);
            window.visualViewport?.removeEventListener('resize', onViewport);
        };
    }, [fit, document.width, document.height]);

    const scale = fit
        ? (() => {
              const bounds = available ?? measureHost();

              if (!bounds) {
                  return 1;
              }

              return fitCanvasScale(
                  bounds.width,
                  bounds.height,
                  document.width,
                  document.height,
                  cover ? 'cover' : PLAYER_DESIGN_FIT,
              );
          })()
        : Math.min(maxWidth, document.width) / document.width;
    const ordered = [...document.elements]
        .filter((element) => !element.hidden)
        .sort((a, b) => a.zIndex - b.zIndex);
    const letterbox = document.background || '#000';

    return (
        <div
            ref={host}
            className={
                fit
                    ? 'flex h-full min-h-0 w-full min-w-0 items-center justify-center overflow-hidden'
                    : undefined
            }
            style={
                fit
                    ? {
                          height: '100%',
                          width: '100%',
                          background: letterbox,
                      }
                    : undefined
            }
        >
            <div
                className={
                    fit
                        ? 'relative shrink-0 overflow-hidden'
                        : 'relative shrink-0 overflow-hidden shadow-xl'
                }
                style={{
                    width: document.width * scale,
                    height: document.height * scale,
                    background: document.background,
                }}
            >
                {ordered.map((element) => {
                    const fullscreen =
                        canonicalWidgetKey(element.type) === 'web_page' &&
                        (element.props.fullscreen === true ||
                            element.props.fullscreen === 'true' ||
                            element.props.fullscreen === 1);

                    return (
                    <div
                        key={element.id}
                        className="absolute overflow-hidden text-white"
                        style={{
                            left: fullscreen ? 0 : element.x * scale,
                            top: fullscreen ? 0 : element.y * scale,
                            width: fullscreen
                                ? document.width * scale
                                : element.width * scale,
                            height: fullscreen
                                ? document.height * scale
                                : element.height * scale,
                            opacity: element.opacity,
                            transform: `rotate(${fullscreen ? 0 : element.rotation}deg)`,
                            zIndex: fullscreen
                                ? element.zIndex + 1000
                                : element.zIndex,
                            background:
                                element.type === 'shape' ||
                                element.type === 'button'
                                    ? String(element.props.fill ?? '#2563eb')
                                    : 'transparent',
                            borderRadius:
                                Number(element.props.radius ?? 0) * scale,
                        }}
                    >
                        <DesignElementContent
                            element={element}
                            scale={scale}
                            timezone={timezone}
                            resolveMedia={resolveMedia}
                        />
                    </div>
                    );
                })}
            </div>
        </div>
    );
}

export function FullscreenCanvasPreview({
    document,
    timezone,
    resolveMedia,
    closeHref,
    onClose,
}: {
    document: DesignDocument;
    timezone?: string;
    resolveMedia?: MediaResolver;
    closeHref?: ComponentProps<typeof Link>['href'];
    onClose?: () => void;
}) {
    const closeButton = (
        <Button
            type="button"
            size="icon"
            variant="secondary"
            className="absolute top-4 left-1/2 z-10 size-10 -translate-x-1/2 rounded-full bg-black/70 text-white shadow-lg hover:bg-black/90"
            aria-label="Close preview"
            onClick={onClose}
        >
            <X className="size-5" />
        </Button>
    );

    return (
        <div className="fixed inset-0 z-[100] bg-black">
            {closeHref ? (
                <Button
                    asChild
                    size="icon"
                    variant="secondary"
                    className="absolute top-4 left-1/2 z-10 size-10 -translate-x-1/2 rounded-full bg-black/70 text-white shadow-lg hover:bg-black/90"
                >
                    <Link href={closeHref} aria-label="Close preview">
                        <X className="size-5" />
                    </Link>
                </Button>
            ) : (
                closeButton
            )}
            <CanvasPreview
                document={document}
                fit
                timezone={timezone}
                resolveMedia={resolveMedia}
            />
        </div>
    );
}
