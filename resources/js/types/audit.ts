export type AuditLogRecord = {
    id: number;
    action: string;
    action_label: string;
    resource_type: string | null;
    resource_id: number | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    user_name: string | null;
    user_email: string | null;
    created_at: string | null;
};

export type AuditLogFilters = {
    action: string;
    resource_type: string;
    user_id: string;
    search: string;
    from: string;
    until: string;
};
