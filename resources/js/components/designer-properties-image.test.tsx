import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { DesignDocumentElement } from '@/types';
import DesignerProperties from './designer-properties';

const image: DesignDocumentElement = {
    id: 'image-1',
    type: 'image',
    name: 'Portrait',
    x: 0,
    y: 0,
    width: 600,
    height: 400,
    rotation: 0,
    opacity: 1,
    zIndex: 1,
    locked: false,
    hidden: false,
    props: { objectFit: 'cover', imageZoom: 2, imageX: 25, imageY: 75 },
};

describe('designer image framing', () => {
    it('shows the full image and resets the previous crop adjustments', () => {
        const onChange = vi.fn();
        render(<DesignerProperties element={image} disabled={false} onChange={onChange} />);

        fireEvent.click(screen.getByRole('button', { name: 'Show full image' }));

        expect(onChange).toHaveBeenCalledWith({
            ...image.props,
            objectFit: 'contain',
            imageZoom: 1,
            imageX: 50,
            imageY: 50,
        });
    });

    it('fills the block and resets the previous crop adjustments', () => {
        const onChange = vi.fn();
        render(<DesignerProperties element={{ ...image, props: { ...image.props, objectFit: 'contain' } }} disabled={false} onChange={onChange} />);

        fireEvent.click(screen.getByRole('button', { name: 'Fill block' }));

        expect(onChange).toHaveBeenCalledWith({
            ...image.props,
            objectFit: 'cover',
            imageZoom: 1,
            imageX: 50,
            imageY: 50,
        });
    });
});
