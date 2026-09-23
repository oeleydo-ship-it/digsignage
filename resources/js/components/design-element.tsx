import { type CSSProperties, type ReactNode } from 'react';
import WidgetSurface from '@/components/widget-surface';
import { canonicalWidgetKey, isWidgetType } from '@/lib/widget-keys';
import { clampWidgetFontSize } from '@/lib/widget-style';
import type { DesignDocumentElement, WidgetPayload } from '@/types';

export type MediaResolver = (
    mediaId: number | null,
    fallback: string | null,
) => string | null;

function textAlign(value: unknown): CSSProperties['textAlign'] {
    return value === 'center' || value === 'right' ? value : 'left';
}

export function DesignElementContent({
    element,
    scale = 1,
    timezone = 'UTC',
    resolveMedia = (_mediaId, fallback) => fallback,
}: {
    element: DesignDocumentElement;
    scale?: number;
    timezone?: string;
    resolveMedia?: MediaResolver;
}) {
    const props = element.props;
    const mediaId = Number(props.media_id ?? 0) || null;
    const source = resolveMedia(
        mediaId,
        typeof props.src === 'string' ? props.src : null,
    );
    const fit = props.objectFit === 'contain' ? 'contain' : 'cover';
    const commonMediaClass = 'pointer-events-none h-full w-full select-none';
    const widgetKey = canonicalWidgetKey(element.widget?.key ?? element.type);

    if (element.widget || isWidgetType(element.type)) {
        const liveWidget: WidgetPayload = {
            key: element.widget?.key ?? widgetKey,
            data: element.widget?.data ?? props,
            settings: {
                ...(element.widget?.settings ?? {}),
                ...props,
            },
        };

        return (
            <div className="h-full w-full overflow-hidden">
                <WidgetSurface widget={liveWidget} timezone={timezone} />
            </div>
        );
    }

    if ((element.type === 'image' || element.type === 'logo') && source) {
        return (
            <img
                src={source}
                alt={element.name}
                draggable={false}
                className={commonMediaClass}
                style={{
                    objectFit: fit,
                    objectPosition: `${Number(props.imageX ?? 50)}% ${Number(props.imageY ?? 50)}%`,
                    transform: `scale(${Number(props.imageZoom ?? 1)})`,
                }}
            />
        );
    }

    if (
        (element.type === 'video' && (source || props.url)) ||
        (element.type === 'live_stream' && props.url)
    ) {
        return (
            <video
                src={source ?? String(props.url)}
                className={commonMediaClass}
                style={{ objectFit: fit }}
                autoPlay
                muted
                loop
                playsInline
            />
        );
    }

    if (element.type === 'shape') {
        return null;
    }

    if (['image', 'logo', 'video'].includes(element.type)) {
        return (
            <Placeholder>
                Choose {element.type === 'video' ? 'video' : 'image'} from Media
            </Placeholder>
        );
    }

    const text = String(props.text ?? props.label ?? element.name);

    return (
        <TextContent element={element} scale={scale}>
            {text}
        </TextContent>
    );
}

function TextContent({
    element,
    scale,
    children,
}: {
    element: DesignDocumentElement;
    scale: number;
    children: ReactNode;
}) {
    const props = element.props;

    return (
        <div
            className="flex h-full w-full items-center overflow-hidden whitespace-pre-wrap"
            style={{
                justifyContent:
                    props.align === 'center'
                        ? 'center'
                        : props.align === 'right'
                          ? 'flex-end'
                          : 'flex-start',
                padding: Math.max(2, Number(props.padding ?? 8) * scale),
                color: String(props.color ?? '#ffffff'),
                fontSize: clampWidgetFontSize(props.fontSize, 24) * scale,
                fontFamily: String(props.fontFamily ?? 'Arial'),
                fontWeight: String(props.fontWeight ?? '400'),
                lineHeight: Number(props.lineHeight ?? 1.2),
                textAlign: textAlign(props.align),
                borderRadius: Math.max(0, Number(props.radius ?? 0) * scale),
            }}
        >
            {children}
        </div>
    );
}

function Placeholder({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full w-full items-center justify-center bg-black/20 text-center text-white/70">
            {children}
        </div>
    );
}
