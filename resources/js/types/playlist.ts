export type PlaylistPermissions = {
    canViewPlaylists: boolean;
    canCreatePlaylist: boolean;
    canUpdatePlaylist: boolean;
    canDeletePlaylist: boolean;
};

export type PlaylistItemRecord = {
    id?: number;
    type: string;
    type_label?: string;
    title: string;
    duration_seconds: number;
    transition: string;
    transition_ms: number;
    enabled: boolean;
    available_from: string | null;
    available_until: string | null;
    position?: number;
    media_id: number | null;
    design_id: number | null;
    template_id: number | null;
    url: string | null;
    widget_key: string | null;
    widget_settings?: Record<string, string | number | boolean | null>;
    preview_url?: string | null;
    media_type?: string | null;
};

export type PlaylistRecord = {
    id: number;
    name: string;
    description?: string | null;
    status: string;
    status_label: string;
    loop: boolean;
    version: number;
    duration_seconds: number;
    duration_label: string;
    items_count: number;
    updated_at: string | null;
    items?: PlaylistItemRecord[];
};
