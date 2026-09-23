export type NotificationChannelFlags = {
    email: boolean;
    in_app: boolean;
    webhook: boolean;
    slack: boolean;
};

export type NotificationPreferenceRow = NotificationChannelFlags & {
    label: string;
};

export type InAppNotificationItem = {
    id: number;
    event: string;
    event_label: string;
    title: string;
    body: string;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationSettings = {
    webhook_url: string | null;
    slack_webhook_url: string | null;
    min_player_version: string | null;
    preferences: Record<string, NotificationPreferenceRow>;
};
