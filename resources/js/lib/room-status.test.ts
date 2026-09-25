import { describe, expect, it } from 'vitest';
import {
    formatMinutes,
    roomState,
    upcomingBookings,
    type ScreenBooking,
} from '@/lib/room-status';

function booking(id: number, start: string, end: string): ScreenBooking {
    return {
        id,
        title: `Meeting ${id}`,
        organizer: null,
        starts_at: `2026-09-25T${start}:00Z`,
        ends_at: `2026-09-25T${end}:00Z`,
        start_label: start,
        end_label: end,
    };
}

const at = (time: string) => new Date(`2026-09-25T${time}:00Z`);

const day = [
    booking(1, '09:00', '10:00'),
    booking(2, '10:00', '11:00'),
    booking(3, '14:00', '15:30'),
];

describe('roomState', () => {
    it('is free with nothing booked', () => {
        expect(roomState([], at('09:00'))).toEqual({
            kind: 'free',
            next: null,
            minutesUntil: null,
        });
    });

    it('is busy during a meeting and reports progress', () => {
        const state = roomState(day, at('09:15'));

        expect(state.kind).toBe('busy');

        if (state.kind === 'busy') {
            expect(state.current.id).toBe(1);
            expect(state.progress).toBeCloseTo(0.25);
            expect(state.minutesLeft).toBe(45);
            expect(state.next?.id).toBe(2);
        }
    });

    it('hands over exactly at the boundary of back-to-back meetings', () => {
        const state = roomState(day, at('10:00'));

        expect(state.kind).toBe('busy');

        if (state.kind === 'busy') {
            expect(state.current.id).toBe(2);
        }
    });

    it('warns when the next meeting starts soon', () => {
        const state = roomState(day, at('13:52'), 10);

        expect(state).toMatchObject({ kind: 'soon', minutesUntil: 8 });
    });

    it('is free until the next meeting when it is further away', () => {
        const state = roomState(day, at('11:30'), 10);

        expect(state).toMatchObject({ kind: 'free', minutesUntil: 150 });
        expect(state.kind === 'free' && state.next?.id).toBe(3);
    });

    it('is free for the rest of the day after the last meeting', () => {
        expect(roomState(day, at('16:00'))).toMatchObject({
            kind: 'free',
            next: null,
        });
    });
});

describe('upcomingBookings', () => {
    it('lists future meetings in order, excluding the current one', () => {
        expect(
            upcomingBookings(day, at('09:30'), 5).map((item) => item.id),
        ).toEqual([2, 3]);
        expect(
            upcomingBookings(day, at('09:30'), 1).map((item) => item.id),
        ).toEqual([2]);
    });
});

describe('formatMinutes', () => {
    it('formats short and long durations', () => {
        expect(formatMinutes(45)).toBe('45 min');
        expect(formatMinutes(60)).toBe('1 h');
        expect(formatMinutes(95)).toBe('1 h 35 min');
    });
});
