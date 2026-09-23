export type ApiScopeOption = {
    value: string;
    label: string;
};

export type PartnerApiToken = {
    id: number;
    name: string;
    token_prefix: string;
    scopes: string[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string | null;
    created_by: string | null;
};

export type PartnerWebhookEndpoint = {
    id: number;
    url: string;
    events: string[];
    is_active: boolean;
    last_delivery_at: string | null;
    created_at: string | null;
};

export type PartnerWebhookDelivery = {
    id: number;
    uuid: string;
    event: string;
    status: string;
    attempts: number;
    response_code: number | null;
    url: string;
    created_at: string | null;
};
