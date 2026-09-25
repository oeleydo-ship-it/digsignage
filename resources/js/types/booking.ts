export type BookingPermissions = {
    canViewBookings: boolean;
    canCreateBooking: boolean;
    canManageBookings: boolean;
    canManageRooms: boolean;
};

export type BookingRoomSummary = {
    id: number;
    name: string;
    color: string;
    capacity: number | null;
    location: string | null;
    timezone: string;
    opens_at: string;
    closes_at: string;
    is_active: boolean;
    microsoft: boolean;
    booking_url: string | null;
};

export type RoomBookingRecord = {
    id: number;
    room_id: number;
    room_name: string;
    title: string;
    organizer_name: string | null;
    organizer_email: string | null;
    attendees: number | null;
    notes: string | null;
    status: 'pending' | 'confirmed' | 'declined' | 'cancelled';
    status_label: string;
    source: 'manual' | 'public_form' | 'microsoft365';
    source_label: string;
    date: string;
    start_time: string;
    end_time: string;
    starts_at: string;
    ends_at: string;
    microsoft: boolean;
    sync_error: string | null;
    can_edit: boolean;
    can_approve: boolean;
};

export type MeetingRoomRecord = {
    id: number;
    name: string;
    description: string | null;
    location_id: number | null;
    location: string | null;
    capacity: number | null;
    amenities: string[];
    color: string;
    is_active: boolean;
    public_booking_enabled: boolean;
    requires_approval: boolean;
    min_duration_minutes: number;
    max_duration_minutes: number;
    opens_at: string;
    closes_at: string;
    timezone: string;
    external_calendar_id: string | null;
    microsoft: boolean;
    booking_url: string | null;
    upcoming_count: number;
};
