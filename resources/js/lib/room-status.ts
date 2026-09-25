export type ScreenBooking = {
    id: number;
    title: string;
    organizer: string | null;
    starts_at: string;
    ends_at: string;
    start_label: string;
    end_label: string;
};

export type RoomState =
    | {
          kind: 'busy';
          current: ScreenBooking;
          next: ScreenBooking | null;
          progress: number;
          minutesLeft: number;
      }
    | { kind: 'soon'; next: ScreenBooking; minutesUntil: number }
    | { kind: 'free'; next: ScreenBooking | null; minutesUntil: number | null };

/**
 * Work out a room's live state from today's bookings.
 *
 * Runs on the player every second, so it only needs the list the server
 * resolved into the manifest: no network round trip to flip "In use" on.
 */
export function roomState(
    bookings: ScreenBooking[],
    now: Date,
    soonMinutes = 10,
): RoomState {
    const time = now.getTime();
    const sorted = [...bookings].sort(
        (left, right) =>
            Date.parse(left.starts_at) - Date.parse(right.starts_at),
    );
    const current = sorted.find(
        (booking) =>
            Date.parse(booking.starts_at) <= time &&
            time < Date.parse(booking.ends_at),
    );
    const next =
        sorted.find(
            (booking) =>
                Date.parse(booking.starts_at) > time &&
                booking.id !== current?.id,
        ) ?? null;

    if (current) {
        const start = Date.parse(current.starts_at);
        const end = Date.parse(current.ends_at);

        return {
            kind: 'busy',
            current,
            next,
            progress: Math.min(
                1,
                Math.max(0, (time - start) / Math.max(1, end - start)),
            ),
            minutesLeft: Math.max(0, Math.ceil((end - time) / 60000)),
        };
    }

    const minutesUntil = next
        ? Math.max(0, Math.ceil((Date.parse(next.starts_at) - time) / 60000))
        : null;

    if (
        next &&
        minutesUntil !== null &&
        minutesUntil <= Math.max(0, soonMinutes)
    ) {
        return { kind: 'soon', next, minutesUntil };
    }

    return { kind: 'free', next, minutesUntil };
}

/**
 * Bookings still to come after the current one, for the "Up next" list.
 */
export function upcomingBookings(
    bookings: ScreenBooking[],
    now: Date,
    limit: number,
): ScreenBooking[] {
    const time = now.getTime();

    return [...bookings]
        .filter((booking) => Date.parse(booking.starts_at) > time)
        .sort(
            (left, right) =>
                Date.parse(left.starts_at) - Date.parse(right.starts_at),
        )
        .slice(0, Math.max(0, limit));
}

export function formatMinutes(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}
