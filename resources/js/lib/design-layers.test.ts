import { describe, expect, it } from 'vitest';
import type { DesignDocumentElement } from '@/types';
import { nextDesignLayer, visibleDesignLayers } from './design-layers';

function element(id: string, zIndex: number, hidden = false): DesignDocumentElement {
    return {
        id, type: id === 'new-image' ? 'image' : 'queue_now_serving', name: id,
        x: 0, y: 0, width: 100, height: 100, rotation: 0, opacity: 1,
        zIndex, locked: false, hidden, props: {},
    };
}

describe('designer layers', () => {
    it('places a newly added image above existing widgets', () => {
        const existing = [element('serving', 3), element('ticker', 8)];
        const image = element('new-image', nextDesignLayer(existing));

        expect(image.zIndex).toBe(9);
        expect(visibleDesignLayers([...existing, image]).map((item) => item.id))
            .toEqual(['serving', 'ticker', 'new-image']);
    });

    it('excludes hidden layers without changing the source array', () => {
        const elements = [element('hidden', 10, true), element('serving', 1)];

        expect(visibleDesignLayers(elements).map((item) => item.id)).toEqual(['serving']);
        expect(elements[0].id).toBe('hidden');
    });
});
