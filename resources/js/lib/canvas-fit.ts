export type CanvasFitMode = 'contain' | 'cover';

/** Live player canvases always contain so ticker, clock, and headlines stay fully visible. */
export const PLAYER_DESIGN_FIT: CanvasFitMode = 'contain';

export function fitCanvasScale(
    viewportWidth: number,
    viewportHeight: number,
    documentWidth: number,
    documentHeight: number,
    mode: CanvasFitMode = PLAYER_DESIGN_FIT,
): number {
    const artboardWidth = Math.max(1, documentWidth);
    const artboardHeight = Math.max(1, documentHeight);
    const width = Math.max(0, viewportWidth);
    const height = Math.max(0, viewportHeight);

    if (width <= 0 || height <= 0) {
        return 1;
    }

    const ratio = (mode === 'cover' ? Math.max : Math.min)(
        width / artboardWidth,
        height / artboardHeight,
    );

    return Number.isFinite(ratio) && ratio > 0 ? ratio : 1;
}
