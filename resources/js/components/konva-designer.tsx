import { useEffect, useRef, useState } from 'react';
import Konva from 'konva';
import {
    Stage,
    Layer,
    Group,
    Rect,
    Transformer,
} from 'react-konva';
import { DesignElementContent } from '@/components/design-element';
import { visibleDesignLayers } from '@/lib/design-layers';
import { canonicalWidgetKey } from '@/lib/widget-keys';
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
                    fill="rgba(0,0,0,0)"
                />
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
    const visible = visibleDesignLayers(props.document.elements);
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
            <div className="absolute inset-0 z-20">
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
                className="pointer-events-none absolute top-0 left-0 z-0 origin-top-left overflow-hidden"
                style={{
                    width: props.document.width,
                    height: props.document.height,
                    transform: `scale(${props.zoom})`,
                    transformOrigin: 'top left',
                }}
            >
                {visible.map((element) => {
                    const frame = overlayFrame(
                        element,
                        props.document,
                        liveFrames[element.id],
                    );

                    return (
                        <div
                            key={element.id}
                            data-design-layer={element.id}
                            className="absolute overflow-hidden text-white"
                            style={{
                                left: frame.x,
                                top: frame.y,
                                width: frame.width,
                                height: frame.height,
                                opacity: element.opacity,
                                transform: `rotate(${frame.rotation}deg)`,
                                zIndex: canonicalWidgetKey(element.type) === 'web_page' && isTruthySetting(element.props.fullscreen)
                                    ? element.zIndex + 1000
                                    : element.zIndex,
                                background: ['shape', 'button'].includes(element.type)
                                    ? String(element.props.fill ?? '#2563eb')
                                    : 'transparent',
                                borderRadius: Math.max(0, Number(element.props.radius ?? 0)),
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
