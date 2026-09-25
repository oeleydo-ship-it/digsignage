import { describe, expect, it } from 'vitest';
import {
    alignToCanvas,
    canReorder,
    distributeEvenly,
    reorderLayers,
    stretchToCanvas,
} from '@/lib/designer-arrange';
import type { DesignDocumentElement } from '@/types';

function element(
    id: string,
    patch: Partial<DesignDocumentElement> = {},
): DesignDocumentElement {
    return {
        id,
        type: 'shape',
        name: id,
        x: 0,
        y: 0,
        width: 100,
        height: 50,
        rotation: 0,
        opacity: 1,
        zIndex: 0,
        locked: false,
        hidden: false,
        props: {},
        ...patch,
    };
}

const canvas = { width: 1920, height: 1080 };

describe('alignToCanvas', () => {
    const target = element('a', { x: 300, y: 200, width: 400, height: 100 });

    it('pins the element to each canvas edge', () => {
        expect(alignToCanvas(target, canvas, 'left').x).toBe(0);
        expect(alignToCanvas(target, canvas, 'right').x).toBe(1520);
        expect(alignToCanvas(target, canvas, 'top').y).toBe(0);
        expect(alignToCanvas(target, canvas, 'bottom').y).toBe(980);
    });

    it('centres on the requested axis only', () => {
        expect(alignToCanvas(target, canvas, 'center')).toEqual({
            x: 760,
            y: 200,
        });
        expect(alignToCanvas(target, canvas, 'middle')).toEqual({
            x: 300,
            y: 490,
        });
    });

    it('does not push oversized elements off the canvas', () => {
        const oversized = element('big', { width: 4000, height: 3000 });

        expect(alignToCanvas(oversized, canvas, 'right').x).toBe(0);
        expect(alignToCanvas(oversized, canvas, 'bottom').y).toBe(0);
    });
});

describe('stretchToCanvas', () => {
    const target = element('a', { x: 300, y: 200, width: 400, height: 100 });

    it('spans one axis without touching the other', () => {
        expect(stretchToCanvas(target, canvas, 'width')).toEqual({
            x: 0,
            y: 200,
            width: 1920,
            height: 100,
        });
        expect(stretchToCanvas(target, canvas, 'height')).toEqual({
            x: 300,
            y: 0,
            width: 400,
            height: 1080,
        });
    });

    it('fills the whole canvas', () => {
        expect(stretchToCanvas(target, canvas, 'canvas')).toEqual({
            x: 0,
            y: 0,
            width: 1920,
            height: 1080,
        });
    });
});

describe('reorderLayers', () => {
    const elements = [
        element('a', { zIndex: 0 }),
        element('b', { zIndex: 5 }),
        element('c', { zIndex: 9 }),
    ];

    const order = (list: DesignDocumentElement[]) =>
        [...list].sort((l, r) => l.zIndex - r.zIndex).map((item) => item.id);

    it('moves an element to the front and to the back', () => {
        expect(order(reorderLayers(elements, 'a', 'front'))).toEqual([
            'b',
            'c',
            'a',
        ]);
        expect(order(reorderLayers(elements, 'c', 'back'))).toEqual([
            'c',
            'a',
            'b',
        ]);
    });

    it('moves one step at a time', () => {
        expect(order(reorderLayers(elements, 'a', 'forward'))).toEqual([
            'b',
            'a',
            'c',
        ]);
        expect(order(reorderLayers(elements, 'c', 'backward'))).toEqual([
            'a',
            'c',
            'b',
        ]);
    });

    it('rewrites layers as a dense sequence so repeated moves cannot drift', () => {
        const moved = reorderLayers(elements, 'a', 'front');

        expect(
            [...moved].map((item) => item.zIndex).sort((l, r) => l - r),
        ).toEqual([0, 1, 2]);
    });

    it('returns the input untouched when the move is a no-op', () => {
        expect(reorderLayers(elements, 'c', 'front')).toBe(elements);
        expect(reorderLayers(elements, 'missing', 'front')).toBe(elements);
    });
});

describe('canReorder', () => {
    const elements = [element('a', { zIndex: 0 }), element('b', { zIndex: 1 })];

    it('reports which directions are available', () => {
        expect(canReorder(elements, 'a', 'forward')).toBe(true);
        expect(canReorder(elements, 'a', 'backward')).toBe(false);
        expect(canReorder(elements, 'b', 'front')).toBe(false);
        expect(canReorder(elements, 'b', 'back')).toBe(true);
    });

    it('reports false for an unknown element', () => {
        expect(canReorder(elements, 'missing', 'front')).toBe(false);
    });
});

describe('distributeEvenly', () => {
    it('spaces elements evenly between the outermost two', () => {
        const elements = [
            element('a', { x: 0, width: 100 }),
            element('b', { x: 150, width: 100 }),
            element('c', { x: 700, width: 100 }),
        ];

        const spaced = distributeEvenly(
            elements,
            ['a', 'b', 'c'],
            'horizontal',
        );

        expect(spaced.map((item) => item.x)).toEqual([0, 350, 700]);
    });

    it('leaves fewer than three elements alone', () => {
        const elements = [element('a'), element('b', { x: 400 })];

        expect(distributeEvenly(elements, ['a', 'b'], 'horizontal')).toBe(
            elements,
        );
    });
});
