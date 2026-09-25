import { describe, expect, it } from 'vitest';
import { snapToGuides } from '@/lib/designer-guides';

const canvas = { width: 1920, height: 1080 };

describe('snapToGuides', () => {
    it('leaves a box alone when nothing is within the threshold', () => {
        const snap = snapToGuides(
            { x: 400, y: 300, width: 200, height: 100 },
            [],
            canvas,
            8,
        );

        expect(snap).toEqual({ x: 400, y: 300, vertical: [], horizontal: [] });
    });

    it('centres on the canvas when close to the centre lines', () => {
        const snap = snapToGuides(
            { x: 855, y: 487, width: 200, height: 100 },
            [],
            canvas,
            8,
        );

        expect(snap.x).toBe(860);
        expect(snap.y).toBe(490);
        expect(snap.vertical).toContain(960);
        expect(snap.horizontal).toContain(540);
    });

    it("aligns a box's left edge with another element's left edge", () => {
        const snap = snapToGuides(
            { x: 104, y: 600, width: 300, height: 80 },
            [{ x: 100, y: 100, width: 500, height: 200 }],
            canvas,
            8,
        );

        expect(snap.x).toBe(100);
        expect(snap.vertical).toContain(100);
        expect(snap.horizontal).toEqual([]);
    });

    it('snaps each axis to its closest target independently', () => {
        const snap = snapToGuides(
            { x: 590, y: 303, width: 100, height: 100 },
            [{ x: 100, y: 100, width: 500, height: 200 }],
            canvas,
            12,
        );

        // Left edge 590 is 10 from the other's right edge at 600;
        // top edge 303 is 3 from the other's bottom edge at 300.
        expect(snap.x).toBe(600);
        expect(snap.y).toBe(300);
    });

    it('prefers the nearest of several candidate targets', () => {
        const snap = snapToGuides(
            { x: 205, y: 700, width: 100, height: 50 },
            [
                { x: 200, y: 0, width: 50, height: 50 },
                { x: 208, y: 400, width: 50, height: 50 },
            ],
            canvas,
            8,
        );

        expect(snap.x).toBe(208);
    });
});
