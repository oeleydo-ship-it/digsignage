import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { DesignDocumentElement } from '@/types';
import { DesignElementContent } from './design-element';

function image(src: string): DesignDocumentElement {
    return {
        id: 'catalog-photo', type: 'image', name: 'Catalog photo',
        x: 0, y: 0, width: 800, height: 450, rotation: 0, opacity: 1,
        zIndex: 1, locked: false, hidden: false, props: { src },
    };
}

describe('catalog image fallback', () => {
    it('positions a zoomed image across the full crop range', () => {
        render(<DesignElementContent element={{
            ...image('/storage/team-image.jpg'),
            props: { src: '/storage/team-image.jpg', imageZoom: 2, imageX: 0, imageY: 100 },
        }} />);
        const photo = screen.getByRole('img', { name: 'Catalog photo' }) as HTMLImageElement;

        expect(photo.style.width).toBe('200%');
        expect(photo.style.height).toBe('200%');
        expect(photo.style.left).toBe('0%');
        expect(photo.style.top).toBe('-100%');
    });

    it('uses the bundled image when a catalog photo is missing', () => {
        render(<DesignElementContent element={image('/images/catalog/lobby-welcome.jpg')} />);
        const photo = screen.getByRole('img', { name: 'Catalog photo' }) as HTMLImageElement;

        fireEvent.error(photo);

        expect(photo.src).toContain('/images/catalog-fallback.svg');
    });

    it('does not replace a missing team image with a catalog fallback', () => {
        render(<DesignElementContent element={image('/storage/team-image.jpg')} />);
        const photo = screen.getByRole('img', { name: 'Catalog photo' }) as HTMLImageElement;

        fireEvent.error(photo);

        expect(photo.src).toContain('/storage/team-image.jpg');
    });
});
