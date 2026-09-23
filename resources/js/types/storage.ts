export type StorageProviderOption = {
    value: string;
    label: string;
    driver: 'local' | 's3';
    requires_endpoint: boolean;
    default_path_style: boolean;
    endpoint_template: string | null;
    region_hint: string;
};

export type StorageDisk = {
    id: number;
    name: string;
    disk_name: string;
    provider: string;
    provider_label: string;
    driver: 'local' | 's3';
    team_id: number | null;
    team_name: string | null;
    bucket: string | null;
    region: string | null;
    root: string | null;
    endpoint: string | null;
    url: string | null;
    access_key: string | null;
    has_credentials: boolean;
    path_style_endpoint: boolean;
    visibility: string;
    is_default: boolean;
    is_active: boolean;
    assigned_teams_count: number;
    stored_bytes: number;
    last_tested_at: string | null;
    last_test_error: string | null;
};

export type StorageOrganization = {
    id: number;
    name: string;
    slug: string;
    storage_disk_id: number | null;
};
