export type PlatformMetrics = {
    organizations: number;
    active_subscriptions: number;
    mrr_cents: number;
    screens: number;
    online_screens: number;
    offline_screens: number;
    storage_bytes: number;
    bandwidth_bytes: number;
    users: number;
    queue: {
        connection: string;
        pending: number;
        failed: number;
    };
};

export type ImpersonationState = {
    actor_name: string;
    actor_email: string;
    target_name: string | null;
};

export type PlatformAnnouncement = {
    id: number;
    title: string;
    body: string;
    published_at: string | null;
};
