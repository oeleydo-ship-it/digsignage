import type { ReactNode } from 'react';
import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { DesignDocumentElement } from '@/types';

vi.mock('react-konva', () => ({
    Stage: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Layer: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Group: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Rect: () => null,
    Transformer: () => null,
}));

import KonvaDesigner from './konva-designer';

function element(id: string, type: string, zIndex: number): DesignDocumentElement {
    return {
        id, type, name: id, x: 0, y: 0, width: 500, height: 300,
        rotation: 0, opacity: 1, zIndex, locked: false, hidden: false,
        props: type === 'image' ? { src: '/example.jpg' } : {},
    };
}

describe('designer visual layering', () => {
    it('renders a newly added image above an older queue widget', () => {
        const { container } = render(<KonvaDesigner
            document={{ width: 1000, height: 600, background: '#000000', elements: [
                element('image', 'image', 2),
                element('queue', 'queue_now_serving', 1),
            ] }}
            zoom={1}
            grid={20}
            selectedId={null}
            editable
            onSelect={() => undefined}
            onChange={() => undefined}
        />);
        const layers = [...container.querySelectorAll('[data-design-layer]')] as HTMLElement[];

        expect(layers.map((layer) => layer.dataset.designLayer)).toEqual(['queue', 'image']);
        expect(layers[0].style.zIndex).toBe('1');
        expect(layers[1].style.zIndex).toBe('2');
        expect(layers[1].querySelector('img')).not.toBeNull();
    });
});
