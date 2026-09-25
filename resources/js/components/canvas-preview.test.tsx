import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import CanvasPreview from '@/components/canvas-preview';
import type { DesignDocument } from '@/types';

const document: DesignDocument = {
    width: 1920,
    height: 1080,
    background: '#000000',
    elements: [
        {
            id: 'headline',
            type: 'text',
            name: 'Headline',
            x: 100,
            y: 200,
            width: 800,
            height: 160,
            rotation: 0,
            opacity: 1,
            zIndex: 1,
            locked: false,
            hidden: false,
            props: { text: 'Please sign in', fontSize: 96, color: '#ffffff' },
        },
        {
            id: 'site',
            type: 'web_page',
            name: 'Web page',
            x: 1000,
            y: 200,
            width: 800,
            height: 600,
            rotation: 0,
            opacity: 1,
            zIndex: 2,
            locked: false,
            hidden: false,
            props: { url: 'https://example.com' },
        },
    ],
};

describe('CanvasPreview', () => {
    it('renders at native resolution inside one uniform scale transform', () => {
        const { container } = render(
            <CanvasPreview document={document} maxWidth={480} />,
        );

        const stage = container.querySelector<HTMLElement>(
            '[style*="transform: scale"]',
        );

        expect(stage).not.toBeNull();
        expect(stage?.style.width).toBe('1920px');
        expect(stage?.style.height).toBe('1080px');
        expect(stage?.style.transform).toBe('scale(0.25)');

        // Elements keep their authored coordinates and font sizes, so text is
        // shrunk with the canvas instead of overflowing a miniature box.
        const headline = [...container.querySelectorAll<HTMLElement>('div')].find(
            (node) => node.style.left === '100px' && node.style.top === '200px',
        );

        expect(headline?.style.width).toBe('800px');
        expect(container.textContent).toContain('Please sign in');
        expect(
            [...container.querySelectorAll<HTMLElement>('div')].some(
                (node) => node.style.fontSize === '96px',
            ),
        ).toBe(true);
    });

    it('swaps live iframes for a poster in static gallery previews', () => {
        const live = render(<CanvasPreview document={document} />);
        expect(live.container.querySelector('iframe')).not.toBeNull();
        live.unmount();

        const gallery = render(
            <CanvasPreview document={document} staticPreview />,
        );
        expect(gallery.container.querySelector('iframe')).toBeNull();
        expect(gallery.container.textContent).toContain('example.com');
    });
});
