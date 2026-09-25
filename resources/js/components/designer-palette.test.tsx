import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DesignerPalette from '@/components/designer-palette';

const elementTypes = [
    { value: 'text', label: 'Text' },
    { value: 'image', label: 'Image' },
    { value: 'clock', label: 'Clock' },
    { value: 'weather_forecast', label: 'Weather Forecast' },
    { value: 'room_status', label: 'Room Status' },
    { value: 'room_board', label: 'Room Availability Board' },
    { value: 'directory', label: 'Wayfinding Directory' },
    { value: 'queue_now_serving', label: 'Queue · Now Serving' },
    { value: 'brand_new_widget', label: 'Brand New Widget' },
];

describe('DesignerPalette', () => {
    it('groups elements, including room booking and queue', () => {
        render(
            <DesignerPalette
                elementTypes={elementTypes}
                onAdd={() => undefined}
            />,
        );

        const rooms = screen.getByRole('region', { name: 'Room booking' });
        expect(within(rooms).getByText('Room Status')).toBeTruthy();
        expect(within(rooms).getByText('Room Availability Board')).toBeTruthy();

        const queue = screen.getByRole('region', { name: 'Queue' });
        expect(within(queue).getByText('Now Serving')).toBeTruthy();

        // Unknown widgets are never hidden.
        const more = screen.getByRole('region', { name: 'More' });
        expect(within(more).getByText('Brand New Widget')).toBeTruthy();
    });

    it('keeps full labels available and lets long ones wrap', () => {
        render(
            <DesignerPalette
                elementTypes={elementTypes}
                onAdd={() => undefined}
            />,
        );

        const button = screen.getByRole('button', {
            name: 'Wayfinding Directory',
        });
        expect(button.getAttribute('title')).toBe('Wayfinding Directory');
        expect(button.className).toContain('break-words');
        expect(button.className).not.toContain('whitespace-nowrap');
    });

    it('filters by name, key and group, and adds the first match on Enter', () => {
        const onAdd = vi.fn();
        render(<DesignerPalette elementTypes={elementTypes} onAdd={onAdd} />);
        const search = screen.getByRole('textbox', {
            name: 'Search widgets and elements',
        });

        fireEvent.change(search, { target: { value: 'room' } });
        expect(screen.getByText('Room Status')).toBeTruthy();
        expect(screen.getByText('Room Availability Board')).toBeTruthy();
        expect(screen.queryByText('Clock')).toBeNull();

        fireEvent.change(search, { target: { value: 'forecast' } });
        expect(screen.getByText('Weather Forecast')).toBeTruthy();
        fireEvent.keyDown(search, { key: 'Enter' });
        expect(onAdd).toHaveBeenCalledWith('weather_forecast');

        fireEvent.change(search, { target: { value: 'nothing-like-this' } });
        expect(screen.getByText(/No widgets match/)).toBeTruthy();

        fireEvent.keyDown(search, { key: 'Escape' });
        expect(screen.getByText('Text')).toBeTruthy();
    });

    it('adds an element on click', () => {
        const onAdd = vi.fn();
        render(<DesignerPalette elementTypes={elementTypes} onAdd={onAdd} />);

        fireEvent.click(screen.getByRole('button', { name: 'Room Status' }));

        expect(onAdd).toHaveBeenCalledWith('room_status');
    });
});
