import type { DesignDocumentElement } from '@/types';

export function resizeElement(
    element: DesignDocumentElement,
    direction: string,
    dx: number,
    dy: number,
    grid: number,
    canvasWidth: number,
    canvasHeight: number,
) {
    const snap = (value: number) =>
        Math.round(value / Math.max(1, grid)) * Math.max(1, grid);
    const clamp = (value: number, min: number, max: number) =>
        Math.max(min, Math.min(max, value));
    const right = element.x + element.width;
    const bottom = element.y + element.height;
    const x = direction.includes('w')
        ? clamp(snap(element.x + dx), 0, right - 20)
        : element.x;
    const y = direction.includes('n')
        ? clamp(snap(element.y + dy), 0, bottom - 20)
        : element.y;
    return {
        x,
        y,
        width: direction.includes('w')
            ? right - x
            : clamp(snap(element.width + dx), 20, canvasWidth - x),
        height: direction.includes('n')
            ? bottom - y
            : clamp(snap(element.height + dy), 20, canvasHeight - y),
    };
}
