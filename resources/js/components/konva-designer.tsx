import { useEffect, useRef, useState } from 'react';
import Konva from 'konva';
import {
    Stage,
    Layer,
    Group,
    Rect,
    Text,
    Image as CanvasImage,
    Transformer,
} from 'react-konva';
import { DesignElementContent } from '@/components/design-element';
import { canonicalWidgetKey, isWidgetType } from '@/lib/widget-keys';
import { clampWidgetFontSize } from '@/lib/widget-style';
import type { DesignDocument, DesignDocumentElement } from '@/types';

type OverlayFrame = {
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
};

function isTruthySetting(value: unknown): boolean {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function overlayFrame(
    element: DesignDocumentElement,
    document: DesignDocument,
    live?: OverlayFrame,
): OverlayFrame {
    const fullscreen =
        canonicalWidgetKey(element.type) === 'web_page' &&
        isTruthySetting(element.props.fullscreen);

    if (fullscreen) {
        return {
            x: 0,
            y: 0,
            width: document.width,
            height: document.height,
            rotation: 0,
        };
    }

    return (
        live ?? {
            x: element.x,
            y: element.y,
            width: element.width,
            height: element.height,
            rotation: element.rotation,
        }
    );
}

type Props = {
    document: DesignDocument;
    zoom: number;
    grid: number;
    selectedId: string | null;
    editable: boolean;
    timezone?: string;
    onSelect: (id: string | null) => void;
    onChange: (element: DesignDocumentElement) => void;
};

function ElementContent({ element }: { element: DesignDocumentElement }) {
    const [image, setImage] = useState<HTMLImageElement | HTMLVideoElement>();
    const [failed, setFailed] = useState(false);
    const [now, setNow] = useState(() => new Date());
    const imageNode = useRef<Konva.Image>(null);
    const source = String(element.props.src ?? element.props.url ?? '');
    const video = ['video', 'live_stream'].includes(element.type);
    useEffect(() => {
        setImage(undefined);
        setFailed(false);
        if (
            !source ||
            !['image', 'logo'].includes(element.type)
        )
            return;
        let active = true;
        const media = video
            ? window.document.createElement('video')
            : new window.Image();
        const ready = () => {
            if (active) setImage(media);
        };
        media.addEventListener(video ? 'loadeddata' : 'load', ready);
        media.onerror = () => {
            if (active) setFailed(true);
        };
        if (media instanceof HTMLVideoElement) {
            media.muted = true;
            media.loop = true;
            media.playsInline = true;
        }
        media.src = source;
        if (media instanceof HTMLVideoElement)
            void media.play().catch(() => undefined);
        return () => {
            active = false;
            media.removeEventListener(video ? 'loadeddata' : 'load', ready);
            media.onerror = null;
            if (media instanceof HTMLVideoElement) {
                media.pause();
                media.removeAttribute('src');
                media.load();
            }
        };
    }, [source, video, element.type]);
    useEffect(() => {
        if (!video || !image) return;
        const animation = new Konva.Animation(
            () => undefined,
            imageNode.current?.getLayer(),
        );
        animation.start();
        return () => {
            animation.stop();
        };
    }, [video, image]);
    useEffect(() => {
        if (!['clock', 'date'].includes(element.type)) return;
        const timer = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(timer);
    }, [element.type]);
    const p = element.props;
    if (image) {
        const iw =
            image instanceof HTMLVideoElement
                ? image.videoWidth
                : image.naturalWidth;
        const ih =
            image instanceof HTMLVideoElement
                ? image.videoHeight
                : image.naturalHeight;
        if (iw && ih) {
            const cover = p.objectFit !== 'contain';
            const ratio = (cover ? Math.max : Math.min)(
                element.width / iw,
                element.height / ih,
            );
            const zoom = Number(p.imageZoom ?? 1);
            const width = iw * ratio;
            const height = ih * ratio;
            return (
                <CanvasImage
                    ref={imageNode}
                    image={image}
                    listening={false}
                    width={width * zoom}
                    height={height * zoom}
                    x={
                        (((element.width - width) * Number(p.imageX ?? 50)) /
                            100) *
                            zoom +
                        (element.width / 2) * (1 - zoom)
                    }
                    y={
                        (((element.height - height) * Number(p.imageY ?? 50)) /
                            100) *
                            zoom +
                        (element.height / 2) * (1 - zoom)
                    }
                />
            );
        }
    }
    if (
        element.type === 'shape' ||
        element.type === 'video' ||
        element.type === 'live_stream' ||
        isWidgetType(element.type)
    )
        return null;
    let text = String(p.text ?? p.label ?? element.name);
    if (['image', 'logo', 'video', 'live_stream'].includes(element.type))
        text = failed
            ? 'Media unavailable'
            : source
              ? 'Loading media…'
              : 'Choose media';
    if (element.type === 'clock')
        text = now.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    if (element.type === 'date')
        text = now.toLocaleDateString([], {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    if (['iframe', 'web_page'].includes(element.type))
        text = `Web content · ${String(p.url ?? '')}`;
    return (
        <Text
            text={text}
            width={element.width}
            height={element.height}
            padding={Number(p.padding ?? 8)}
            fontSize={clampWidgetFontSize(p.fontSize, 24)}
            fontFamily={String(p.fontFamily ?? 'Arial')}
            fontStyle={
                String(p.fontWeight ?? '400') === '700' ? 'bold' : 'normal'
            }
            fill={String(p.color ?? '#ffffff')}
            align={String(p.align ?? 'left')}
            verticalAlign="middle"
            lineHeight={Number(p.lineHeight ?? 1.2)}
            listening={false}
        />
    );
}

function readOverlayFrame(
    group: Konva.Group,
    element: DesignDocumentElement,
): OverlayFrame {
    const scaleX = group.scaleX();
    const scaleY = group.scaleY();

    return {
        x: group.x() - element.width * scaleX * 0.5,
        y: group.y() - element.height * scaleY * 0.5,
        width: Math.max(20, element.width * scaleX),
        height: Math.max(20, element.height * scaleY),
        rotation: group.rotation(),
    };
}

function CanvasElement({
    element,
    selected,
    editable,
    grid,
    document,
    onSelect,
    onChange,
    onOverlayFrame,
}: { element: DesignDocumentElement; selected: boolean } & Omit<
    Props,
    'zoom' | 'selectedId'
> & {
    onOverlayFrame?: (id: string, frame: OverlayFrame | null) => void;
}) {
    const node = useRef<Konva.Group>(null);
    const transformer = useRef<Konva.Transformer>(null);
    useEffect(() => {
        if (selected && editable && !element.locked && node.current)
            transformer.current?.nodes([node.current]);
    }, [selected, editable, element]);
    const persist = () => {
        const group = node.current;
        if (!group) return;
        const width = Math.min(
            document.width,
            Math.max(20, element.width * group.scaleX()),
        );
        const height = Math.min(
            document.height,
            Math.max(20, element.height * group.scaleY()),
        );
        const x = Math.max(
            0,
            Math.min(
                document.width - width,
                Math.round((group.x() - width / 2) / grid) * grid,
            ),
        );
        const y = Math.max(
            0,
            Math.min(
                document.height - height,
                Math.round((group.y() - height / 2) / grid) * grid,
            ),
        );
        group.scale({ x: 1, y: 1 });
        group.position({ x: x + width / 2, y: y + height / 2 });
        onChange({
            ...element,
            x,
            y,
            width,
            height,
            rotation: group.rotation(),
        });
    };
    return (
        <>
            <Group
                ref={node}
                id={element.id}
                x={element.x + element.width / 2}
                y={element.y + element.height / 2}
                offsetX={element.width / 2}
                offsetY={element.height / 2}
                width={element.width}
                height={element.height}
                rotation={element.rotation}
                opacity={element.opacity}
                draggable={editable && !element.locked}
                onMouseDown={() => onSelect(element.id)}
                onTap={() => onSelect(element.id)}
                onDragMove={() => {
                    if (node.current) {
                        onOverlayFrame?.(
                            element.id,
                            readOverlayFrame(node.current, element),
                        );
                    }
                }}
                onTransform={() => {
                    if (node.current) {
                        onOverlayFrame?.(
                            element.id,
                            readOverlayFrame(node.current, element),
                        );
                    }
                }}
                onDragEnd={() => {
                    persist();
                    onOverlayFrame?.(element.id, null);
                }}
                onTransformEnd={() => {
                    persist();
                    onOverlayFrame?.(element.id, null);
                }}
            >
                <Rect
                    width={element.width}
                    height={element.height}
                    fill={
                        ['shape', 'button'].includes(element.type)
                            ? String(element.props.fill ?? '#2563eb')
                            : 'rgba(0,0,0,0)'
                    }
                    cornerRadius={Number(element.props.radius ?? 0)}
                />
                <Group
                    clipX={0}
                    clipY={0}
                    clipWidth={element.width}
                    clipHeight={element.height}
                    listening={false}
                >
                    <ElementContent element={element} />
                </Group>
            </Group>
            {selected && editable && !element.locked && (
                <Transformer
                    ref={transformer}
                    flipEnabled={false}
                    keepRatio={false}
                    rotateEnabled
                    rotationSnaps={[0, 90, 180, 270]}
                    anchorSize={10}
                    borderStroke="#0ea5e9"
                    anchorStroke="#0284c7"
                    boundBoxFunc={(oldBox, nextBox) =>
                        Math.abs(nextBox.width) < 10 ||
                        Math.abs(nextBox.height) < 10
                            ? oldBox
                            : nextBox
                    }
                />
            )}
        </>
    );
}

export default function KonvaDesigner(props: Props) {
    const width = props.document.width * props.zoom;
    const height = props.document.height * props.zoom;
    const visible = [...props.document.elements]
        .filter((element) => !element.hidden)
        .sort((a, b) => a.zIndex - b.zIndex);
    const [liveFrames, setLiveFrames] = useState<Record<string, OverlayFrame>>(
        {},
    );

    return (
        <div
            data-canvas
            className="relative isolate overflow-hidden shadow-xl"
            style={{
                width,
                height,
                background: props.document.background,
            }}
        >
            <div className="absolute inset-0 z-0">
                <Stage
                    width={width}
                    height={height}
                    scaleX={props.zoom}
                    scaleY={props.zoom}
                    onMouseDown={(e) => {
                        if (
                            e.target === e.target.getStage() ||
                            e.target.name() === 'background'
                        )
                            props.onSelect(null);
                    }}
                    onTouchStart={(e) => {
                        if (e.target.name() === 'background') props.onSelect(null);
                    }}
                >
                    <Layer>
                        <Rect
                            name="background"
                            width={props.document.width}
                            height={props.document.height}
                            fill="rgba(0,0,0,0)"
                        />
                        {visible.map((element) => (
                            <CanvasElement
                                key={element.id}
                                {...props}
                                element={element}
                                selected={props.selectedId === element.id}
                                onOverlayFrame={(id, frame) => {
                                    setLiveFrames((current) => {
                                        if (frame === null) {
                                            if (!(id in current)) {
                                                return current;
                                            }

                                            const next = { ...current };
                                            delete next[id];

                                            return next;
                                        }

                                        return { ...current, [id]: frame };
                                    });
                                }}
                            />
                        ))}
                    </Layer>
                </Stage>
            </div>
            <div
                className="pointer-events-none absolute top-0 left-0 z-20 origin-top-left overflow-hidden"
                style={{
                    width: props.document.width,
                    height: props.document.height,
                    transform: `scale(${props.zoom})`,
                    transformOrigin: 'top left',
                }}
            >
                {visible
                    .filter(
                        (element) =>
                            isWidgetType(element.type) ||
                            ['video', 'live_stream'].includes(element.type),
                    )
                    .map((element) => {
                        const frame = overlayFrame(
                            element,
                            props.document,
                            liveFrames[element.id],
                        );

                        return (
                            <div
                                key={element.id}
                                className="absolute overflow-hidden"
                                style={{
                                    left: frame.x,
                                    top: frame.y,
                                    width: frame.width,
                                    height: frame.height,
                                    opacity: element.opacity,
                                    transform: `rotate(${frame.rotation}deg)`,
                                    zIndex: element.zIndex,
                                }}
                            >
                                <DesignElementContent
                                    element={element}
                                    timezone={props.timezone}
                                />
                            </div>
                        );
                    })}
            </div>
        </div>
    );
}
