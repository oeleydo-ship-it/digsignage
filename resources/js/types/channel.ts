export type ChannelPermissions = {
    canViewChannels: boolean;
    canCreateChannel: boolean;
    canUpdateChannel: boolean;
    canDeleteChannel: boolean;
};

export type ChannelZoneRecord = {
    id?: number;
    name: string;
    playlist_id: number | null;
    playlist_name?: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
    z_index: number;
    position?: number;
};

export type ChannelRecord = {
    id: number;
    name: string;
    description?: string | null;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    playlist_id?: number | null;
    playlist_name?: string | null;
    live_protocol?: string | null;
    live_protocol_label?: string | null;
    live_url?: string | null;
    width?: number;
    height?: number;
    version: number;
    scheduled_at?: string | null;
    zones_count: number;
    screens_count: number;
    screen_ids?: number[];
    zones?: ChannelZoneRecord[];
    updated_at: string | null;
};
