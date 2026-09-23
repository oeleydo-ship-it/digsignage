export type SchedulePermissions = {
    canViewSchedules: boolean;
    canCreateSchedule: boolean;
    canUpdateSchedule: boolean;
    canDeleteSchedule: boolean;
};

export type ScheduleTargetRecord = {
    id?: number;
    target_type: 'screen' | 'screen_group' | 'location';
    target_type_label?: string;
    screen_id: number | null;
    screen_group_id: number | null;
    location_id: number | null;
    label?: string;
};

export type ScheduleRecord = {
    id: number;
    name: string;
    description: string | null;
    content_type: 'channel' | 'playlist';
    content_type_label: string;
    channel_id: number | null;
    channel_name: string | null;
    playlist_id: number | null;
    playlist_name: string | null;
    timezone: string;
    starts_on: string;
    ends_on: string | null;
    start_time: string;
    end_time: string;
    recurrence: 'once' | 'daily' | 'weekly';
    recurrence_label: string;
    weekdays: number[];
    priority: number;
    is_enabled: boolean;
    targets: ScheduleTargetRecord[];
};

export type ScheduleOccurrence = {
    schedule_id: number;
    name: string;
    date: string;
    start_minutes: number;
    end_minutes: number;
    priority: number;
    content_type: string;
    channel_name: string | null;
    playlist_name: string | null;
    timezone: string;
    is_enabled: boolean;
};

export type ResolvedPlayback = {
    source: 'schedule' | 'fallback';
    schedule_id: number | null;
    channel_id: number | null;
    playlist_id: number | null;
    schedule_name: string | null;
    timezone: string;
};
