export type EmergencyPermissions = {
    canViewEmergencies: boolean;
    canCreateEmergency: boolean;
    canUpdateEmergency: boolean;
    canStartEmergency: boolean;
    canStopEmergency: boolean;
};

export type EmergencyRecord = {
    id: number;
    title: string;
    severity: string;
    severity_label: string;
    status: string;
    status_label: string;
    starts_at: string | null;
    expires_at: string | null;
    started_at: string | null;
    deliveries_count: number;
    updated_at: string | null;
};

export type EmergencyDetail = EmergencyRecord & {
    message: string | null;
    instructions: string | null;
    background: string | null;
    image_id: number | null;
    image_name: string | null;
    video_id: number | null;
    video_name: string | null;
    stopped_at: string | null;
    created_by: string | null;
    started_by: string | null;
    stopped_by: string | null;
    audience_count: number;
    screen_ids: number[];
    location_ids: number[];
    targets: { type: string; label: string | null }[];
};

export type EmergencyDeliveryRecord = {
    id: number;
    screen_id: number;
    screen_name: string | null;
    status: string;
    status_label: string;
    sent_at: string | null;
    acknowledged_at: string | null;
    completed_at: string | null;
};

export type EmergencyAuditRecord = {
    id: number;
    action: string;
    action_label: string;
    user: string | null;
    screen_id: number | null;
    payload: Record<string, unknown> | null;
    created_at: string | null;
};
