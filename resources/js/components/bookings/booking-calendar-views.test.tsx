import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    addDays,
    addMonths,
    datesBetween,
    MonthView,
    startOfWeek,
    WeekView,
} from '@/components/bookings/booking-calendar-views';
import type { BookingRoomSummary, RoomBookingRecord } from '@/types';

const room = {
    id: 1,
    name: 'Boardroom',
    color: '#2563eb',
    capacity: 12,
    is_active: true,
} as BookingRoomSummary;

function booking(id: number, date: string, title: string): RoomBookingRecord {
    return {
        id,
        room_id: 1,
        room_name: 'Boardroom',
        title,
        date,
        start_time: `0${id}:00`.slice(-5),
        end_time: '10:00',
        status: 'confirmed',
    } as RoomBookingRecord;
}

describe('booking date helpers', () => {
    it('steps days, weeks and months across boundaries', () => {
        expect(addDays('2026-09-30', 1)).toBe('2026-10-01');
        expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28');
        expect(addMonths('2026-03-15', -1)).toBe('2026-02-15');
        expect(startOfWeek('2026-10-04')).toBe('2026-09-28');
        expect(startOfWeek('2026-09-28')).toBe('2026-09-28');
        expect(datesBetween('2026-09-28', '2026-10-04')).toHaveLength(7);
    });
});

describe('week view', () => {
    it('places bookings in their room and day, and books empty cells', () => {
        const onCreate = vi.fn();
        const onSelect = vi.fn();
        const onOpenDay = vi.fn();

        render(
            <WeekView
                date="2026-09-30"
                today="2026-09-28"
                rooms={[room]}
                bookings={[booking(9, '2026-10-02', 'Friday review')]}
                canCreate
                onSelect={onSelect}
                onCreate={onCreate}
                onOpenDay={onOpenDay}
            />,
        );

        const cell = screen.getByTestId('week-cell-1-2026-10-02');
        expect(cell.textContent).toContain('Friday review');

        fireEvent.click(screen.getByText('Friday review'));
        expect(onSelect).toHaveBeenCalledOnce();
        expect(onCreate).not.toHaveBeenCalled();

        fireEvent.click(screen.getByTestId('week-cell-1-2026-10-01'));
        expect(onCreate).toHaveBeenCalledWith(1, '2026-10-01');
    });
});

describe('month view', () => {
    it('lists the first bookings of each day and links to the rest', () => {
        const onOpenDay = vi.fn();
        const list = [1, 2, 3, 4, 5].map((id) =>
            booking(id, '2026-10-15', `Meeting ${id}`),
        );

        render(
            <MonthView
                date="2026-10-15"
                range={{ start: '2026-09-28', end: '2026-11-01' }}
                today="2026-09-28"
                rooms={[room]}
                bookings={list}
                canCreate={false}
                onSelect={vi.fn()}
                onCreate={vi.fn()}
                onOpenDay={onOpenDay}
            />,
        );

        const cell = screen.getByTestId('month-cell-2026-10-15');
        expect(cell.textContent).toContain('Meeting 1');
        expect(cell.textContent).not.toContain('Meeting 4');

        fireEvent.click(screen.getByText('+2 more'));
        expect(onOpenDay).toHaveBeenCalledWith('2026-10-15');
        expect(screen.getAllByTestId(/^month-cell-/)).toHaveLength(35);
    });
});
