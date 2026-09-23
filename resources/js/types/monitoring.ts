export type ScreenHealthRecord = {
    id: number;
    name: string;
    status: 'online' | 'offline' | 'warning' | 'updating' | 'disabled';
    status_label: string;
    paired: boolean;
    last_seen_at: string | null;
    current_channel: string | null;
    current_content: string | null;
    storage_available: number | null;
    storage_total: number | null;
    app_version: string | null;
    last_error: string | null;
    network_status: string | null;
    memory_usage: number | null;
    cpu_usage: number | null;
    playing_offline: boolean;
};

export type MonitoringSummary = {
    counts: {
        healthy: number;
        warning: number;
        offline: number;
        disabled: number;
    };
    screens: ScreenHealthRecord[];
    thresholds: {
        healthy_seconds: number;
        warning_seconds: number;
    } | null;
};
