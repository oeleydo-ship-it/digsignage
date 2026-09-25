export type AppReleaseStatus =
    | 'ready'
    | 'queued'
    | 'installing'
    | 'active'
    | 'inactive'
    | 'failed';

export type AppRelease = {
    id: number;
    version: string;
    title: string | null;
    notes: string | null;
    features: string[];
    source: 'github' | 'upload' | 'pipeline' | 'existing';
    source_label: string;
    source_ref: string | null;
    status: AppReleaseStatus;
    status_label: string;
    error: string | null;
    checksum: string | null;
    package_size: number | null;
    can_install: boolean;
    can_rollback: boolean;
    can_delete: boolean;
    created_by: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    activated_at: string | null;
};

export type AvailableRelease = {
    repository: string;
    tag: string;
    version: string;
    name: string | null;
    features: string[];
    notes: string | null;
    published_at: string | null;
    prerelease: boolean;
    url: string;
    has_package: boolean;
    installed: boolean;
};

export type UpdaterStatus = {
    enabled: boolean;
    reason: string | null;
    base_path: string | null;
    current_path: string | null;
};
