import type { DesignDocument, DesignDocumentElement } from '@/types';

export type AlignAction =
    | 'left'
    | 'center'
    | 'right'
    | 'top'
    | 'middle'
    | 'bottom';

export type StretchAction = 'width' | 'height' | 'canvas';

export type OrderAction = 'front' | 'forward' | 'backward' | 'back';

function clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(Math.max(value, minimum), Math.max(minimum, maximum));
}

/**
 * Position a single element against the canvas edges or centre lines.
 *
 * With one element selected there is no sibling bounding box to align to, so
 * the canvas is the reference — the same behaviour as every layout tool.
 */
export function alignToCanvas(
    element: DesignDocumentElement,
    document: Pick<DesignDocument, 'width' | 'height'>,
    action: AlignAction,
): { x: number; y: number } {
    const maxX = Math.max(0, document.width - element.width);
    const maxY = Math.max(0, document.height - element.height);

    switch (action) {
        case 'left':
            return { x: 0, y: element.y };
        case 'center':
            return { x: Math.round(maxX / 2), y: element.y };
        case 'right':
            return { x: maxX, y: element.y };
        case 'top':
            return { x: element.x, y: 0 };
        case 'middle':
            return { x: element.x, y: Math.round(maxY / 2) };
        case 'bottom':
            return { x: element.x, y: maxY };
    }
}

/**
 * Grow an element to span the canvas on one or both axes.
 */
export function stretchToCanvas(
    element: DesignDocumentElement,
    document: Pick<DesignDocument, 'width' | 'height'>,
    action: StretchAction,
): { x: number; y: number; width: number; height: number } {
    if (action === 'width') {
        return {
            x: 0,
            y: element.y,
            width: document.width,
            height: element.height,
        };
    }

    if (action === 'height') {
        return {
            x: element.x,
            y: 0,
            width: element.width,
            height: document.height,
        };
    }

    return { x: 0, y: 0, width: document.width, height: document.height };
}

/**
 * Re-stack elements so the target moves one step, or all the way, in z-order.
 *
 * Layers are rewritten as a dense 0..n-1 sequence. That keeps the Layers panel
 * readable and avoids the unbounded zIndex drift repeated "bring to front"
 * clicks would otherwise cause.
 */
export function reorderLayers(
    elements: DesignDocumentElement[],
    id: string,
    action: OrderAction,
): DesignDocumentElement[] {
    const sorted = [...elements].sort(
        (left, right) => left.zIndex - right.zIndex,
    );
    const from = sorted.findIndex((element) => element.id === id);

    if (from === -1) {
        return elements;
    }

    const to =
        action === 'front'
            ? sorted.length - 1
            : action === 'back'
              ? 0
              : clamp(
                    from + (action === 'forward' ? 1 : -1),
                    0,
                    sorted.length - 1,
                );

    if (to === from) {
        return elements;
    }

    const [moved] = sorted.splice(from, 1);
    sorted.splice(to, 0, moved);

    const layers = new Map(
        sorted.map((element, index) => [element.id, index] as const),
    );

    return elements.map((element) => ({
        ...element,
        zIndex: layers.get(element.id) ?? element.zIndex,
    }));
}

/**
 * Whether a layer move would change anything, for disabling toolbar buttons.
 */
export function canReorder(
    elements: DesignDocumentElement[],
    id: string,
    action: OrderAction,
): boolean {
    const sorted = [...elements].sort(
        (left, right) => left.zIndex - right.zIndex,
    );
    const index = sorted.findIndex((element) => element.id === id);

    if (index === -1) {
        return false;
    }

    return action === 'front' || action === 'forward'
        ? index < sorted.length - 1
        : index > 0;
}

/**
 * Space three or more elements evenly between the outermost two.
 *
 * Exported for the layer-wide "distribute" action, which spaces every
 * unlocked, visible element on the chosen axis.
 */
export function distributeEvenly(
    elements: DesignDocumentElement[],
    ids: string[],
    axis: 'horizontal' | 'vertical',
): DesignDocumentElement[] {
    const targets = elements.filter((element) => ids.includes(element.id));

    if (targets.length < 3) {
        return elements;
    }

    const start = axis === 'horizontal' ? 'x' : 'y';
    const size = axis === 'horizontal' ? 'width' : 'height';
    const ordered = [...targets].sort(
        (left, right) => left[start] - right[start],
    );
    const first = ordered[0];
    const last = ordered[ordered.length - 1];
    const span =
        last[start] +
        last[size] -
        first[start] -
        ordered.reduce((total, element) => total + element[size], 0);
    const gap = span / (ordered.length - 1);

    let cursor = first[start];
    const positions = new Map<string, number>();

    ordered.forEach((element) => {
        positions.set(element.id, Math.round(cursor));
        cursor += element[size] + gap;
    });

    return elements.map((element) =>
        positions.has(element.id)
            ? { ...element, [start]: positions.get(element.id) as number }
            : element,
    );
}
