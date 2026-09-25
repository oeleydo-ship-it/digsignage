import type { DesignDocument, DesignDocumentElement } from '@/types';
import { nextDesignLayer } from '@/lib/design-layers';

const STORAGE_KEY = 'digsignage.designer.clipboard';

let memory: DesignDocumentElement | null = null;

/**
 * Remember an element for pasting. Kept in memory and, when available,
 * localStorage, so an element copied in a design can be pasted into a
 * template in another tab.
 */
export function copyElement(element: DesignDocumentElement): void {
    const { widget: _widget, ...portable } = element;
    memory = JSON.parse(JSON.stringify(portable)) as DesignDocumentElement;

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(memory));
    } catch {
        // Private browsing can block storage; the in-memory copy still works.
    }
}

export function readClipboard(): DesignDocumentElement | null {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored) {
            const parsed = JSON.parse(stored) as DesignDocumentElement;

            if (parsed && typeof parsed.type === 'string') {
                return parsed;
            }
        }
    } catch {
        // Fall back to the in-memory copy.
    }

    return memory;
}

/**
 * Clone an element for insertion: fresh id, top layer, unlocked, nudged so it
 * is visibly separate from the original, and kept inside the canvas.
 */
export function cloneForCanvas(
    element: DesignDocumentElement,
    document: DesignDocument,
    offset = 20,
): DesignDocumentElement {
    const width = Math.min(element.width, document.width);
    const height = Math.min(element.height, document.height);
    const clamp = (value: number, max: number) =>
        Math.min(Math.max(value, 0), Math.max(0, max));

    return {
        ...JSON.parse(JSON.stringify(element)),
        id: crypto.randomUUID(),
        name: element.name.endsWith(' copy')
            ? element.name
            : `${element.name} copy`,
        width,
        height,
        x: clamp(element.x + offset, document.width - width),
        y: clamp(element.y + offset, document.height - height),
        zIndex: nextDesignLayer(document.elements),
        locked: false,
        hidden: false,
    };
}
