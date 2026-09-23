export type AnalyticsBar = { label: string; value: number };

export type AnalyticsDailyOps = {
    label: string;
    downloads: number;
    crashes: number;
    sync: number;
    errors: number;
    commands: number;
};

export type AnalyticsReport = {
    screens: {
        online: number;
        warning: number;
        offline: number;
        disabled: number;
        uptime_percent: number;
        storage_warnings: number;
        versions: AnalyticsBar[];
        status: AnalyticsBar[];
    };
    content: {
        plays: number;
        duration_ms: number;
        screens_reached: number;
        locations_reached: number;
        top: AnalyticsBar[];
        daily: AnalyticsBar[];
    };
    operations: {
        failed_downloads: number;
        crashes: number;
        sync_failures: number;
        device_errors: number;
        command_failures: number;
        daily: AnalyticsDailyOps[];
    };
};
