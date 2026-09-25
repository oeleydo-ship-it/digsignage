export type GuideBox = { x: number; y: number; width: number; height: number };

export type GuideSnap = {
    x: number;
    y: number;
    /** Canvas x positions of vertical guide lines to draw. */
    vertical: number[];
    /** Canvas y positions of horizontal guide lines to draw. */
    horizontal: number[];
};

/**
 * Snap a moving box to the edges and centre lines of other elements and of
 * the canvas, returning the adjusted position and the guides to draw.
 *
 * Each axis snaps independently to its single closest target inside the
 * threshold, so a box can align left edges with one element while centring
 * vertically on the canvas.
 */
export function snapToGuides(
    box: GuideBox,
    others: GuideBox[],
    canvas: { width: number; height: number },
    threshold: number,
): GuideSnap {
    const verticalTargets = [0, canvas.width / 2, canvas.width];
    const horizontalTargets = [0, canvas.height / 2, canvas.height];

    for (const other of others) {
        verticalTargets.push(
            other.x,
            other.x + other.width / 2,
            other.x + other.width,
        );
        horizontalTargets.push(
            other.y,
            other.y + other.height / 2,
            other.y + other.height,
        );
    }

    const xAxis = closest(
        [box.x, box.x + box.width / 2, box.x + box.width],
        verticalTargets,
        threshold,
    );
    const yAxis = closest(
        [box.y, box.y + box.height / 2, box.y + box.height],
        horizontalTargets,
        threshold,
    );
    const x = box.x + (xAxis?.delta ?? 0);
    const y = box.y + (yAxis?.delta ?? 0);

    return {
        x,
        y,
        vertical: xAxis
            ? matchingLines(
                  [x, x + box.width / 2, x + box.width],
                  verticalTargets,
              )
            : [],
        horizontal: yAxis
            ? matchingLines(
                  [y, y + box.height / 2, y + box.height],
                  horizontalTargets,
              )
            : [],
    };
}

function closest(
    edges: number[],
    targets: number[],
    threshold: number,
): { delta: number } | null {
    let best: { delta: number } | null = null;

    for (const edge of edges) {
        for (const target of targets) {
            const delta = target - edge;

            if (
                Math.abs(delta) <= threshold &&
                (best === null || Math.abs(delta) < Math.abs(best.delta))
            ) {
                best = { delta };
            }
        }
    }

    return best;
}

/**
 * Every guide the snapped box now sits exactly on, de-duplicated, so aligning
 * with two elements at once draws both lines.
 */
function matchingLines(edges: number[], targets: number[]): number[] {
    const lines = new Set<number>();

    for (const edge of edges) {
        for (const target of targets) {
            if (Math.abs(target - edge) < 0.5) {
                lines.add(Math.round(target * 10) / 10);
            }
        }
    }

    return [...lines];
}
