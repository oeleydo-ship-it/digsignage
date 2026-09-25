import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import WidgetSurface from '@/components/widget-surface';
import type { WidgetPayload } from '@/types';

const bookings = [
    {
        id: 1,
        title: 'Leadership sync',
        organizer: 'Priya Shah',
        starts_at: '2026-09-28T09:00:00Z',
        ends_at: '2026-09-28T10:00:00Z',
        start_label: '09:00',
        end_label: '10:00',
    },
    {
        id: 2,
        title: 'Client workshop',
        organizer: null,
        starts_at: '2026-09-28T13:00:00Z',
        ends_at: '2026-09-28T14:00:00Z',
        start_label: '13:00',
        end_label: '14:00',
    },
];

function roomStatus(extra: Record<string, unknown> = {}): WidgetPayload {
    return {
        key: 'room_status',
        settings: {
            room_id: '1',
            show_qr: true,
            upcoming: 3,
            soon_minutes: 10,
        },
        data: {
            room: {
                id: 1,
                name: 'Boardroom',
                capacity: 12,
                location: 'HQ',
                color: '#2563eb',
            },
            bookings,
            booking_url: 'https://signage.test/book/abc',
            ...extra,
        },
    } as WidgetPayload;
}

describe('room status widget', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the meeting in progress and what is next', () => {
        vi.setSystemTime(new Date('2026-09-28T09:15:00Z'));
        render(<WidgetSurface widget={roomStatus()} />);

        expect(screen.getByText('Boardroom')).toBeTruthy();
        expect(screen.getByText('In use')).toBeTruthy();
        expect(screen.getByText('Leadership sync')).toBeTruthy();
        expect(screen.getByText(/45 min left/)).toBeTruthy();
        expect(screen.getByText('Client workshop')).toBeTruthy();
        expect(
            screen.getByAltText('Scan to book this room').getAttribute('src'),
        ).toContain(encodeURIComponent('https://signage.test/book/abc'));
    });

    it('shows when the room is free until', () => {
        vi.setSystemTime(new Date('2026-09-28T11:00:00Z'));
        render(<WidgetSurface widget={roomStatus()} />);

        expect(screen.getByText('Available')).toBeTruthy();
        expect(screen.getByText('Free until 13:00')).toBeTruthy();
    });

    it('shows a labelled sample until a room is chosen', () => {
        vi.setSystemTime(new Date('2026-09-28T11:00:00Z'));
        render(
            <WidgetSurface
                widget={
                    {
                        key: 'room_status',
                        settings: { room_id: '0' },
                        data: { error: 'Choose a room for this widget.' },
                    } as WidgetPayload
                }
            />,
        );

        expect(screen.getByRole('status').textContent).toContain(
            'Choose a room for this widget.',
        );
        expect(screen.getByText('Meeting room')).toBeTruthy();
        expect(screen.getByText('In use')).toBeTruthy();
        expect(screen.queryByText('room_status')).toBeNull();
    });

    it('drops the setup banner in gallery previews', () => {
        vi.setSystemTime(new Date('2026-09-28T11:00:00Z'));
        render(
            <WidgetSurface
                staticPreview
                widget={
                    {
                        key: 'room_status',
                        settings: { room_id: '0' },
                        data: { room_id: '0' },
                    } as WidgetPayload
                }
            />,
        );

        expect(screen.queryByRole('status')).toBeNull();
        expect(screen.getByText('Meeting room')).toBeTruthy();
    });

    it('hides the QR code when public booking is off', () => {
        vi.setSystemTime(new Date('2026-09-28T11:00:00Z'));
        render(<WidgetSurface widget={roomStatus({ booking_url: null })} />);

        expect(screen.queryByAltText('Scan to book this room')).toBeNull();
    });
});

describe('room availability board samples', () => {
    it('shows sample rooms until the board is resolved, and real emptiness after', () => {
        const board = (data: Record<string, unknown>) =>
            ({ key: 'room_board', settings: {}, data }) as WidgetPayload;

        const unresolved = render(
            <WidgetSurface widget={board({ limit: 8 })} />,
        );
        expect(unresolved.container.textContent).toContain('Boardroom');
        unresolved.unmount();

        render(<WidgetSurface widget={board({ rooms: [] })} />);
        expect(screen.getByText('No rooms to show yet.')).toBeTruthy();
    });
});

describe('room availability board', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('lists each room with its live state', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-09-28T09:15:00Z'));

        render(
            <WidgetSurface
                widget={
                    {
                        key: 'room_board',
                        settings: { heading: 'Meeting rooms' },
                        data: {
                            rooms: [
                                {
                                    room: { id: 1, name: 'Boardroom' },
                                    bookings,
                                },
                                {
                                    room: { id: 2, name: 'Focus room' },
                                    bookings: [],
                                },
                            ],
                        },
                    } as WidgetPayload
                }
            />,
        );

        expect(screen.getByText('Leadership sync · until 10:00')).toBeTruthy();
        expect(screen.getByText('Free all day')).toBeTruthy();
        expect(screen.getAllByText('In use')).toHaveLength(1);
        expect(screen.getAllByText('Available')).toHaveLength(1);
    });
});
