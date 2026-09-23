export type SignagePermissions = {
    canViewLocations: boolean;
    canCreateLocation: boolean;
    canUpdateLocation: boolean;
    canDeleteLocation: boolean;
    canViewScreens: boolean;
    canCreateScreen: boolean;
    canUpdateScreen: boolean;
    canDeleteScreen: boolean;
    canPairScreen: boolean;
    canViewScreenGroups: boolean;
    canCreateScreenGroup: boolean;
    canUpdateScreenGroup: boolean;
    canDeleteScreenGroup: boolean;
};

export type LocationTypeValue =
    | 'country'
    | 'city'
    | 'building'
    | 'floor'
    | 'area';

export type SignageLocation = {
    id: number;
    parent_id: number | null;
    type: LocationTypeValue;
    type_label: string;
    name: string;
    description: string | null;
    address: string | null;
    timezone: string | null;
    path: string;
    depth: number;
    tags: string[];
    children_count: number;
    screens_count: number;
};

export type ScreenRecord = {
    id: number;
    name: string;
    description: string | null;
    location_id: number | null;
    location_name: string | null;
    orientation: 'landscape' | 'portrait';
    resolution_width: number | null;
    resolution_height: number | null;
    timezone: string | null;
    status: 'online' | 'offline' | 'warning' | 'updating' | 'disabled';
    status_label: string;
    paired: boolean;
    device_uuid: string | null;
    last_seen_at: string | null;
    app_version: string | null;
    current_channel: string | null;
    current_content: string | null;
    storage_available: number | null;
    storage_total: number | null;
    last_error: string | null;
    fallback_image_url: string | null;
    groups: { id: number; name: string }[];
};

export type ScreenGroupRecord = {
    id: number;
    name: string;
    description: string | null;
    screens_count: number;
    screen_ids: number[];
    screens: { id: number; name: string; status: string }[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
