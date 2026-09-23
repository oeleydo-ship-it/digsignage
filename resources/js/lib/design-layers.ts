import type { DesignDocumentElement } from '@/types';

export function nextDesignLayer(elements: DesignDocumentElement[]): number {
    return Math.max(0, ...elements.map((element) => element.zIndex)) + 1;
}

export function visibleDesignLayers(elements: DesignDocumentElement[]): DesignDocumentElement[] {
    return [...elements]
        .filter((element) => !element.hidden)
        .sort((left, right) => left.zIndex - right.zIndex);
}
